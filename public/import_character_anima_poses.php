<?php 
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// Ensure PDO settings for safety
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pageTitle = "IMPORT CHARACTER ANIMA POSES";

// =========================================================================================
// API HANDLER (AJAX Engine)
// =========================================================================================
if (isset($_REQUEST['api_action'])) {
    while (ob_get_level()) ob_end_clean();
    header('Content-Type: application/json');
    $action = $_REQUEST['api_action'];

    try {
        $req = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET;
        
        // Common parameters
        $char_from = intval($req['character_from'] ?? 1);
        $char_to   = intval($req['character_to']   ?? 1);
        $pose_from = intval($req['pose_from'] ?? 1);
        $pose_to   = intval($req['pose_to']   ?? 999);
        
        $angles       = isset($req['angles'])       && is_array($req['angles'])       ? array_map('intval', $req['angles'])       : [];
        $perspectives = isset($req['perspectives']) && is_array($req['perspectives']) ? array_map('intval', $req['perspectives']) : [];

        // ----------------------------------------------------
        // GET FRAME URL (For Reference Image Preview)
        // ----------------------------------------------------
        if ($action === 'get_frame_url') {
            $id   = intval($req['frame_id']);
            $stmt = $pdo->prepare("SELECT filename FROM frames WHERE id = ?");
            $stmt->execute([$id]);
            $url  = $stmt->fetchColumn();
            echo json_encode(['status' => 'success', 'url' => $url]);
            exit;
        }

        // ----------------------------------------------------
        // PREVIEW or IMPORT (Tab 1 Logic)
        // ----------------------------------------------------
        if ($action === 'preview' || $action === 'import') {
            if (empty($angles) || empty($perspectives)) {
                echo json_encode(['status' => 'success', 'total' => 0, 'new_count' => 0, 'ext_count' => 0, 'new_rows' => [], 'ext_rows' => []]);
                exit;
            }

            // Exclusions logic (from clicked pills)
            $exclusions = json_decode($req['exclusions'] ?? '[]', true);
            $excludeSql = "";
            if (!empty($exclusions)) {
                $exclList   = implode(',', array_map([$pdo, 'quote'], $exclusions));
                $excludeSql = "AND CONCAT(v.character_id, '_', v.pose_id, '_', v.angle_id, '_', v.perspective_id) NOT IN ($exclList)";
            }

            $anglePl = implode(',', array_fill(0, count($angles),       '?'));
            $perspPl = implode(',', array_fill(0, count($perspectives), '?'));
            $params  = array_merge([$char_from, $char_to, $pose_from, $pose_to], $angles, $perspectives);

            $baseWhere = "WHERE v.character_id BETWEEN ? AND ?
                          AND v.pose_id BETWEEN ? AND ?
                          AND v.angle_id IN ($anglePl)
                          AND v.perspective_id IN ($perspPl) $excludeSql";

            $notExistsClause = "AND NOT EXISTS (
                SELECT 1 FROM character_anima_poses cap
                WHERE cap.character_id   = v.character_id
                  AND cap.pose_id        = v.pose_id
                  AND cap.angle_id       = v.angle_id
                  AND cap.perspective_id = v.perspective_id
            )";
            $existsClause = "AND EXISTS (
                SELECT 1 FROM character_anima_poses cap
                WHERE cap.character_id   = v.character_id
                  AND cap.pose_id        = v.pose_id
                  AND cap.angle_id       = v.angle_id
                  AND cap.perspective_id = v.perspective_id
            )";

            $excludeChar = !empty($req['exclude_char_desc']);
            $neutralBg   = !empty($req['neutral_white_bg']);
            $promptSql   = $excludeChar ? "v.base_prompt" : "v.description";
            if ($neutralBg) {
                $promptSql = "CONCAT('((neutral greenscreen background - Pantone: 354 C, Hex: #00FB00)), ', $promptSql)";
            }

            // ── IMPORT ────────────────────────────────────────────────────────
            if ($action === 'import') {
                $force = !empty($req['force_update']);
                $imgId = intval($req['img2img_frame_id'] ?? 0);

                $insertCols = "name, `order`, description, character_id, pose_id, angle_id, perspective_id, regenerate_images";
                $selectCols = "CONCAT(v.character_name, ' - ', v.pose_name, ' - ', v.angle_name, ' - ', v.perspective_name),
                               0, $promptSql, v.character_id, v.pose_id, v.angle_id, v.perspective_id, 1";

                if ($imgId > 0) {
                    $insertCols .= ", img2img_frame_id, img2img";
                    $selectCols .= ", $imgId, 1";
                }

                $pdo->beginTransaction();
                $inserted = 0;
                $updated  = 0;

                if ($force) {
                    $updateSql = "name = VALUES(name), description = VALUES(description), regenerate_images = 1";
                    if ($imgId > 0) {
                        $updateSql .= ", img2img_frame_id = VALUES(img2img_frame_id), img2img = VALUES(img2img)";
                    }
                    $sql  = "INSERT INTO character_anima_poses ($insertCols)
                             SELECT $selectCols
                             FROM v_character_anima_pose_angle_combinations v
                             $baseWhere
                             ON DUPLICATE KEY UPDATE $updateSql";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $updated = $stmt->rowCount();
                } else {
                    $sql  = "INSERT INTO character_anima_poses ($insertCols)
                             SELECT $selectCols
                             FROM v_character_anima_pose_angle_combinations v
                             $baseWhere $notExistsClause";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $inserted = $stmt->rowCount();
                }
                $pdo->commit();

                echo json_encode([
                    'status'  => 'success',
                    'message' => "Success! Processed $updated existing items and inserted $inserted new ones. Regeneration triggered."
                ]);
                exit;
            }

            // ── PREVIEW ───────────────────────────────────────────────────────
            $page_insert = max(1, intval($req['page_insert'] ?? 1));
            $page_update = max(1, intval($req['page_update'] ?? 1));
            $limit  = 100;
            $off_ins = ($page_insert - 1) * $limit;
            $off_upd = ($page_update - 1) * $limit;

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM v_character_anima_pose_angle_combinations v $baseWhere");
            $stmt->execute($params);
            $totalMatches = (int)$stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM v_character_anima_pose_angle_combinations v $baseWhere $notExistsClause");
            $stmt->execute($params);
            $insertableMatches = (int)$stmt->fetchColumn();
            $updateMatches     = $totalMatches - $insertableMatches;

            $newRows = [];
            if ($insertableMatches > 0) {
                $sql  = "SELECT v.character_id, v.pose_id, v.angle_id, v.perspective_id,
                                v.character_name, v.pose_name, v.angle_name, v.perspective_name,
                                $promptSql as description
                         FROM v_character_anima_pose_angle_combinations v
                         $baseWhere $notExistsClause
                         ORDER BY v.character_id, v.pose_id
                         LIMIT $limit OFFSET $off_ins";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $newRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $extRows = [];
            if ($updateMatches > 0) {
                $sql  = "SELECT v.character_id, v.pose_id, v.angle_id, v.perspective_id,
                                v.character_name, v.pose_name, v.angle_name, v.perspective_name,
                                (SELECT f.filename
                                 FROM character_anima_poses cap
                                 JOIN frames_2_character_anima_poses m ON m.to_id = cap.id
                                 JOIN frames f ON f.id = m.from_id
                                 WHERE cap.character_id   = v.character_id
                                   AND cap.pose_id        = v.pose_id
                                   AND cap.angle_id       = v.angle_id
                                   AND cap.perspective_id = v.perspective_id
                                 ORDER BY m.from_id DESC LIMIT 1) as mapped_thumb
                         FROM v_character_anima_pose_angle_combinations v
                         $baseWhere $existsClause
                         ORDER BY v.character_id, v.pose_id
                         LIMIT $limit OFFSET $off_upd";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $extRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode([
                'status'     => 'success',
                'total'      => $totalMatches,
                'new_count'  => $insertableMatches,
                'ext_count'  => $updateMatches,
                'new_rows'   => $newRows,
                'ext_rows'   => $extRows,
                'page_insert' => $page_insert,
                'page_update' => $page_update,
                'max_p_ins'  => ceil($insertableMatches / $limit),
                'max_p_upd'  => ceil($updateMatches     / $limit),
            ]);
            exit;
        }

        // ----------------------------------------------------
        // MAPPING: GET POSES (Tab 2 Logic)
        // ----------------------------------------------------
        if ($action === 'get_poses_for_mapping') {
            if (empty($angles) || empty($perspectives)) {
                echo json_encode(['status' => 'success', 'data' => []]);
                exit;
            }

            $anglePl = implode(',', array_fill(0, count($angles),       '?'));
            $perspPl = implode(',', array_fill(0, count($perspectives), '?'));
            $params  = array_merge([$char_from, $char_to, $pose_from, $pose_to], $angles, $perspectives);

            $sql  = "SELECT
                         cap.id   as pose_id,
                         c.name   as character_name,
                         p.name   as pose_name,
                         ca.name  as angle_name,
                         cpe.name as perspective_name,
                         (SELECT f.filename
                          FROM frames_2_character_anima_poses m
                          JOIN frames f ON f.id = m.from_id
                          WHERE m.to_id = cap.id
                          ORDER BY m.from_id DESC LIMIT 1) as mapped_thumb
                     FROM character_anima_poses cap
                     JOIN characters         c   ON cap.character_id   = c.id
                     JOIN poses_anima        p   ON cap.pose_id        = p.id
                     JOIN camera_angles      ca  ON cap.angle_id       = ca.id
                     JOIN camera_perspectives cpe ON cap.perspective_id = cpe.id
                     WHERE cap.character_id   BETWEEN ? AND ?
                       AND cap.pose_id        BETWEEN ? AND ?
                       AND cap.angle_id       IN ($anglePl)
                       AND cap.perspective_id IN ($perspPl)
                     ORDER BY cap.character_id, cap.pose_id, cap.perspective_id, cap.angle_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        // ----------------------------------------------------
        // MAPPING: GET FRAMES
        // ----------------------------------------------------
        if ($action === 'get_run_frames') {
            $runId = intval($req['map_run_id'] ?? 0);
            $stmt  = $pdo->prepare("SELECT id, filename FROM frames WHERE map_run_id = ? ORDER BY id ASC");
            $stmt->execute([$runId]);
            echo json_encode(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        // ----------------------------------------------------
        // MAPPING: ASSIGN FRAME TO ANIMA POSE
        // ----------------------------------------------------
        if ($action === 'map_frame') {
            $frameId = intval($req['frame_id']);
            $poseId  = intval($req['pose_id']);

            // Toggle logic — identical to the original system
            $stmt = $pdo->prepare("SELECT 1 FROM frames_2_character_anima_poses WHERE from_id = ? AND to_id = ?");
            $stmt->execute([$frameId, $poseId]);
            if ($stmt->fetchColumn()) {
                $pdo->prepare("DELETE FROM frames_2_character_anima_poses WHERE from_id = ? AND to_id = ?")->execute([$frameId, $poseId]);
                $mapped = false;
            } else {
                $pdo->prepare("INSERT IGNORE INTO frames_2_character_anima_poses (from_id, to_id) VALUES (?, ?)")->execute([$frameId, $poseId]);
                $mapped = true;
            }

            // Re-fetch thumbnail for UI refresh
            $thumb = $pdo->prepare("SELECT f.filename
                                    FROM frames_2_character_anima_poses m
                                    JOIN frames f ON f.id = m.from_id
                                    WHERE m.to_id = ?
                                    ORDER BY m.from_id DESC LIMIT 1");
            $thumb->execute([$poseId]);
            $filename = $thumb->fetchColumn();

            echo json_encode(['status' => 'success', 'mapped' => $mapped, 'thumb' => $filename]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// =========================================================================================
// DATA FETCHING FOR UI LOAD
// =========================================================================================
try {
    $allAngles       = $pdo->query("SELECT id, name, description FROM camera_angles      ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
    $allPerspectives = $pdo->query("SELECT id, name, description FROM camera_perspectives ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die("Error fetching camera data: " . $e->getMessage());
}

ob_start();
?>
<!-- Dependencies -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="/css/base.css">
<!-- PhotoSwipe CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/photoswipe/5.4.2/photoswipe.min.css">

<style>
    /* ── ANIMA POSES THEME — clone of FRAMETAGGER, accent shifted to amber/gold ── */
    :root {
        --bg:         #0a0a0f;
        --card:       #111118;
        --border:     #1e1e2e;
        --text:       #e2e2f0;
        --text-muted: #555570;
        --blue:       #3b82f6;
        --green:      #10b981;
        --amber:      #f59e0b;
        --cyan:       #06b6d4;
        --purple:     #8b5cf6;
        --gold:       #fbbf24;   /* Anima accent — replaces purple as primary */
        --red:        #ef4444;
    }

    *, *::before, *::after { box-sizing: border-box; }
    html, body { margin: 0; background: var(--bg); color: var(--text); font-family: 'DM Mono', 'Fira Mono', monospace; height: 100%; overflow: hidden; }

    /* ── LAYOUT ── */
    .eh-layout   { display: flex; flex-direction: column; height: 100vh; overflow: hidden; width: 100%; }
    .eh-header   { flex-shrink: 0; padding: 12px 20px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
    .eh-header-left  { display: flex; align-items: center; }
    .eh-title    { font-size: 1rem; font-weight: 700; letter-spacing: 1px; color: var(--gold); margin: 0; text-transform: uppercase; }
    .eh-header-right { display: flex; gap: 10px; align-items: center; }

    .hamburger-btn { display: none; background: transparent; border: 1px solid var(--border); color: var(--text); font-size: 1.5rem; padding: 4px 10px; border-radius: 4px; cursor: pointer; margin-left: 70px; margin-right: 15px; }

    /* TABS */
    .eh-tabs { display: flex; border-bottom: 1px solid var(--border); background: var(--card); flex-shrink: 0; padding: 0 10px; }
    .eh-tab  { padding: 12px 20px; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); cursor: pointer; border-bottom: 2px solid transparent; text-transform: uppercase; letter-spacing: 1px; transition: 0.2s; }
    .eh-tab:hover { color: var(--text); }
    .eh-tab.active { color: var(--gold); border-bottom-color: var(--gold); }

    .eh-body { flex: 1; display: flex; overflow: hidden; position: relative; }

    /* ── SIDEBAR ── */
    .eh-sidebar { width: 340px; background: var(--card); border-right: 1px solid var(--border); display: flex; flex-direction: column; overflow-y: auto; padding: 20px; flex-shrink: 0; z-index: 10000; transition: left 0.3s ease; }
    .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 9999; display: none; backdrop-filter: blur(2px); }
    .sidebar-header-mobile { display: none; }

    .sidebar-section { margin-bottom: 25px; }
    .section-title   { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); font-weight: 700; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 6px; }
    .section-title a { color: var(--blue); text-decoration: none; font-size: 0.65rem; padding: 2px 6px; border-radius: 3px; background: rgba(59,130,246,0.1); }

    .chips-container { display: flex; flex-wrap: wrap; gap: 6px; }
    .chip-label      { display: inline-block; cursor: pointer; margin: 0; }
    .chip-label input { display: none; }
    .chip-span { padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; border: 1px solid var(--border); background: rgba(255,255,255,0.02); color: var(--text-muted); transition: 0.15s; display: inline-block; }
    .chip-label input:checked + .chip-span.persp { background: rgba(251,191,36,0.15); border-color: rgba(251,191,36,0.5);  color: var(--gold); }
    .chip-label input:checked + .chip-span.angle { background: rgba(6,182,212,0.15);  border-color: rgba(6,182,212,0.5);   color: var(--cyan); }

    .form-row-compact { display: flex; gap: 10px; margin-bottom: 12px; }
    .form-col { flex: 1; display: flex; flex-direction: column; gap: 4px; }
    .form-col label { font-size: 0.7rem; color: var(--text-muted); font-weight: 700; }
    .dark-input { background: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 4px; padding: 8px 12px; font-family: inherit; font-size: 0.8rem; width: 100%; outline: none; }
    .dark-input:focus { border-color: var(--blue); }

    .check-wrap  { display: flex; align-items: center; gap: 8px; margin-bottom: 12px; cursor: pointer; font-size: 0.75rem; color: var(--text); }
    .review-check { width: 18px; height: 18px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); appearance: none; cursor: pointer; position: relative; flex-shrink: 0; }
    .review-check:checked { background: var(--green); border-color: var(--green); }
    .review-check:checked::after { content: ''; position: absolute; top: 1px; left: 5px; width: 6px; height: 11px; border: 2px solid #000; border-top: none; border-left: none; transform: rotate(45deg); }

    /* ── BUTTONS ── */
    .action-btn { padding: 8px 16px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; border: 1px solid var(--border); background: var(--card); color: var(--text); cursor: pointer; text-transform: uppercase; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; }
    .action-btn:hover { background: rgba(255,255,255,0.05); }
    .action-btn.primary { border-color: var(--gold); color: #000; background: rgba(251,191,36,0.3); }
    .action-btn.primary:hover { background: rgba(251,191,36,0.5); box-shadow: 0 0 15px rgba(251,191,36,0.3); }

    /* ── MAIN AREA ── */
    .eh-main  { flex: 1; display: flex; flex-direction: column; overflow: hidden; background: #000; position: relative; }
    .tab-view { display: none; height: 100%; flex-direction: column; overflow: hidden; }

    /* ── IMPORT TAB ── */
    .eh-mid-panel { flex-shrink: 0; background: var(--card); border-bottom: 1px solid var(--border); padding: 10px 20px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
    .stats-row    { display: flex; gap: 10px; font-size: 0.75rem; font-weight: 700; flex-wrap: wrap; }
    .stat-item    { display: flex; align-items: center; gap: 6px; padding: 4px 10px; border-radius: 4px; border: 1px solid var(--border); background: rgba(0,0,0,0.2); }
    .s-blue  { color: var(--blue);  border-color: rgba(59,130,246,0.3); }
    .s-green { color: var(--green); border-color: rgba(16,185,129,0.3); }
    .s-amber { color: var(--amber); border-color: rgba(245,158,11,0.3); }

    .mr-pagination { display: flex; align-items: center; gap: 6px; }
    .pg-btn  { width: 30px; height: 30px; background: var(--bg); border: 1px solid var(--border); color: var(--text-muted); border-radius: 4px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-weight: bold; }
    .pg-btn:hover:not(:disabled) { border-color: var(--blue); color: var(--blue); }
    .pg-btn:disabled { opacity: 0.3; cursor: not-allowed; }
    .pg-text { font-size: 0.75rem; color: var(--text-muted); font-weight: 700; }

    .eh-grid-area  { flex: 1; overflow-y: auto; padding: 20px; }
    .console-msg   { padding: 12px 16px; margin-bottom: 15px; border-radius: 4px; font-size: 0.8rem; border-left: 4px solid var(--border); background: var(--card); display: none; }
    .msg-success   { border-left-color: var(--green); color: #6ee7b7; display: block; }

    .table-wrapper   { margin-bottom: 30px; border-radius: 6px; overflow: hidden; border: 1px solid var(--border); background: var(--card); width: 100%; overflow-x: auto; }
    .table-header-bar { padding: 10px 15px; background: rgba(0,0,0,0.4); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; font-weight: 700; }
    .h-success { color: var(--green); }
    .h-warning { color: var(--amber); }

    .dark-table    { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.8rem; }
    .dark-table th { text-align: left; padding: 10px 15px; border-bottom: 1px solid var(--border); color: var(--text-muted); text-transform: uppercase; font-size: 0.7rem; font-weight: 700; white-space: nowrap; }
    .dark-table td { padding: 12px 15px; border-bottom: 1px solid var(--border); color: var(--text); vertical-align: middle; background: var(--bg); white-space: nowrap; }
    .dark-table tr:hover td { background: rgba(255,255,255,0.02); }
    .dark-table tr:last-child td { border-bottom: none; }

    .badge-chip  { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 0.65rem; font-weight: 700; letter-spacing: 0.5px; margin-right: 4px; transition: 0.2s; border: 1px solid transparent; }
    /* Anima pose badge uses gold instead of amber */
    .b-pose  { background: rgba(251,191,36,0.15); border-color: rgba(251,191,36,0.3); color: var(--gold);   cursor: pointer; }
    .b-pose:hover { border-color: var(--gold); }
    .b-pose.excluded { background: rgba(255,255,255,0.05) !important; border-color: var(--border) !important; color: var(--text-muted) !important; text-decoration: line-through; opacity: 0.5; }
    .b-persp { background: rgba(251,191,36,0.1);  border-color: rgba(251,191,36,0.3); color: var(--gold);   }
    .b-angle { background: rgba(6,182,212,0.15);  border-color: rgba(6,182,212,0.3);  color: var(--cyan);   }
    .b-ref   { background: rgba(236,72,153,0.15); border-color: rgba(236,72,153,0.3); color: #f472b6;       }
    .b-status{ background: rgba(16,185,129,0.15); border-color: rgba(16,185,129,0.3); color: var(--green);  }

    .desc-cell { max-width: 400px; overflow: hidden; text-overflow: ellipsis; cursor: pointer; color: var(--text-muted); transition: 0.2s; font-family: 'DM Mono', monospace; font-size: 0.75rem; }
    .desc-cell:hover { color: var(--blue); }

    .empty-state { padding: 40px; text-align: center; color: var(--text-muted); display: flex; flex-direction: column; align-items: center; gap: 10px; font-size: 0.9rem; }
    .empty-state span { font-size: 2.5rem; }

    /* ── MAPPING TAB ── */
    .map-layout      { display: flex; height: 100%; gap: 1px; overflow: hidden; }
    .map-sidebar-list{ width: 350px; flex-shrink: 0; background: var(--card); border-right: 1px solid var(--border); overflow-y: auto; display: flex; flex-direction: column; }
    .map-pose-item   { padding: 12px 15px; border-bottom: 1px solid var(--border); cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: 0.2s; }
    .map-pose-item:hover  { background: rgba(255,255,255,0.03); }
    .map-pose-item.active { background: rgba(251,191,36,0.12); border-left: 4px solid var(--gold); padding-left: 11px; }
    .map-pose-info   { font-size: 0.75rem; line-height: 1.4; color: var(--text-muted); }
    .map-pose-info strong { color: var(--text); font-size: 0.8rem; display: block; margin-bottom: 2px; }
    .map-thumb-wrap  { width: 44px; height: 44px; flex-shrink: 0; background: rgba(0,0,0,0.3); border-radius: 4px; overflow: hidden; margin-left: 10px; border: 1px solid var(--border); }

    .map-grid-area   { flex: 1; background: var(--bg); padding: 15px; overflow-y: auto; }
    .grid-container  { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; }
    .grid-frame      { aspect-ratio: 1/1; cursor: pointer; border: 2px solid transparent; border-radius: 4px; overflow: hidden; transition: 0.2s; background: #000; position: relative; }
    .grid-frame:hover { border-color: var(--blue); transform: scale(1.02); z-index: 2; box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
    .grid-frame img  { width: 100%; height: 100%; object-fit: cover; }
    .grid-frame .id-badge { position: absolute; top: 4px; left: 4px; background: rgba(0,0,0,0.7); color: #fff; font-size: 0.65rem; padding: 2px 4px; border-radius: 3px; font-weight: bold; pointer-events: none; }

    /* ── MODALS ── */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.85); display: none; align-items: center; justify-content: center; z-index: 12000; backdrop-filter: blur(5px); }
    .modal-overlay.active { display: flex; }
    .modal-content { background: var(--card); border-radius: 8px; border: 1px solid var(--border); display: flex; flex-direction: column; width: 600px; max-width: 90%; max-height: 80vh; }
    .modal-header  { padding: 12px 20px; border-bottom: 1px solid var(--border); font-weight: 700; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; background: #000; }
    .modal-body    { padding: 20px; overflow-y: auto; font-size: 0.85rem; line-height: 1.6; white-space: pre-wrap; }
    .close-btn     { background: transparent; border: none; color: var(--text-muted); cursor: pointer; font-size: 1.2rem; display: flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 4px; }
    .close-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }

    /* ── RESPONSIVE ── */
    @media (max-width: 900px) {
        .hamburger-btn { display: block; }
        .eh-title span { display: none; }
        .action-btn { padding: 8px; font-size: 0.7rem; }
        .action-btn .btn-text { display: none; }
        .eh-sidebar { position: absolute; top: 0; left: -100%; height: 100%; width: 85vw; max-width: 320px; box-shadow: 5px 0 20px rgba(0,0,0,0.5); }
        .eh-sidebar.open { left: 0; }
        .sidebar-header-mobile { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border); }
        .close-sidebar-btn { background: transparent; border: none; color: var(--text); font-size: 1.5rem; cursor: pointer; }
        .map-layout { flex-direction: column; }
        .map-sidebar-list { width: 100%; height: 35vh; border-right: none; border-bottom: 1px solid var(--border); }
    }
</style>

<div class="eh-layout">

    <!-- HEADER -->
    <div class="eh-header">
        <div class="eh-header-left">
            <button type="button" class="hamburger-btn" onclick="toggleSidebar()">☰</button>
            <h1 class="eh-title">⚡ IMPORT CHARACTER ANIMA POSES</h1>
        </div>
        <div class="eh-header-right tab-import-only">
            <button type="button" class="action-btn" onclick="loadPreview()">
                <span>🔄</span> <span class="btn-text">Refresh Preview</span>
            </button>
            <button type="button" class="action-btn primary" onclick="runImport()">
                <span>⚡</span> <span class="btn-text">Run Import</span>
            </button>
        </div>
    </div>

    <!-- TABS -->
    <div class="eh-tabs">
        <div class="eh-tab active" id="tab-btn-import" onclick="switchTab('import')">1. Import Combinations</div>
        <div class="eh-tab"        id="tab-btn-map"    onclick="switchTab('map')">2. Map Frames</div>
    </div>

    <!-- MAIN BODY -->
    <form id="appForm" class="eh-body" onsubmit="return false;">

        <!-- SIDEBAR -->
        <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
        <div class="eh-sidebar" id="appSidebar">
            <div class="sidebar-header-mobile">
                <span style="font-weight: bold; color: var(--gold); font-size: 0.85rem; letter-spacing: 1px;">⚙️ FILTERS</span>
                <button type="button" class="close-sidebar-btn" onclick="toggleSidebar()">&times;</button>
            </div>

            <div class="sidebar-section">
                <div class="section-title">
                    1. Camera Perspectives
                    <span>
                        <a href="#" onclick="toggleChecks('perspectives[]', true);  triggerLoad(); return false;">All</a> |
                        <a href="#" onclick="toggleChecks('perspectives[]', false); triggerLoad(); return false;">None</a>
                    </span>
                </div>
                <div class="chips-container">
                    <?php foreach ($allPerspectives as $p): ?>
                        <label class="chip-label" title="<?= htmlspecialchars($p['description'] ?? $p['name']) ?>">
                            <input type="checkbox" name="perspectives[]" value="<?= $p['id'] ?>" onchange="triggerLoad()">
                            <span class="chip-span persp"><?= htmlspecialchars($p['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sidebar-section">
                <div class="section-title">
                    2. Camera Angles
                    <span>
                        <a href="#" onclick="toggleChecks('angles[]', true);  triggerLoad(); return false;">All</a> |
                        <a href="#" onclick="toggleChecks('angles[]', false); triggerLoad(); return false;">None</a>
                    </span>
                </div>
                <div class="chips-container">
                    <?php foreach ($allAngles as $angle): ?>
                        <label class="chip-label" title="<?= htmlspecialchars($angle['description'] ?? $angle['name']) ?>">
                            <input type="checkbox" name="angles[]" value="<?= $angle['id'] ?>" onchange="triggerLoad()">
                            <span class="chip-span angle"><?= htmlspecialchars($angle['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="sidebar-section">
                <div class="section-title">3. Character Range</div>
                <div class="form-row-compact">
                    <div class="form-col"><label>Char From</label><input type="number" name="character_from" class="dark-input" value="1" oninput="debounceLoad()"></div>
                    <div class="form-col"><label>Char To</label>  <input type="number" name="character_to"   class="dark-input" value="1" oninput="debounceLoad()"></div>
                </div>
                <div class="form-row-compact">
                    <div class="form-col"><label>Pose From</label><input type="number" name="pose_from" class="dark-input" value="1"   oninput="debounceLoad()"></div>
                    <div class="form-col"><label>Pose To</label>  <input type="number" name="pose_to"   class="dark-input" value="999" oninput="debounceLoad()"></div>
                </div>
            </div>

            <!-- IMPORT OPTIONS -->
            <div class="sidebar-section tab-import-only">
                <div class="section-title">4. Import Options</div>
                <div class="form-col" style="margin-bottom: 15px;">
                    <label>Reference Frame ID (Optional)</label>
                    <div style="display: flex; gap: 8px;">
                        <input type="number" id="img2img_frame_id" name="img2img_frame_id" class="dark-input" placeholder="e.g. 500" oninput="debounceLoadRef()">
                    </div>
                    <div id="refPreviewContainer" style="margin-top: 15px; text-align: center;"></div>
                </div>
                <label class="check-wrap"><input type="checkbox" name="exclude_char_desc" value="1" class="review-check" onchange="loadPreview()"> Exclude Character Prompt</label>
                <label class="check-wrap"><input type="checkbox" name="neutral_white_bg"  value="1" class="review-check" onchange="loadPreview()"> Use Neutral BG Prefix</label>
                <label class="check-wrap"><input type="checkbox" name="force_update"      value="1" class="review-check"> Force Update Existing</label>
                <input type="hidden" name="page_insert" id="page_insert" value="1">
                <input type="hidden" name="page_update" id="page_update" value="1">
            </div>

            <!-- MAPPING OPTIONS -->
            <div class="sidebar-section tab-map-only" style="display:none;">
                <div class="section-title">4. Map Run Source</div>
                <div class="form-col">
                    <label>Map Run ID</label>
                    <input type="number" id="map_run_id" name="map_run_id" class="dark-input" placeholder="Enter ID to load frames..." oninput="debounceLoadFrames()">
                </div>
            </div>
        </div>

        <!-- MAIN AREA -->
        <div class="eh-main pswp-gallery">

            <div id="msgContainer" class="console-msg"></div>

            <!-- TAB 1: IMPORT VIEW -->
            <div id="view-import" class="tab-view" style="display:flex;">
                <div class="eh-mid-panel">
                    <div class="stats-row" id="statsRow">
                        <div class="stat-item s-blue">🔍 Total: 0</div>
                        <div class="stat-item s-green">✨ New: 0</div>
                        <div class="stat-item s-amber">⚠️ Existing: 0</div>
                    </div>
                    <div class="mr-pagination" id="pgInsertUI" style="display:none;">
                        <span style="font-size:0.7rem; color:var(--text-muted); font-weight:bold; margin-right:5px;">NEW ENTRIES:</span>
                        <button type="button" class="pg-btn" onclick="changePage('ins', -1)">&#8592;</button>
                        <span class="pg-text" id="lbl_pg_ins">Page 1 / 1</span>
                        <button type="button" class="pg-btn" onclick="changePage('ins', 1)">&#8594;</button>
                    </div>
                </div>

                <div class="eh-grid-area" id="importContainers">
                    <div class="empty-state">
                        <span>⚡</span><div>Select Filters on the left to generate an Anima Pose preview.</div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: MAPPING VIEW -->
            <div id="view-map" class="tab-view map-layout">
                <div class="map-sidebar-list" id="mapPosesList">
                    <div class="empty-state" style="padding:20px;"><span>📋</span><div style="font-size:0.75rem;">Loading Anima poses...</div></div>
                </div>
                <div class="map-grid-area">
                    <div class="grid-container" id="mapFramesGrid">
                        <div class="empty-state" style="grid-column: 1/-1;"><span>🖼️</span><div>Enter a Map Run ID to load frames for assignment.</div></div>
                    </div>
                </div>
            </div>

        </div>
    </form>
</div>

<!-- Modal -->
<div id="descModal" class="modal-overlay" onclick="closeDescModal(event)">
    <div class="modal-content">
        <div class="modal-header">
            <span>Full Anima Description Prompt</span>
            <button type="button" class="close-btn" onclick="closeDescModal(event)">&times;</button>
        </div>
        <div id="descModalText" class="modal-body"></div>
    </div>
</div>

<script>
// --- STATE ---
let currentTab = 'import';
let excludedCombinations = new Set();
let timerLoad = null, timerRef = null, timerFrames = null;
let activeMapPoseId = null;
let curPageIns = 1, curPageUpd = 1;
let maxPageIns = 1, maxPageUpd = 1;

// --- PHOTOSWIPE ---
let lightbox = null;
async function initPhotoswipe() {
    if (lightbox) { lightbox.destroy(); }
    const module = await import('https://cdnjs.cloudflare.com/ajax/libs/photoswipe/5.4.2/photoswipe-lightbox.esm.min.js');
    const PhotoSwipeLightbox = module.default;
    lightbox = new PhotoSwipeLightbox({
        gallery: '.pswp-gallery',
        children: 'a.pswp-link',
        pswpModule: () => import('https://cdnjs.cloudflare.com/ajax/libs/photoswipe/5.4.2/photoswipe.esm.min.js')
    });
    lightbox.init();
}

// --- TAB SWITCHING ---
function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.eh-tab').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-btn-' + tab).classList.add('active');
    document.querySelectorAll('.tab-view').forEach(el => el.style.display = 'none');
    document.getElementById('view-' + tab).style.display = 'flex';
    document.querySelectorAll('.tab-import-only, .tab-map-only').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-' + tab + '-only').forEach(el => el.style.display = 'block');
    triggerLoad();
}

// --- SIDEBAR ---
function toggleSidebar() {
    document.getElementById('appSidebar').classList.toggle('open');
    const over = document.querySelector('.sidebar-overlay');
    over.style.display = over.style.display === 'block' ? 'none' : 'block';
}
function toggleChecks(name, check) {
    document.querySelectorAll(`input[name="${name}"]`).forEach(el => el.checked = check);
}

function triggerLoad() {
    if (currentTab === 'import') {
        curPageIns = 1; curPageUpd = 1;
        document.getElementById('page_insert').value = 1;
        document.getElementById('page_update').value = 1;
        loadPreview();
    } else {
        loadMappingPoses();
    }
}

function debounceLoad()   { clearTimeout(timerLoad);   timerLoad   = setTimeout(triggerLoad,       400); }
function debounceLoadRef() { clearTimeout(timerRef);   timerRef    = setTimeout(loadRefPreview,    400); }
function debounceLoadFrames() { clearTimeout(timerFrames); timerFrames = setTimeout(loadRunFrames, 400); }

function showMessage(msg, type = 'success') {
    const box = document.getElementById('msgContainer');
    box.className = `console-msg msg-${type}`;
    box.innerText = msg;
    box.style.display = 'block';
    setTimeout(() => { box.style.display = 'none'; }, 5000);
}

// ==========================================================
// IMPORT TAB LOGIC
// ==========================================================

async function loadPreview() {
    const fd = new FormData(document.getElementById('appForm'));
    fd.append('api_action', 'preview');
    fd.append('exclusions', JSON.stringify(Array.from(excludedCombinations)));

    try {
        const res = await fetch('?', { method: 'POST', body: fd }).then(r => r.json());
        if (res.status !== 'success') return;

        maxPageIns = res.max_p_ins || 1;
        maxPageUpd = res.max_p_upd || 1;

        document.getElementById('statsRow').innerHTML = `
            <div class="stat-item s-blue">🔍 Total: ${res.total}</div>
            <div class="stat-item s-green">✨ New: ${res.new_count}</div>
            <div class="stat-item s-amber">⚠️ Existing: ${res.ext_count}</div>
        `;

        const pgUI = document.getElementById('pgInsertUI');
        if (res.new_count > 100) {
            pgUI.style.display = 'flex';
            document.getElementById('lbl_pg_ins').innerText = `Page ${curPageIns} / ${maxPageIns}`;
        } else {
            pgUI.style.display = 'none';
        }

        const cont = document.getElementById('importContainers');
        if (res.total === 0) {
            cont.innerHTML = `<div class="empty-state"><span>⚡</span><div>No Anima combinations found. Adjust filters.</div></div>`;
            return;
        }

        let html = '';

        if (res.new_rows && res.new_rows.length > 0) {
            html += `
            <div class="table-wrapper">
              <div class="table-header-bar h-success"><div>NEW ENTRIES</div><div>Page ${curPageIns}</div></div>
              <table class="dark-table">
                <thead><tr><th>Character</th><th>Anima Pose</th><th>Shot Info</th><th>Description</th></tr></thead>
                <tbody>`;
            res.new_rows.forEach(r => {
                const key      = `${r.character_id}_${r.pose_id}_${r.angle_id}_${r.perspective_id}`;
                const exCls    = excludedCombinations.has(key) ? 'excluded' : '';
                const shortDesc = r.description.length > 150 ? r.description.substring(0, 150) + '...' : r.description;
                html += `<tr>
                    <td><strong>${escapeHtml(r.character_name)}</strong></td>
                    <td><span class="badge-chip b-pose ${exCls}" onclick="togglePose(this, '${key}')" title="Click to exclude">${escapeHtml(r.pose_name)}</span></td>
                    <td><span class="badge-chip b-persp">${escapeHtml(r.perspective_name)}</span> <span class="badge-chip b-angle">${escapeHtml(r.angle_name)}</span></td>
                    <td class="desc-cell" onclick="openDescModal(this)" data-fulltext="${escapeHtml(r.description)}">${escapeHtml(shortDesc)}</td>
                </tr>`;
            });
            html += `</tbody></table></div>`;
        }

        if (res.ext_rows && res.ext_rows.length > 0) {
            html += `
            <div class="table-wrapper">
              <div class="table-header-bar h-warning"><div>EXISTING ENTRIES</div><div>Page ${curPageUpd}</div></div>
              <table class="dark-table">
                <thead><tr><th>Thumb</th><th>Character</th><th>Anima Pose</th><th>Shot Info</th><th>Status</th></tr></thead>
                <tbody>`;
            res.ext_rows.forEach(r => {
                const key   = `${r.character_id}_${r.pose_id}_${r.angle_id}_${r.perspective_id}`;
                const exCls = excludedCombinations.has(key) ? 'excluded' : '';

                let thumbHtml = `<div style="width:32px;height:32px;background:#111;border-radius:4px;border:1px solid #333;"></div>`;
                if (r.mapped_thumb) {
                    thumbHtml = `<a href="${escapeHtml(r.mapped_thumb)}" class="pswp-link" style="display:block;">
                        <img src="${escapeHtml(r.mapped_thumb)}"
                             onload="this.parentElement.setAttribute('data-pswp-width',this.naturalWidth);this.parentElement.setAttribute('data-pswp-height',this.naturalHeight);"
                             style="width:32px;height:32px;object-fit:cover;border-radius:4px;border:1px solid #444;cursor:zoom-in;">
                    </a>`;
                }
                html += `<tr>
                    <td style="padding:6px 15px;">${thumbHtml}</td>
                    <td><strong>${escapeHtml(r.character_name)}</strong></td>
                    <td><span class="badge-chip b-pose ${exCls}" onclick="togglePose(this,'${key}')" title="Click to exclude">${escapeHtml(r.pose_name)}</span></td>
                    <td><span class="badge-chip b-persp">${escapeHtml(r.perspective_name)}</span> <span class="badge-chip b-angle">${escapeHtml(r.angle_name)}</span></td>
                    <td><span class="badge-chip b-status">Will Update</span></td>
                </tr>`;
            });

            if (res.ext_count > 100) {
                html += `<div style="padding:10px;background:#000;border-top:1px solid var(--border);display:flex;gap:10px;align-items:center;justify-content:center;">
                    <button type="button" class="pg-btn" onclick="changePage('upd',-1)">&#8592;</button>
                    <span class="pg-text">Page ${curPageUpd} / ${maxPageUpd}</span>
                    <button type="button" class="pg-btn" onclick="changePage('upd',1)">&#8594;</button>
                </div>`;
            }
            html += `</tbody></table></div>`;
        }

        cont.innerHTML = html;
        initPhotoswipe();
    } catch (e) { console.error(e); }
}

function changePage(type, diff) {
    if (type === 'ins') {
        let n = curPageIns + diff;
        if (n >= 1 && n <= maxPageIns) { curPageIns = n; document.getElementById('page_insert').value = n; loadPreview(); }
    } else {
        let n = curPageUpd + diff;
        if (n >= 1 && n <= maxPageUpd) { curPageUpd = n; document.getElementById('page_update').value = n; loadPreview(); }
    }
}

function togglePose(el, key) {
    if (excludedCombinations.has(key)) {
        excludedCombinations.delete(key);
        el.classList.remove('excluded');
    } else {
        excludedCombinations.add(key);
        el.classList.add('excluded');
    }
}

async function runImport() {
    const fd = new FormData(document.getElementById('appForm'));
    fd.append('api_action', 'import');
    fd.append('exclusions', JSON.stringify(Array.from(excludedCombinations)));
    try {
        const res = await fetch('?', { method: 'POST', body: fd }).then(r => r.json());
        if (res.status === 'success') {
            showMessage(res.message, 'success');
            loadPreview();
        } else {
            showMessage(res.message, 'error');
        }
    } catch (e) { showMessage('Network Error', 'error'); }
}

async function loadRefPreview() {
    const id   = document.getElementById('img2img_frame_id').value;
    const cont = document.getElementById('refPreviewContainer');
    if (!id) { cont.innerHTML = ''; return; }
    try {
        const res = await fetch(`?api_action=get_frame_url&frame_id=${id}`).then(r => r.json());
        if (res.status === 'success' && res.url) {
            cont.innerHTML = `
            <div style="display:inline-block;width:60%;max-width:150px;border:1px solid var(--border);border-radius:6px;overflow:hidden;background:#000;box-shadow:0 4px 10px rgba(0,0,0,0.3);">
                <a href="${escapeHtml(res.url)}" data-pswp-width="1024" data-pswp-height="1024" class="pswp-link" style="display:block;">
                    <img src="${escapeHtml(res.url)}"
                         onload="this.parentElement.setAttribute('data-pswp-width',this.naturalWidth);this.parentElement.setAttribute('data-pswp-height',this.naturalHeight);"
                         style="width:100%;display:block;aspect-ratio:1/1;object-fit:cover;cursor:zoom-in;" alt="Preview Frame">
                </a>
            </div>`;
            initPhotoswipe();
        } else {
            cont.innerHTML = `<div style="color:var(--amber);font-size:0.7rem;font-weight:bold;">⚠️ Not found.</div>`;
        }
    } catch (e) {}
}

// ==========================================================
// MAPPING TAB LOGIC
// ==========================================================

async function loadMappingPoses() {
    const fd   = new FormData(document.getElementById('appForm'));
    fd.append('api_action', 'get_poses_for_mapping');
    const cont = document.getElementById('mapPosesList');
    cont.innerHTML = `<div class="empty-state" style="padding:20px;"><span>⏳</span><div style="font-size:0.75rem;">Loading...</div></div>`;

    try {
        const res = await fetch('?', { method: 'POST', body: fd }).then(r => r.json());
        if (res.status !== 'success') return;

        if (!res.data || res.data.length === 0) {
            cont.innerHTML = `<div class="empty-state" style="padding:20px;"><span>📋</span><div style="font-size:0.75rem;">No imported Anima poses found. Adjust filters.</div></div>`;
            return;
        }

        let html = '';
        res.data.forEach(p => {
            let img = p.mapped_thumb ? `<img src="${escapeHtml(p.mapped_thumb)}" class="map-thumb" />` : '';
            html += `
            <div class="map-pose-item" id="mp_${p.pose_id}" onclick="selectMapPose(${p.pose_id})">
                <div class="map-pose-info">
                    <strong>${escapeHtml(p.character_name)}</strong>
                    <span style="color:var(--gold);font-weight:bold;">⚡ ${escapeHtml(p.pose_name)}</span><br>
                    ${escapeHtml(p.perspective_name)} | ${escapeHtml(p.angle_name)}
                </div>
                <div class="map-thumb-wrap" id="mp_img_${p.pose_id}">${img}</div>
            </div>`;
        });
        cont.innerHTML = html;
        activeMapPoseId = null;
    } catch (e) {}
}

async function loadRunFrames() {
    const runId = document.getElementById('map_run_id').value;
    const cont  = document.getElementById('mapFramesGrid');
    if (!runId) {
        cont.innerHTML = `<div class="empty-state" style="grid-column:1/-1;"><span>🖼️</span><div>Enter a Map Run ID to load frames.</div></div>`;
        return;
    }
    const fd = new FormData();
    fd.append('api_action', 'get_run_frames');
    fd.append('map_run_id', runId);
    try {
        const res = await fetch('?', { method: 'POST', body: fd }).then(r => r.json());
        if (res.status !== 'success' || !res.data.length) {
            cont.innerHTML = `<div class="empty-state" style="grid-column:1/-1;"><span>❌</span><div>No frames found.</div></div>`;
            return;
        }
        let html = '';
        res.data.forEach(f => {
            html += `<div class="grid-frame" onclick="assignFrame(${f.id})">
                <div class="id-badge">#${f.id}</div>
                <img src="${escapeHtml(f.filename)}" loading="lazy">
            </div>`;
        });
        cont.innerHTML = html;
    } catch (e) {}
}

function selectMapPose(id) {
    document.querySelectorAll('.map-pose-item').forEach(el => el.classList.remove('active'));
    const item = document.getElementById('mp_' + id);
    if (item) { item.classList.add('active'); item.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
    activeMapPoseId = id;
}

async function assignFrame(frameId) {
    if (!activeMapPoseId) { alert("Please click an Anima Pose in the left list first!"); return; }
    const fd = new FormData();
    fd.append('api_action', 'map_frame');
    fd.append('frame_id',   frameId);
    fd.append('pose_id',    activeMapPoseId);
    try {
        const res = await fetch('?', { method: 'POST', body: fd }).then(r => r.json());
        if (res.status === 'success') {
            const imgWrap = document.getElementById('mp_img_' + activeMapPoseId);
            if (res.thumb) {
                imgWrap.innerHTML = `<a href="${escapeHtml(res.thumb)}" class="pswp-link" style="display:block;width:100%;height:100%;">
                    <img src="${escapeHtml(res.thumb)}" class="map-thumb"
                         onload="this.parentElement.setAttribute('data-pswp-width',this.naturalWidth);this.parentElement.setAttribute('data-pswp-height',this.naturalHeight);"
                         style="cursor:zoom-in;" /></a>`;
            } else {
                imgWrap.innerHTML = '';
            }
            initPhotoswipe();
            if (res.mapped) {
                const currentItem = document.getElementById('mp_' + activeMapPoseId);
                const nextItem    = currentItem.nextElementSibling;
                if (nextItem && nextItem.classList.contains('map-pose-item')) {
                    selectMapPose(nextItem.id.replace('mp_', ''));
                }
            }
        }
    } catch (e) {}
}

// --- UTILS ---
function escapeHtml(unsafe) {
    if (!unsafe) return '';
    return unsafe.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;").replace(/'/g,"&#039;");
}
function openDescModal(element) {
    document.getElementById('descModalText').textContent = element.getAttribute('data-fulltext');
    document.getElementById('descModal').classList.add('active');
}
function closeDescModal(e) {
    if (e.target.classList.contains('modal-overlay') || e.target.classList.contains('close-btn')) {
        document.getElementById('descModal').classList.remove('active');
    }
}

document.addEventListener("DOMContentLoaded", () => { triggerLoad(); });
</script>

<?php
$content = ob_get_clean();
$spw->renderLayout($content . $eruda, $pageTitle, $spw->getProjectPath() . '/templates/gallery.php');
?>
