<?php
// public/view_vidbat_review.php
// Map Run based Video Batch Review — mobile-first, Android Chrome
require_once __DIR__ . '/bootstrap.php';

// Import modules for the Rich Video Details Modal
use App\UI\Modules\VideoFrameExtractorModule;
use App\UI\Modules\ImageEditorModule;

$videoExtractor = new VideoFrameExtractorModule();
$imageEditor = new ImageEditorModule();

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();
$pageTitle = "Video Batch Review";

// ═══════════════════════════════════════════════════════
// INLINE API HANDLER
// ═══════════════════════════════════════════════════════
if (isset($_REQUEST['api_action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['api_action'];
    try {

        // ── MAP RUNS: Fetch paginated map runs ──
        if ($action === 'get_map_runs') {
            $limit  = (int)($_GET['limit'] ?? 3);
            $offset = (int)($_GET['offset'] ?? 0);
            $search = $_GET['search'] ?? '';
            
            $whereParts =["m.entity_type = 'animatics'"];
            $params =[];

            if ($search) {
                $whereParts[] = "(m.note LIKE ? OR m.id = ?)";
                $params[] = "%$search%";
                $params[] = intval($search);
            }
            
            $whereSQL = implode(' AND ', $whereParts);

            $countSql = "SELECT COUNT(DISTINCT m.id) 
                         FROM map_runs m
                         INNER JOIN videos v ON m.id = v.map_run_id 
                         WHERE $whereSQL";
            $stmtCount = $pdo->prepare($countSql);
            $stmtCount->execute($params);
            $total = $stmtCount->fetchColumn();

            $sql = "SELECT m.*, COUNT(v.id) as item_count 
                    FROM map_runs m
                    INNER JOIN videos v ON m.id = v.map_run_id
                    WHERE $whereSQL
                    GROUP BY m.id
                    ORDER BY m.id DESC 
                    LIMIT $limit OFFSET $offset";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['status'=>'ok', 'data'=>$rows, 'total'=>$total]);
            exit;
        }

        // ── VIDEOS: Fetch all for a specific map run ──
        if ($action === 'get_videos') {
            $runId = (int)$_GET['map_run_id'];
            
            $sql = "SELECT v.id, v.name, v.thumbnail, v.url, v.duration, v.file_size, v.description, v.category_id, v.is_active, v.`review`,
                           va.to_id as animatic_id, c.name as category_name
                    FROM videos v 
                    LEFT JOIN videos_2_animatics va ON v.id = va.from_id
                    LEFT JOIN video_categories c ON v.category_id = c.id
                    WHERE v.map_run_id = $runId 
                    ORDER BY v.id ASC";
            
            $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status'=>'ok', 'videos'=>$rows]);
            exit;
        }

        // ── STORYBOARDS: Fetch paginated list ──
        if ($action === 'get_storyboards') {
            $limit  = (int)($_GET['limit'] ?? 20);
            $offset = (int)($_GET['offset'] ?? 0);
            $search = $_GET['search'] ?? '';

            $whereParts = ['is_archived = 0'];
            $params     = [];

            if ($search) {
                $whereParts[] = '(name LIKE ? OR id = ?)';
                $params[]     = "%$search%";
                $params[]     = intval($search);
            }

            $whereSQL = implode(' AND ', $whereParts);

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM storyboards WHERE $whereSQL");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT s.*,
                        (SELECT COUNT(*) FROM storyboard_frames sf WHERE sf.storyboard_id = s.id) as frame_count
                 FROM storyboards s
                 WHERE $whereSQL
                 ORDER BY s.id DESC
                 LIMIT $limit OFFSET $offset"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'ok', 'data' => $rows, 'total' => $total]);
            exit;
        }

        // ── VIDEOS: Resolve all videos for a storyboard via entity chain ──
        if ($action === 'get_videos_for_storyboard') {
            $sbId = (int)$_GET['storyboard_id'];
            if (!$sbId) throw new Exception('Missing storyboard_id');

            $sfStmt = $pdo->prepare(
                "SELECT sf.frame_id, f.entity_type, f.entity_id
                 FROM storyboard_frames sf
                 JOIN frames f ON f.id = sf.frame_id
                 WHERE sf.storyboard_id = ?
                   AND f.entity_type IS NOT NULL
                   AND f.entity_id IS NOT NULL"
            );
            $sfStmt->execute([$sbId]);
            $sbFrames = $sfStmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($sbFrames)) {
                echo json_encode(['status' => 'ok', 'videos' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1]);
                exit;
            }

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

                $directStmt = $pdo->prepare("SELECT id FROM frames WHERE entity_type = ? AND entity_id = ?");
                $directStmt->execute([$eType, $eId]);
                foreach ($directStmt->fetchAll(PDO::FETCH_COLUMN) as $fid) {
                    $allFrameIds[] = (int)$fid;
                }

                $mapTable = "frames_2_{$eType}";
                $checkMap = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($mapTable));
                if ($checkMap && $checkMap->rowCount() > 0) {
                    $mapStmt = $pdo->prepare("SELECT from_id FROM `$mapTable` WHERE to_id = ?");
                    $mapStmt->execute([$eId]);
                    foreach ($mapStmt->fetchAll(PDO::FETCH_COLUMN) as $fid) {
                        $allFrameIds[] = (int)$fid;
                    }
                }
            }

            $allFrameIds = array_unique($allFrameIds);

            if (empty($allFrameIds)) {
                echo json_encode(['status' => 'ok', 'videos' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1]);
                exit;
            }

            $inClause = implode(',', $allFrameIds);
            $animaticStmt = $pdo->query("SELECT id FROM animatics WHERE img2img_frame_id IN ($inClause)");
            $animaticIds = $animaticStmt->fetchAll(PDO::FETCH_COLUMN);

            if (empty($animaticIds)) {
                echo json_encode(['status' => 'ok', 'videos' => [], 'total' => 0, 'page' => 1, 'total_pages' => 1]);
                exit;
            }

            $anInClause = implode(',', array_map('intval', $animaticIds));
            $videoStmt = $pdo->query(
                "SELECT v.id, v.name, v.thumbnail, v.url, v.duration, v.file_size,
                        v.description, v.category_id, v.is_active, v.`review`,
                        va.to_id as animatic_id, c.name as category_name
                 FROM videos v
                 JOIN videos_2_animatics va ON v.id = va.from_id
                 LEFT JOIN video_categories c ON v.category_id = c.id
                 WHERE va.to_id IN ($anInClause)
                 ORDER BY v.id ASC"
            );
            $videos = $videoStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode(['status' => 'ok', 'videos' => $videos, 'total' => count($videos), 'page' => 1, 'total_pages' => 1]);
            exit;
        }

        // ── FILTER VIDEOS: Paginated fetching for Filter Mode ──
        if ($action === 'list_videos') {
            $page        = max(1, (int)($_GET['page']  ?? 1));
            $limit       = max(1, min(200, (int)($_GET['limit'] ?? 24)));
            $offset      = ($page - 1) * $limit;
            $onlyReview  = (int)($_GET['only_review'] ?? 0);
            $nodeId      = (int)($_GET['node_id'] ?? 0);
            $inclDesc    = (int)($_GET['include_descendants'] ?? 1);
            $seqId         = (int)($_GET['seq_id'] ?? 0);
            $fuzzCandId    = (int)($_GET['fuzz_cand_id'] ?? 0);
            $storyboardId  = (int)($_GET['storyboard_id'] ?? 0);

            $whereParts =[];
            $params     =[];

            if ($onlyReview) {
                $whereParts[] = 'v.`review` = 1';
            }

            if ($fuzzCandId) {
                // Fuzz candidate → sketch IDs via fuzz_mentions → frames → animatics → videos
                $whereParts[] = 'v.id IN (
                    SELECT DISTINCT va2.from_id
                    FROM videos_2_animatics va2
                    JOIN animatics an ON va2.to_id = an.id
                    JOIN frames fr ON an.img2img_frame_id = fr.id
                    WHERE (
                        (fr.entity_type = \'sketches\' AND fr.entity_id IN (
                            SELECT DISTINCT source_row_id
                            FROM fuzz_mentions
                            WHERE candidate_id = ?
                              AND source_table IN (\'sketches\',\'sketch_analysis\',\'sketch_lore_history\',\'sketch_ingredients\')
                              AND source_row_id IS NOT NULL
                        ))
                        OR fr.id IN (
                            SELECT f2s.from_id FROM frames_2_sketches f2s
                            WHERE f2s.to_id IN (
                                SELECT DISTINCT source_row_id
                                FROM fuzz_mentions
                                WHERE candidate_id = ?
                                  AND source_table IN (\'sketches\',\'sketch_analysis\',\'sketch_lore_history\',\'sketch_ingredients\')
                                  AND source_row_id IS NOT NULL
                            )
                        )
                    )
                )';
                $params[] = $fuzzCandId;
                $params[] = $fuzzCandId;

            } elseif ($seqId) {
                // Narrative sequence → sketches → frames → animatics → videos
                $whereParts[] = 'v.id IN (
                    SELECT DISTINCT va2.from_id
                    FROM videos_2_animatics va2
                    JOIN animatics an ON va2.to_id = an.id
                    JOIN frames fr ON an.img2img_frame_id = fr.id
                    WHERE (
                        (fr.entity_type = \'sketches\' AND fr.entity_id IN (
                            SELECT CASE WHEN JSON_TYPE(jt.val) = \'INTEGER\'
                                        THEN JSON_VALUE(jt.val, \'$\')
                                        ELSE JSON_VALUE(jt.val, \'$.sketch_id\')
                                   END
                            FROM narrative_sequences ns,
                            JSON_TABLE(ns.sequence_data, \'$[*]\' COLUMNS(val JSON PATH \'$\')) jt
                            WHERE ns.id = ?
                        ))
                        OR fr.id IN (
                            SELECT f2s.from_id FROM frames_2_sketches f2s
                            WHERE f2s.to_id IN (
                                SELECT CASE WHEN JSON_TYPE(jt2.val) = \'INTEGER\'
                                            THEN JSON_VALUE(jt2.val, \'$\')
                                            ELSE JSON_VALUE(jt2.val, \'$.sketch_id\')
                                       END
                                FROM narrative_sequences ns2,
                                JSON_TABLE(ns2.sequence_data, \'$[*]\' COLUMNS(val JSON PATH \'$\')) jt2
                                WHERE ns2.id = ?
                            )
                        )
                    )
                )';
                $params[] = $seqId;
                $params[] = $seqId;

            } elseif ($storyboardId) {
                // Storyboard → entity chain → frames → animatics → videos
                $whereParts[] = 'v.id IN (
                    SELECT DISTINCT va2.from_id
                    FROM videos_2_animatics va2
                    JOIN animatics an ON va2.to_id = an.id
                    WHERE an.img2img_frame_id IN (
                        SELECT DISTINCT f.id FROM frames f
                        WHERE f.entity_type IS NOT NULL AND f.entity_id IS NOT NULL AND (
                            EXISTS (
                                SELECT 1 FROM storyboard_frames sf
                                JOIN frames sf2 ON sf2.id = sf.frame_id
                                WHERE sf.storyboard_id = ?
                                  AND sf2.entity_type = f.entity_type
                                  AND sf2.entity_id   = f.entity_id
                            )
                        )
                    )
                )';
                $params[] = $storyboardId;

            } elseif ($nodeId) {
                if ($inclDesc) {
                    $whereParts[] = 'v.id IN (
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
                    )';
                    $params[] = $nodeId;
                } else {
                    $whereParts[] = 'v.id IN (SELECT vti.video_id FROM video_tree_items vti WHERE vti.node_id = ?)';
                    $params[] = $nodeId;
                }
            }

            $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM videos v $where");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            $dataStmt = $pdo->prepare(
                "SELECT v.id, v.name, v.thumbnail, v.url, v.duration, v.file_size, v.description, v.category_id, v.is_active, v.`review`,
                        va.to_id as animatic_id, c.name as category_name
                 FROM videos v
                 LEFT JOIN videos_2_animatics va ON va.from_id = v.id
                 LEFT JOIN video_categories c ON v.category_id = c.id
                 $where
                 ORDER BY v.id DESC
                 LIMIT $limit OFFSET $offset"
            );
            $dataStmt->execute($params);
            $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status'      => 'ok',
                'videos'      => $rows,
                'total'       => (int)$total,
                'page'        => $page,
                'total_pages' => (int)ceil($total / $limit),
            ]);
            exit;
        }

        // ── Narrative sequences list ──
        if ($action === 'list_narrative_sequences') {
            $search = trim($_GET['search'] ?? '');
            $page   = max(1, (int)($_GET['page'] ?? 1));
            $limit  = 20;
            $offset = ($page - 1) * $limit;
            
            $whereSQL = "";
            $params =[];
            if ($search !== '') {
                $whereSQL = "WHERE name LIKE ? OR description LIKE ?";
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
            }

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM narrative_sequences $whereSQL");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT id, name, description FROM narrative_sequences $whereSQL ORDER BY id DESC LIMIT $limit OFFSET $offset");
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'ok',
                'sequences' => $rows,
                'total_pages' => ceil($total / $limit),
                'page' => $page
            ]);
            exit;
        }

        // ── Fuzz candidates list ──
        if ($action === 'list_fuzz_candidates') {
            $search = trim($_GET['search'] ?? '');
            $page   = max(1, (int)($_GET['page'] ?? 1));
            $limit  = 20;
            $offset = ($page - 1) * $limit;
            
            $params =[];
            $whereSQL = "WHERE status NOT IN ('rejected','promoted','canonized')";
            if ($search !== '') {
                $whereSQL .= " AND label LIKE ?";
                $params[] = '%' . $search . '%';
            }
            
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM fuzz_candidates c $whereSQL");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            $stmt = $pdo->prepare(
                "SELECT c.id, c.label, c.concept_type, c.status,
                        (SELECT COUNT(*) FROM fuzz_mentions m WHERE m.candidate_id = c.id) as mention_count
                 FROM fuzz_candidates c
                 $whereSQL
                 ORDER BY mention_count DESC, c.updated_at DESC
                 LIMIT $limit OFFSET $offset"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'status' => 'ok',
                'candidates' => $rows,
                'total_pages' => ceil($total / $limit),
                'page' => $page
            ]);
            exit;
        }

        // ── BATCH FLAG ──
        if ($action === 'toggle_review_batch') {
            $input = json_decode(file_get_contents('php://input'), true);
            $ids   = $input['ids'] ??[];
            $val   = (int)($input['value'] ?? 0);
            if (empty($ids)) throw new Exception('Missing ids');
            
            $in = str_repeat('?,', count($ids) - 1) . '?';
            $params = array_merge([$val], $ids);
            $stmt = $pdo->prepare("UPDATE videos SET `review` = ? WHERE id IN ($in)");
            $stmt->execute($params);
            echo json_encode(['status' => 'ok', 'ids' => $ids, 'review' => $val]);
            exit;
        }

        // ── BATCH ASSIGN ──
        if ($action === 'tree_assign_batch') {
            $input = json_decode(file_get_contents('php://input'), true);
            $nodeId = (int)($input['node_id'] ?? 0);
            $videoIds = $input['video_ids'] ??[];
            if (!$nodeId || empty($videoIds)) throw new Exception('Missing node_id or video_ids');
            
            $stmt = $pdo->prepare("INSERT IGNORE INTO video_tree_items (node_id, video_id) VALUES (?, ?)");
            foreach ($videoIds as $vid) {
                $stmt->execute([$nodeId, $vid]);
            }
            echo json_encode(['status' => 'ok', 'node_id' => $nodeId, 'video_ids' => $videoIds]);
            exit;
        }

        // ── BATCH UNASSIGN ──
        if ($action === 'tree_unassign_batch') {
            $input = json_decode(file_get_contents('php://input'), true);
            $videoIds = $input['video_ids'] ??[];
            if (empty($videoIds)) throw new Exception('Missing video_ids');
            
            $in = str_repeat('?,', count($videoIds) - 1) . '?';
            $stmt = $pdo->prepare("DELETE FROM video_tree_items WHERE video_id IN ($in)");
            $stmt->execute($videoIds);
            echo json_encode(['status' => 'ok']);
            exit;
        }

        // ── Video Tree: fetch full tree for jsTree ──
        if ($action === 'tree_fetch') {
            $rows = $pdo->query(
                "SELECT id, parent_id, name, node_type FROM video_tree_nodes ORDER BY sort_order ASC, name ASC"
            )->fetchAll(PDO::FETCH_ASSOC);

            $nodes =[];
            foreach ($rows as $r) {
                $icon = match($r['node_type']) {
                    'episode'  => 'bi bi-film',
                    'sequence' => 'bi bi-collection-play',
                    'scene'    => 'bi bi-camera-video',
                    'other'    => 'bi bi-tag',
                    default    => 'bi bi-folder2',
                };
                $nodes[] =[
                    'id'     => 'n_' . $r['id'],
                    'parent' => $r['parent_id'] ? 'n_' . $r['parent_id'] : '#',
                    'text'   => $r['name'],
                    'icon'   => $icon,
                    'type'   => $r['node_type'],
                    'data'   =>['db_id' => (int)$r['id'], 'node_type' => $r['node_type']],
                ];
            }
            echo json_encode(['status' => 'ok', 'tree' => $nodes]);
            exit;
        }

        // ── Video Tree: create node ──
        if ($action === 'tree_create_node') {
            $input     = json_decode(file_get_contents('php://input'), true);
            $name      = trim($input['name'] ?? '');
            $parentId  = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;
            $nodeType  = in_array($input['node_type'] ?? '',['folder','episode','sequence','scene','other'])
                         ? $input['node_type'] : 'folder';
            if (!$name) throw new Exception('Name required');
            $stmt = $pdo->prepare(
                "INSERT INTO video_tree_nodes (parent_id, name, node_type) VALUES (?, ?, ?)"
            );
            $stmt->execute([$parentId, $name, $nodeType]);
            echo json_encode(['status' => 'ok', 'id' => (int)$pdo->lastInsertId(), 'name' => $name]);
            exit;
        }

        // ── Video Tree: get assignment for a video ──
        if ($action === 'tree_get_assignment') {
            $videoId = (int)($_GET['video_id'] ?? 0);
            if (!$videoId) throw new Exception('Missing video_id');
            $stmt = $pdo->prepare(
                "SELECT vti.node_id, vtn.name as node_name, vtn.node_type
                 FROM video_tree_items vti
                 JOIN video_tree_nodes vtn ON vtn.id = vti.node_id
                 WHERE vti.video_id = ?"
            );
            $stmt->execute([$videoId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'ok', 'assignment' => $row ?: null]);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
    exit;
}

ob_start();
?>
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/themes/default/style.min.css" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<script src="/js/toast.js"></script>

<style>
/* ── Reset ── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ── Variables ── */
:root {
    --bg:        #07070d;
    --card:      #0f0f1a;
    --border:    #1a1a2e;
    --text:      #d4d4e8;
    --text-muted:#4a4a6a;
    --muted:     #4a4a6a;
    --muted-border-rgb: 26, 26, 46;
    --accent:    #6c63ff;
    --green:     #00e5a0;
    --green-dim: rgba(0,229,160,0.13);
    --danger:    #ff6584;
    --tap:       48px;
}

html, body {
    background: var(--bg);
    color: var(--text);
    font-family: 'DM Mono', 'Fira Mono', monospace;
    height: 100dvh;
    overflow: hidden;
}

/* ════════════════════════════════════
   LAYOUT WRAPPER
════════════════════════════════════ */
.rv-layout {
    display: flex;
    flex-direction: column;
    height: 100dvh;
    overflow: hidden;
}

.rv-left-col {
    flex-shrink: 0;
    display: flex;
    flex-direction: column;
}

.rv-right-col {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    min-height: 0;
}

@media (min-width: 900px) {
    .rv-layout {
        flex-direction: row;
        align-items: stretch;
    }
    .rv-left-col {
        width: 400px;
        max-height: 100dvh;
        overflow-y: auto;
        border-right: 1px solid var(--border);
    }
}

/* ════════════════════════════════════
   PLAYER (Height reduced by precisely 25%)
════════════════════════════════════ */
.rv-player-wrap {
    position: relative;
    background: #000;
    width: 100%;
    flex-shrink: 0;
}
.rv-player-wrap video {
    width: 100%;
    aspect-ratio: 16/9;
    display: block;
    background: #000;
}
.rv-player-placeholder {
    aspect-ratio: 16/9;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #000;
    color: var(--muted);
    font-size: 0.7rem;
    letter-spacing: 2px;
    text-transform: uppercase;
}

.rv-flash {
    position: absolute;
    inset: 0;
    pointer-events: none;
    opacity: 0;
    border: 4px solid transparent;
    transition: opacity 0.05s;
}
.rv-flash.on  { opacity: 1; border-color: var(--green); background: rgba(0,229,160,0.12); }
.rv-flash.off { opacity: 0; transition: opacity 0.5s ease; }

/* ════════════════════════════════════
   INFO BAR
════════════════════════════════════ */
.rv-info {
    background: var(--card);
    border-bottom: 1px solid var(--border);
    padding: 8px 12px 6px;
    flex-shrink: 0;
}
.rv-video-name { font-size: 0.78rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 3px; }
.rv-meta-row { display: flex; gap: 10px; font-size: 0.65rem; color: var(--muted); flex-wrap: wrap; }
.rv-flagged-badge { color: var(--green); font-weight: 700; }
.rv-assigned-badge { color: var(--accent); font-weight: 700; font-size: 0.65rem; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.rv-progress-track { height: 4px; background: var(--border); cursor: pointer; margin-top: 7px; border-radius: 2px; overflow: hidden; }
.rv-progress-fill { height: 100%; background: var(--accent); width: 0%; pointer-events: none; transition: width 0.15s linear; }

/* ════════════════════════════════════
   ACTION BUTTONS
════════════════════════════════════ */
.rv-actions {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr 0.8fr 0.8fr 0.8fr 1.2fr;
    gap: 4px;
    padding: 6px 8px;
    background: var(--card);
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.rv-btn {
    min-height: var(--tap); border-radius: 4px; border: 1px solid var(--border); background: transparent; color: var(--muted); font-family: inherit; font-size: 0.7rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 4px; transition: background 0.1s, border-color 0.1s, color 0.1s; -webkit-tap-highlight-color: transparent; user-select: none;
}
.rv-btn:disabled       { opacity: 0.3; pointer-events: none; }
.rv-btn:active         { transform: scale(0.96); }

.rv-btn-nav:active     { border-color: var(--accent); color: var(--accent); }
.rv-btn-review         { border-color: var(--green); color: var(--green); font-size: 0.75rem; }
.rv-btn-review:active,
.rv-btn-review:hover   { background: var(--green-dim); }
.rv-btn-review.flagged { background: var(--green-dim); }
.rv-btn-assign         { border-color: var(--accent); color: var(--accent); font-size: 0.75rem; }
.rv-btn-assign:active,
.rv-btn-assign:hover   { background: rgba(108,99,255,0.12); }
.rv-btn-assign.assigned { background: rgba(108,99,255,0.15); }
.rv-btn-animatic              { border-color: #ffd166; color: #ffd166; font-size: 1rem; }
.rv-btn-animatic:active,
.rv-btn-animatic:hover        { background: rgba(255,209,102,0.12); }
.rv-btn-animatic:disabled     { opacity: 0.2; pointer-events: none; }
.rv-btn-filter         { font-size: 1rem; }
.rv-btn-filter.active-filter  { border-color: var(--accent); color: var(--accent); background: rgba(108,99,255,0.12); }

/* ════════════════════════════════════
   MAP RUN SELECTOR
════════════════════════════════════ */
.mr-top-panel { flex-shrink: 0; display: flex; flex-direction: column; background: var(--card); border-bottom: 1px solid var(--border); }
.mr-controls-row { display: flex; gap: 8px; padding: 8px 12px; border-bottom: 1px solid var(--border); align-items: center; background: rgba(0,0,0,0.2); }
.mr-search-input { flex: 1; min-width: 0; padding: 6px 10px; border-radius: 4px; border: 1px solid var(--border); background: var(--bg); color: var(--text); font-family: inherit; font-size: 0.8rem; }
.mr-search-input:focus { outline: none; border-color: var(--accent); }

.mr-pagination { display: flex; align-items: center; gap: 4px; flex-shrink: 0; }
.pg-btn { width: 26px; height: 26px; background: transparent; border: 1px solid var(--border); color: var(--muted); border-radius: 3px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; }
.pg-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
.pg-input { width: 40px; text-align: center; background: var(--bg); border: 1px solid var(--border); color: var(--accent); border-radius: 3px; font-family: inherit; font-size: 0.75rem; font-weight: 700; padding: 4px 0; -moz-appearance: textfield; }
.pg-input::-webkit-outer-spin-button,
.pg-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.pg-total { font-size: 0.7rem; color: var(--muted); padding: 0 4px; }

/* Fixed to hold precisely 3 items of 42px each */
.mr-list-scroll { display: flex; flex-direction: column; height: 126px; overflow-y: hidden; }
.mr-item { padding: 8px 12px; border-bottom: 1px solid var(--border); cursor: pointer; transition: background 0.15s; display: flex; align-items: center; gap: 10px; height: 42px; flex-shrink: 0; }
.mr-item:hover { background: rgba(255,255,255,0.03); }
.mr-item.active { background: rgba(108,99,255,0.1); border-left: 3px solid var(--accent); padding-left: 9px; }
.mr-id { font-size: 0.7rem; font-weight: 700; color: var(--accent); min-width: 40px; }
.mr-note { font-size: 0.75rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
.mr-meta { font-size: 0.65rem; color: var(--muted); white-space: nowrap; }

/* ════════════════════════════════════
   BATCH TOOLBAR
════════════════════════════════════ */
.rv-pg-bar {
    position: sticky; top: 0; z-index: 50; background: var(--card); border-bottom: 1px solid var(--border);
    display: flex; align-items: center; gap: 6px; padding: 6px 10px; flex-wrap: wrap;
    flex-shrink: 0; min-height: 44px;
}
.rv-pg-btn {
    min-width: 50px; min-height: 30px; background: transparent; border: 1px solid var(--border);
    color: var(--muted); border-radius: 4px; cursor: pointer; font-size: 0.75rem;
    display: flex; align-items: center; justify-content: center; font-weight: bold;
    -webkit-tap-highlight-color: transparent; transition: border-color 0.1s, color 0.1s;
}
.rv-pg-btn:active, .rv-pg-btn:hover:not(:disabled) { border-color: var(--accent); color: var(--accent); }
.rv-pg-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.rv-pg-count { font-size: 0.7rem; color: var(--accent); margin: 0 4px; font-weight: bold; white-space: nowrap; }

.rv-auto-label { display: flex; align-items: center; gap: 5px; font-size: 0.6rem; color: var(--muted); cursor: pointer; white-space: nowrap; -webkit-tap-highlight-color: transparent; }
.rv-auto-label input { accent-color: var(--accent); width: 15px; height: 15px; }

/* ════════════════════════════════════
   VIDEO GRID
════════════════════════════════════ */
.rv-grid-section { 
    padding: 6px; 
    padding-bottom: calc(12px + env(safe-area-inset-bottom)); 
    flex: 1;
    overflow-y: auto;
    min-height: 0;
}
.rv-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 4px; }
@media (min-width: 500px) { .rv-grid { grid-template-columns: repeat(3, 1fr); } }

.rv-card {
    position: relative; aspect-ratio: 16/9; background: #111; border-radius: 3px; overflow: hidden;
    cursor: pointer; border: 2px solid transparent; -webkit-tap-highlight-color: transparent; transition: border-color 0.1s;
}
.rv-card.active  { border-color: #fff; z-index: 2; }
.rv-card.flagged { border-color: var(--green); }
.rv-card.flagged::after {
    content: '★'; position: absolute; top: 2px; right: 3px; font-size: 9px;
    color: var(--green); text-shadow: 0 0 5px rgba(0,229,160,0.9); pointer-events: none; z-index: 3;
}
.rv-card.selected { border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent) inset; }
.rv-card.selected::before {
    content: '✓'; position: absolute; top: 2px; left: 3px; background: var(--accent);
    color: #fff; font-size: 10px; font-weight: bold; padding: 1px 4px; border-radius: 2px; z-index: 5;
}
.rv-card img { width: 100%; height: 100%; object-fit: cover; display: block; }
.rv-card-id { position: absolute; bottom: 2px; left: 3px; font-size: 0.5rem; color: rgba(255,255,255,0.45); pointer-events: none; }

/* ════════════════════════════════════
   STATE MESSAGES
════════════════════════════════════ */
.rv-state { padding: 40px 20px; text-align: center; color: var(--muted); font-size: 0.75rem; display: flex; flex-direction: column; align-items: center; gap: 10px; }
.rv-spinner { width: 20px; height: 20px; border: 2px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.7s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ════════════════════════════════════
   MODALS (Assign & Filter)
════════════════════════════════════ */
.rv-modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.75); z-index: 200; display: none; align-items: flex-end; justify-content: center; padding: 0; }
.rv-modal-overlay.active { display: flex; }
@media (min-width: 600px) { .rv-modal-overlay { align-items: center; padding: 20px; } }

.rv-modal-sheet { width: 100%; max-width: 480px; background: var(--card); border: 1px solid var(--border); border-radius: 12px 12px 0 0; display: flex; flex-direction: column; max-height: 85dvh; overflow: hidden; }
@media (min-width: 600px) { .rv-modal-sheet { border-radius: 10px; max-height: 80dvh; } }

.rv-modal-header { display: flex; align-items: center; justify-content: space-between; padding: 12px 14px 10px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.rv-modal-title { font-size: 0.85rem; font-weight: 700; color: var(--text); letter-spacing: 1px; text-transform: uppercase; }
.rv-modal-close { width: 32px; height: 32px; background: transparent; border: 1px solid var(--border); color: var(--muted); border-radius: 4px; cursor: pointer; font-size: 1rem; display: flex; align-items: center; justify-content: center; -webkit-tap-highlight-color: transparent; }
.rv-modal-close:active { color: var(--danger); border-color: var(--danger); }

.rv-assign-current { padding: 8px 14px; font-size: 0.7rem; color: var(--muted); border-bottom: 1px solid var(--border); flex-shrink: 0; display: flex; align-items: center; justify-content: space-between; gap: 8px; min-height: 36px; }
.rv-assign-current .node-name { color: var(--green); font-weight: 700; }
.rv-unassign-btn { padding: 3px 8px; border: 1px solid var(--danger); background: transparent; color: var(--danger); border-radius: 3px; font-size: 0.6rem; font-family: inherit; cursor: pointer; -webkit-tap-highlight-color: transparent; white-space: nowrap; }

.rv-tree-toolbar { padding: 6px 10px; border-bottom: 1px solid var(--border); display: flex; gap: 6px; flex-shrink: 0; flex-wrap: wrap; }
.rv-tree-toolbar input { flex: 1; min-width: 100px; padding: 5px 8px; background: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 4px; font-family: inherit; font-size: 0.75rem; }
.rv-tree-toolbar input:focus { outline: none; border-color: var(--accent); }
.rv-tree-toolbar select { padding: 5px 6px; background: var(--bg); border: 1px solid var(--border); color: var(--text); border-radius: 4px; font-family: inherit; font-size: 0.7rem; }
.rv-tree-add-btn { padding: 5px 12px; background: var(--accent); border: none; color: #fff; border-radius: 4px; font-family: inherit; font-size: 0.7rem; font-weight: 700; cursor: pointer; white-space: nowrap; -webkit-tap-highlight-color: transparent; }

.rv-tree-scroll { flex: 1; overflow-y: auto; padding: 8px 6px; background: var(--bg); min-height:100px; }
.rv-tree-scroll::-webkit-scrollbar { width: 3px; }
.rv-tree-scroll::-webkit-scrollbar-thumb { background: var(--border); }

.jstree-default .jstree-anchor { color: var(--text) !important; line-height: 28px; height: 28px; }
.jstree-default .jstree-hovered { background: rgba(108,99,255,0.12) !important; border-radius: 4px; }
.jstree-default .jstree-clicked { background: rgba(108,99,255,0.25) !important; color: var(--accent) !important; border-radius: 4px; }
.jstree-default .jstree-icon { color: var(--muted); }
.jstree-default { background: transparent !important; color: var(--text); }
.jstree-container-ul { background: transparent !important; }

.rv-modal-footer { padding: 10px 14px; border-top: 1px solid var(--border); flex-shrink: 0; display: flex; gap: 8px; }
.rv-assign-confirm-btn { flex: 1; min-height: var(--tap); background: var(--green); border: none; color: #000; font-family: inherit; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; border-radius: 4px; cursor: pointer; -webkit-tap-highlight-color: transparent; transition: opacity 0.1s; }
.rv-assign-confirm-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.rv-assign-confirm-btn:active   { opacity: 0.8; }

/* ════════════════════════════════════
   FILTER MODAL — TAB BAR & PAGINATION
════════════════════════════════════ */
.rv-filter-tabs {
    display: flex;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
    background: var(--card);
}
.rv-filter-tab {
    flex: 1;
    padding: 8px 4px;
    text-align: center;
    font-size: 0.62rem;
    font-weight: 700;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: var(--muted);
    cursor: pointer;
    border-bottom: 2px solid transparent;
    transition: color 0.15s, border-color 0.15s;
    -webkit-tap-highlight-color: transparent;
    user-select: none;
}
.rv-filter-tab.active {
    color: var(--accent);
    border-bottom-color: var(--accent);
}
.rv-filter-tab-panel {
    display: none;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}
.rv-filter-tab-panel.active {
    display: flex;
}

/* Filter pagination */
.filter-pg {
    flex-shrink: 0;
    border-top: 1px solid var(--border);
    padding: 6px 10px;
    display: flex;
    align-items: center;
    gap: 4px;
    background: var(--bg);
}
.filter-pg-btn {
    width: 28px; height: 28px;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 3px;
    color: var(--muted);
    font-size: 0.9rem;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0;
    -webkit-tap-highlight-color: transparent;
}
.filter-pg-btn:disabled { opacity: 0.3; pointer-events: none; }
.filter-pg-input {
    width: 36px; text-align: center;
    background: rgba(255,255,255,0.05);
    border: 1px solid var(--border);
    border-radius: 3px;
    color: var(--accent);
    font-family: inherit; font-size: 0.75rem; font-weight: 700;
    padding: 3px 2px; height: 28px;
    -moz-appearance: textfield;
}
.filter-pg-input::-webkit-outer-spin-button, .filter-pg-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
.filter-pg-input:focus { outline: none; border-color: var(--accent); }
.filter-pg-of { font-size: 0.7rem; color: var(--muted); white-space: nowrap; flex: 1; text-align: center; }


/* ── Narrative sequence list ── */
.rv-seq-list {
    flex: 1;
    overflow-y: auto;
    padding: 6px;
    background: var(--bg);
}
.rv-seq-list::-webkit-scrollbar { width: 3px; }
.rv-seq-list::-webkit-scrollbar-thumb { background: var(--border); }
.rv-seq-item {
    padding: 9px 10px;
    border-radius: 4px;
    border: 1px solid var(--border);
    cursor: pointer;
    margin-bottom: 4px;
    transition: border-color 0.1s, background 0.1s;
    -webkit-tap-highlight-color: transparent;
}
.rv-seq-item:active { background: rgba(108,99,255,0.08); }
.rv-seq-item.selected {
    border-color: var(--accent);
    background: rgba(108,99,255,0.12);
}
.rv-seq-item-name {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 2px;
}
.rv-seq-item-desc {
    font-size: 0.6rem;
    color: var(--muted);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Fuzz candidate list ── */
.rv-fuzz-search {
    padding: 6px 10px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.rv-fuzz-search input {
    width: 100%;
    padding: 6px 8px;
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: 4px;
    font-family: inherit;
    font-size: 0.75rem;
}
.rv-fuzz-search input:focus { outline: none; border-color: var(--accent); }
.rv-fuzz-list {
    flex: 1;
    overflow-y: auto;
    padding: 6px;
    background: var(--bg);
}
.rv-fuzz-list::-webkit-scrollbar { width: 3px; }
.rv-fuzz-list::-webkit-scrollbar-thumb { background: var(--border); }
.rv-fuzz-item {
    padding: 8px 10px;
    border-radius: 4px;
    border: 1px solid var(--border);
    cursor: pointer;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    transition: border-color 0.1s, background 0.1s;
    -webkit-tap-highlight-color: transparent;
}
.rv-fuzz-item:active { background: rgba(108,99,255,0.08); }
.rv-fuzz-item.selected {
    border-color: var(--accent);
    background: rgba(108,99,255,0.12);
}
.rv-fuzz-item-label {
    font-size: 0.72rem;
    font-weight: 700;
    color: var(--text);
    flex: 1;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.rv-fuzz-item-meta {
    font-size: 0.58rem;
    color: var(--muted);
    white-space: nowrap;
    flex-shrink: 0;
}

/* ════════════════════════════════════
   REMBG MODAL STYLES
════════════════════════════════════ */
.rembg-color-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.rembg-swatch {
    width: 36px;
    height: 36px;
    border-radius: 4px;
    border: 2px solid rgba(255,255,255,0.15);
    flex-shrink: 0;
    cursor: pointer;
    transition: border-color 0.15s;
}
.rembg-swatch:active { border-color: var(--accent); }
.rembg-hex-input {
    flex: 1;
    padding: 8px 10px;
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: 4px;
    font-family: inherit;
    font-size: 0.9rem;
    letter-spacing: 1px;
}
.rembg-hex-input:focus { outline: none; border-color: var(--accent); }
.rembg-pick-btn {
    padding: 8px 12px;
    background: transparent;
    border: 1px solid var(--accent);
    color: var(--accent);
    border-radius: 4px;
    font-family: inherit;
    font-size: 0.7rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    -webkit-tap-highlight-color: transparent;
}
.rembg-pick-btn:active { background: rgba(108,99,255,0.15); }
.rembg-info-row {
    padding: 8px 14px;
    font-size: 0.7rem;
    color: var(--muted);
    flex-shrink: 0;
}

/* ════════════════════════════════════
   COLOR SAMPLER MODAL STYLES
════════════════════════════════════ */
.sampler-canvas-wrap {
    flex: 1;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #000;
    min-height: 0;
    cursor: crosshair;
    touch-action: none;
}
#samplerCanvas {
    display: block;
    max-width: 100%;
    max-height: 100%;
}
.sampler-result-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-top: 1px solid var(--border);
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
    background: var(--card);
}
.sampler-result-swatch {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    border: 2px solid rgba(255,255,255,0.15);
    flex-shrink: 0;
}
.sampler-result-hex {
    font-size: 1.1rem;
    font-weight: 700;
    letter-spacing: 2px;
    color: var(--text);
    font-family: 'DM Mono', 'Fira Mono', monospace;
}
.sampler-hint {
    font-size: 0.65rem;
    color: var(--muted);
    padding: 6px 14px;
    flex-shrink: 0;
}

/* ════════════════════════════════════
   MODAL OVERLAY (for iframe video details)
════════════════════════════════════ */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.65); display: none; align-items: center; justify-content: center; z-index: 120000; padding: 12px; }
.modal-overlay.active { display: flex; }
.modal-card { width: 100%; max-width: 600px; background: var(--card); border-radius: 10px; box-shadow: 0 8px 30px rgba(2,6,23,0.35); display: flex; flex-direction: column; max-height: 90vh; border: 1px solid rgba(var(--muted-border-rgb),0.06); }
.modal-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; border-bottom: 1px solid rgba(var(--muted-border-rgb),0.08); }
.modal-body { padding: 20px; overflow-y: auto; color: var(--text); }
.modal-footer { padding: 12px 20px; border-top: 1px solid rgba(var(--muted-border-rgb),0.08); background: var(--bg); display: flex; justify-content: flex-end; gap: 8px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-muted); margin-bottom: 6px; }
.form-control { width: 100%; padding: 10px 12px; border-radius: 6px; border: 1px solid rgba(var(--muted-border-rgb), 0.12); font-size: 0.9rem; background: var(--bg); color: var(--text); transition: border-color 0.15s ease; }
</style>

<!-- ══════════════════════ HTML ══════════════════════ -->
<div class="rv-layout">

    <!-- LEFT COL / TOP -->
    <div class="rv-left-col">

        <div class="rv-player-wrap">
            <div class="rv-player-placeholder" id="playerPlaceholder">select a video</div>
            <video id="mainPlayer" style="display:none;"
                   controls playsinline controlsList="nodownload"
                   preload="metadata"></video>
            <div class="rv-flash" id="reviewFlash"></div>
        </div>

        <div class="rv-info">
            <div class="rv-video-name" id="videoName">—</div>
            <div class="rv-meta-row">
                <span id="videoIdEl">—</span>
                <span id="videoDurEl">—</span>
                <span id="videoSizeEl">—</span>
                <span class="rv-flagged-badge" id="flaggedBadge" style="display:none;">★ FLAGGED</span>
                <span class="rv-assigned-badge" id="assignedBadge" style="display:none;"></span>
            </div>
            <div class="rv-progress-track" id="progressTrack">
                <div class="rv-progress-fill" id="progressFill"></div>
            </div>
        </div>

        <div class="rv-actions">
            <button class="rv-btn rv-btn-nav"      id="btnPrev"     disabled onclick="navigate(-1)">◀</button>
            <button class="rv-btn rv-btn-review"   id="btnReview"   disabled onclick="toggleReview()" title="Flag for review">🏁</button>
            <button class="rv-btn rv-btn-animatic" id="btnAnimatic" disabled onclick="openVideoDetail()" title="Video Details">🎬</button>
            <button class="rv-btn rv-btn-assign"   id="btnAssign"   disabled onclick="openAssignModal()" title="Assign to story node">⬡</button>
            <button class="rv-btn rv-btn-filter"   id="btnFilter"   onclick="openFilterModal()" title="Filter / Focus">⚙</button>
            <button class="rv-btn rv-btn-nav"      id="btnNext"     disabled onclick="navigate(1)">▶</button>
        </div>

        <!-- DEFAULT MODE: MAP RUN SELECTOR -->
        <div class="mr-top-panel" id="mapRunPanel">
            <div class="mr-controls-row">
                <input type="text" class="mr-search-input" id="mrSearch" placeholder="Search Run..." oninput="debounceSearch()">
                <div class="mr-pagination">
                    <button class="pg-btn" id="mrPrev" onclick="changeMapRunPage(-1)">&#8592;</button>
                    <input type="number" class="pg-input" id="mrPageInput" value="1" onchange="jumpToMapRunPage()">
                    <span class="pg-total" id="mrTotalPages">/ 1</span>
                    <button class="pg-btn" id="mrNext" onclick="changeMapRunPage(1)">&#8594;</button>
                </div>
            </div>
            <div class="mr-list-scroll" id="mrList">
                <div class="rv-state">Loading runs...</div>
            </div>
        </div>

        <!-- FILTER MODE: STATUS BANNER -->
        <div id="filterActiveBanner" style="display:none; background:var(--card); border-bottom:1px solid var(--border); flex-shrink:0;">
            <div style="display:flex; align-items:center; justify-content:space-between; padding:8px 12px; gap:10px;">
                <div style="display:flex; align-items:center; gap:8px; overflow:hidden;">
                    <span style="font-size:0.75rem; color:var(--accent); font-weight:bold; white-space:nowrap;">⚙ FILTER</span>
                    <span id="filterBannerText" style="font-size:0.7rem; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"></span>
                </div>
                <button class="rv-btn" style="padding:0 12px; min-height:32px; border-color:var(--danger); color:var(--danger); flex-shrink:0;" onclick="resetFilter()">Reset</button>
            </div>
        </div>

    </div><!-- /.rv-left-col -->

    <!-- RIGHT COL / BOTTOM -->
    <div class="rv-right-col">

        <!-- BATCH SELECTION & PAGINATION TOOLBAR -->
        <div class="rv-pg-bar">
            <label class="rv-auto-label" style="padding-left: 2px;" title="Toggle Select Mode">
                <input type="checkbox" id="selectModeToggle" onchange="toggleSelectMode()" style="width:18px;height:18px;"> 
                <span style="font-size:0.75rem; font-weight:700;">SEL</span>
            </label>
            <button class="rv-pg-btn" onclick="selectAll()">ALL</button>
            <button class="rv-pg-btn" onclick="selectNone()">NONE</button>
            
            <span class="rv-pg-count" id="selectedCount">0/0 sel</span>

            <!-- Filter Grid Pagination -->
            <div id="gridPagination" style="display:none; align-items:center; gap:2px; margin-left:auto;">
                <button class="rv-pg-btn" id="gpPrev" onclick="changeGridPage(-1)" style="min-width:28px;">‹</button>
                <input type="number" class="pg-input" id="gpInput" value="1" onchange="jumpToGridPage()" style="width:40px; height:30px;">
                <span class="pg-total" id="gpTotalPages">/ 1</span>
                <button class="rv-pg-btn" id="gpNext" onclick="changeGridPage(1)" style="min-width:28px;">›</button>
            </div>

            <label class="rv-auto-label" style="margin-left: auto;">
                <input type="checkbox" id="autoAdvance" checked> Auto
            </label>
        </div>

        <div class="rv-grid-section">
            <div class="rv-state" id="gridState">
                <div class="rv-spinner"></div>
                <span>Loading…</span>
            </div>
            <div class="rv-grid" id="videoGrid" style="display:none;"></div>
        </div>

    </div><!-- /.rv-right-col -->

</div><!-- /.rv-layout -->

<!-- ══ ASSIGN TREE MODAL ══ -->
<div class="rv-modal-overlay" id="assignModal">
    <div class="rv-modal-sheet">
        <div class="rv-modal-header">
            <span class="rv-modal-title">⬡ Assign to Story Node</span>
            <button class="rv-modal-close" onclick="closeAssignModal()">✕</button>
        </div>
        <div class="rv-assign-current" id="assignCurrentStrip">
            <span id="assignCurrentText" style="color:var(--muted);">No assignment</span>
            <button class="rv-unassign-btn" id="btnUnassign" style="display:none;" onclick="unassignVideo()">Unassign</button>
        </div>
        <div class="rv-tree-toolbar">
            <input type="text" id="newNodeName" placeholder="New node name…">
            <select id="newNodeType">
                <option value="folder">Folder</option>
                <option value="episode">Episode</option>
                <option value="sequence">Sequence</option>
                <option value="scene">Scene</option>
                <option value="other">Other</option>
            </select>
            <button class="rv-tree-add-btn" onclick="createTreeNode()">+ Add</button>
        </div>
        <div class="rv-tree-scroll">
            <div id="assignTree">Loading…</div>
        </div>
        <div class="rv-modal-footer">
            <button class="rv-assign-confirm-btn" id="btnAssignConfirm" disabled onclick="confirmAssign()">
                Assign to Selected Node
            </button>
        </div>
    </div>
</div>

<!-- ══ FILTER MODAL ══ -->
<div class="rv-modal-overlay" id="filterModal">
    <div class="rv-modal-sheet">
        <div class="rv-modal-header">
            <span class="rv-modal-title">⚙ Filter / Focus</span>
            <button class="rv-modal-close" onclick="closeFilterModal()">✕</button>
        </div>
        <div class="rv-assign-current" id="filterActiveStrip">
            <span id="filterActiveText" style="color:var(--muted);">No filter active</span>
        </div>
        <div style="display:flex; gap:16px; padding:8px 14px; border-bottom:1px solid var(--border); flex-shrink:0;">
            <label style="display:flex; align-items:center; gap:6px; font-size:0.75rem; cursor:pointer;">
                <input type="checkbox" id="onlyFlaggedCb" style="accent-color:var(--accent); width:16px; height:16px;">
                <span>Flagged only</span>
            </label>
            <label style="display:flex; align-items:center; gap:6px; font-size:0.75rem; cursor:pointer;">
                <input type="checkbox" id="inclDescendantsCb" checked style="accent-color:var(--accent); width:16px; height:16px;">
                <span>Include descendants</span>
            </label>
        </div>

        <!-- Tab bar -->
        <div class="rv-filter-tabs">
            <div class="rv-filter-tab active" data-tab="tree"       onclick="switchFilterTab('tree')">🌲 Tree</div>
            <div class="rv-filter-tab"        data-tab="narratives" onclick="switchFilterTab('narratives')">📖 Narratives</div>
            <div class="rv-filter-tab"        data-tab="fuzz"       onclick="switchFilterTab('fuzz')">🔮 Fuzz</div>
            <div class="rv-filter-tab"        data-tab="storyboard" onclick="switchFilterTab('storyboard')">🎬 Storyboard</div>
        </div>

        <!-- ── Tab: Story Tree ── -->
        <div class="rv-filter-tab-panel active" id="filterTabTree">
            <div class="rv-tree-scroll">
                <div id="filterTree">Loading…</div>
            </div>
        </div>

        <!-- ── Tab: Narrative Sequences ── -->
        <div class="rv-filter-tab-panel" id="filterTabNarratives">
            <div class="rv-fuzz-search">
                <input type="text" id="seqSearchInput" placeholder="Search sequences…"
                       oninput="debounceSeqSearch(this.value)">
            </div>
            <div class="rv-seq-list" id="seqList">
                <div style="color:var(--muted);font-size:0.7rem;padding:12px;text-align:center;">Loading sequences…</div>
            </div>
            <div class="filter-pg" id="seq-pg" style="display:none;">
                <button class="filter-pg-btn" id="seq-prev" disabled>‹</button>
                <input type="number" class="filter-pg-input" id="seq-pg-input" value="1" min="1">
                <span class="filter-pg-of" id="seq-pg-of">/ 1</span>
                <button class="filter-pg-btn" id="seq-next" disabled>›</button>
            </div>
        </div>

        <!-- ── Tab: Fuzz Candidates ── -->
        <div class="rv-filter-tab-panel" id="filterTabFuzz">
            <div class="rv-fuzz-search">
                <input type="text" id="fuzzSearchInput" placeholder="Search candidates…"
                       oninput="debounceFuzzSearch(this.value)">
            </div>
            <div class="rv-fuzz-list" id="fuzzList">
                <div style="color:var(--muted);font-size:0.7rem;padding:12px;text-align:center;">Loading candidates…</div>
            </div>
            <div class="filter-pg" id="fuzz-pg" style="display:none;">
                <button class="filter-pg-btn" id="fuzz-prev" disabled>‹</button>
                <input type="number" class="filter-pg-input" id="fuzz-pg-input" value="1" min="1">
                <span class="filter-pg-of" id="fuzz-pg-of">/ 1</span>
                <button class="filter-pg-btn" id="fuzz-next" disabled>›</button>
            </div>
        </div>

        <!-- ── Tab: Storyboard ── -->
        <div class="rv-filter-tab-panel" id="filterTabStoryboard">
            <div class="rv-fuzz-search">
                <input type="text" id="sbFilterSearchInput" placeholder="Search storyboards…"
                       oninput="debounceSbFilterSearch(this.value)">
            </div>
            <div class="rv-seq-list" id="sbFilterList">
                <div style="color:var(--muted);font-size:0.7rem;padding:12px;text-align:center;">Loading storyboards…</div>
            </div>
            <div class="filter-pg" id="sb-filter-pg" style="display:none;">
                <button class="filter-pg-btn" id="sb-filter-prev" disabled>‹</button>
                <input type="number" class="filter-pg-input" id="sb-filter-pg-input" value="1" min="1">
                <span class="filter-pg-of" id="sb-filter-pg-of">/ 1</span>
                <button class="filter-pg-btn" id="sb-filter-next" disabled>›</button>
            </div>
        </div>

        <div class="rv-modal-footer" style="display:flex; gap:8px;">
            <button class="rv-assign-confirm-btn" onclick="browseAll()" style="background:var(--bg); border:1px solid var(--border); color:var(--text); flex:0.5;">
                Browse All
            </button>
            <button class="rv-assign-confirm-btn" id="btnApplyFilter" onclick="applyFilter()" style="background:var(--accent); color:#fff;">
                Apply Filter
            </button>
        </div>
    </div>
</div>

<!-- ══ REMBG CONFIRMATION MODAL ══ -->
<div class="rv-modal-overlay" id="rembgModal" style="z-index: 120010;">
    <div class="rv-modal-sheet">
        <div class="rv-modal-header">
            <span class="rv-modal-title">◩ Remove Background</span>
            <button class="rv-modal-close" onclick="closeRembgModal()">✕</button>
        </div>

        <!-- Color Row -->
        <div class="rembg-color-row">
            <div class="rembg-swatch" id="rembgSwatch" onclick="syncSwatchFromInput()" title="Current color"></div>
            <input type="text" class="rembg-hex-input" id="rembgHexInput" value="#00FB00" maxlength="7"
                   oninput="onRembgHexInput()" placeholder="#00FB00">
            <button class="rembg-pick-btn" onclick="openSamplerModal()">Pick from<br>Thumb</button>
        </div>

        <!-- Info -->
        <div class="rembg-info-row" id="rembgInfoRow">
            Target animatic: <span id="rembgAnimaticId" style="color:var(--accent); font-weight:700;">—</span>
            &nbsp;|&nbsp; Source video: <span id="rembgVideoId" style="color:var(--accent); font-weight:700;">—</span>
        </div>

        <div class="rv-modal-footer" style="gap:8px;">
            <button class="rv-assign-confirm-btn"
                    style="background:var(--bg); border:1px solid var(--border); color:var(--text); flex:0.5;"
                    onclick="closeRembgModal()">
                Cancel
            </button>
            <button class="rv-assign-confirm-btn" id="btnRembgConfirm" onclick="confirmRembg()">
                Queue Removal
            </button>
        </div>
    </div>
</div>

<!-- ══ COLOR SAMPLER MODAL ══ -->
<div class="rv-modal-overlay" id="samplerModal" style="z-index: 120015;">
    <div class="rv-modal-sheet" style="max-height:92dvh;">
        <div class="rv-modal-header">
            <span class="rv-modal-title">🎨 Pick Green Color</span>
            <button class="rv-modal-close" onclick="closeSamplerModal()">✕</button>
        </div>

        <div class="sampler-hint">Tap the green area on the thumbnail to sample its color.</div>

        <!-- Canvas Area -->
        <div class="sampler-canvas-wrap" id="samplerCanvasWrap">
            <canvas id="samplerCanvas"></canvas>
        </div>

        <!-- Result Row -->
        <div class="sampler-result-row">
            <div class="sampler-result-swatch" id="samplerSwatch" style="background:#00FB00;"></div>
            <span class="sampler-result-hex" id="samplerHex">#00FB00</span>
            <span style="font-size:0.65rem; color:var(--muted); margin-left:auto;">Tap to retap</span>
        </div>

        <div class="rv-modal-footer" style="gap:8px;">
            <button class="rv-assign-confirm-btn"
                    style="background:var(--bg); border:1px solid var(--border); color:var(--text); flex:0.5;"
                    onclick="closeSamplerModal()">
                Cancel
            </button>
            <button class="rv-assign-confirm-btn" id="btnUseSampledColor" onclick="useSampledColor()">
                Use This Color
            </button>
        </div>
    </div>
</div>

<!-- Modules Rendering -->
<?= $videoExtractor->render() ?>
<?= $imageEditor->render() ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>

<script>
(function () {
    'use strict';

    // ── Global Mode State ──
    let isFilterMode = false;
    let _afterLoad   = null;

    // ── Map Run State ──
    let mrCurPage    = 1;
    let mrTotalPages = 1;
    let currentRunId = null;
    let mrDebounceTimer;

    // ── Filter State ──
    let onlyFlagged    = false;
    let inclDesc       = true;
    let filterNodeId   = null;
    let filterNodeName = '';
    let filterSeqId    = null;
    let filterSeqName  = '';
    let filterFuzzCandId   = null;
    let filterFuzzCandName = '';
    let filterSbId         = null;
    let filterSbName       = '';
    let gridPage = 1;
    let gridTotalPages = 1;
    
    let seqCurPage = 1;
    let seqTotalPages = 1;
    let seqDebounceTimer = null;
    
    let fuzzCurPage = 1;
    let fuzzTotalPages = 1;
    let fuzzDebounceTimer = null;

    let sbFilterCurPage   = 1;
    let sbFilterTotalPages = 1;
    let sbFilterDebounceTimer = null;

    // ── Video State ──
    let videos       =[];
    let curIndex     = -1;
    let categories   =[];
    let playlists    =[];
    let currentDetailVideoId = null;
    
    // ── Batch Selection State ──
    let selectMode       = false;
    let selectedVideoIds = new Set();

    // ── Rembg / Sampler State ──
    let rembgTargetVideoId   = null;
    let rembgTargetAnimaticId = null;
    let rembgThumbnailUrl    = null;
    let samplerPickedColor   = '#00FB00';
    let samplerImg           = null;

    // ── DOM ──
    const player        = document.getElementById('mainPlayer');
    const placeholder   = document.getElementById('playerPlaceholder');
    const reviewFlash   = document.getElementById('reviewFlash');
    const videoNameEl   = document.getElementById('videoName');
    const videoIdEl     = document.getElementById('videoIdEl');
    const videoDurEl    = document.getElementById('videoDurEl');
    const videoSizeEl   = document.getElementById('videoSizeEl');
    const flaggedBadge  = document.getElementById('flaggedBadge');
    const btnPrev       = document.getElementById('btnPrev');
    const btnNext       = document.getElementById('btnNext');
    const btnReview     = document.getElementById('btnReview');
    const btnAssign     = document.getElementById('btnAssign');
    const btnAnimatic   = document.getElementById('btnAnimatic');
    const progressFill  = document.getElementById('progressFill');
    const progressTrack = document.getElementById('progressTrack');
    const gridEl        = document.getElementById('videoGrid');
    const gridState     = document.getElementById('gridState');
    const autoAdvanceCb = document.getElementById('autoAdvance');

    // ── Helpers ──
    function fmtDur(s) {
        if (!s) return '0:00';
        const m = Math.floor(s / 60), sc = Math.floor(s % 60);
        return `${m}:${sc.toString().padStart(2,'0')}`;
    }
    function fmtSize(b) { return b ? (b/1024/1024).toFixed(1)+' MB' : ''; }
    function escH(s) { return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

    // ════════════════════════════════════
    // INITIALIZATION
    // ════════════════════════════════════
    Promise.all([
        fetch('video_admin_api.php?action=list_categories').then(r => r.json()),
        fetch('video_admin_api.php?action=list_playlists').then(r => r.json())
    ]).then(([catData, plData]) => {
        if (catData.status === 'ok') categories = catData.categories;
        if (plData.status === 'ok') playlists = plData.playlists;
    });

    // ════════════════════════════════════
    // VIDEO DETAIL — opens encapsulated view in iframe modal
    // ════════════════════════════════════
    function openVideoDetail() {
        if (curIndex < 0 || curIndex >= videos.length) return;
        const vid = videos[curIndex];
        if (!vid) return;
        if (player && !player.paused) player.pause();
        if (typeof window.showVideoDetailsModal === 'function') {
            window.showVideoDetailsModal(vid.id);
        }
    }

    // ════════════════════════════════════
    // REMBG MODAL
    // ════════════════════════════════════

    function openRembgModal(videoId, animaticId, thumbnailUrl) {
        rembgTargetVideoId    = videoId;
        rembgTargetAnimaticId = animaticId;
        rembgThumbnailUrl     = thumbnailUrl;
        setRembgColor('#00FB00');
        document.getElementById('rembgVideoId').textContent    = '#' + videoId;
        document.getElementById('rembgAnimaticId').textContent = animaticId ? '#' + animaticId : 'none';
        document.getElementById('rembgModal').classList.add('active');
    }

    function closeRembgModal() {
        document.getElementById('rembgModal').classList.remove('active');
    }

    function setRembgColor(hex) {
        hex = hex.toUpperCase();
        if (!/^#[0-9A-F]{6}$/.test(hex)) return;
        document.getElementById('rembgHexInput').value = hex;
        document.getElementById('rembgSwatch').style.background = hex;
    }

    function onRembgHexInput() {
        const val = document.getElementById('rembgHexInput').value.trim();
        if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
            document.getElementById('rembgSwatch').style.background = val;
        }
    }

    function syncSwatchFromInput() { onRembgHexInput(); }

    function confirmRembg() {
        const hex = document.getElementById('rembgHexInput').value.trim().toUpperCase();
        if (!/^#[0-9A-F]{6}$/.test(hex)) {
            if (typeof Toast !== 'undefined') Toast.show('Invalid hex color', 'error');
            return;
        }
        if (!rembgTargetVideoId) return;

        const btn = document.getElementById('btnRembgConfirm');
        const origText = btn.textContent;
        btn.disabled = true;
        btn.textContent = 'Queuing…';

        fetch('video_admin_api.php?action=queue_rembg', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: rembgTargetVideoId, chromakey_color: hex })
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'ok') {
                closeRembgModal();
                if (typeof Toast !== 'undefined') Toast.show('Background removal queued ✓', 'success');
            } else {
                if (typeof Toast !== 'undefined') Toast.show(d.message || 'Error', 'error');
            }
        })
        .finally(() => {
            btn.disabled = false;
            btn.textContent = origText;
        });
    }

    // ════════════════════════════════════
    // COLOR SAMPLER MODAL
    // ════════════════════════════════════

    const SAMPLE_RADIUS = 10;

    function openSamplerModal() {
        if (!rembgThumbnailUrl) {
            if (typeof Toast !== 'undefined') Toast.show('No thumbnail available', 'error');
            return;
        }
        samplerPickedColor = document.getElementById('rembgHexInput').value.trim() || '#00FB00';
        document.getElementById('samplerSwatch').style.background = samplerPickedColor;
        document.getElementById('samplerHex').textContent = samplerPickedColor.toUpperCase();
        document.getElementById('samplerModal').classList.add('active');
        requestAnimationFrame(() => { loadSamplerImage(rembgThumbnailUrl); });
    }

    function closeSamplerModal() {
        document.getElementById('samplerModal').classList.remove('active');
    }

    function loadSamplerImage(url) {
        const canvas = document.getElementById('samplerCanvas');
        const wrap   = document.getElementById('samplerCanvasWrap');
        const ctx    = canvas.getContext('2d');
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function () {
            samplerImg = img;
            const wrapW = wrap.clientWidth;
            const wrapH = wrap.clientHeight;
            const scale = Math.min(wrapW / img.naturalWidth, wrapH / img.naturalHeight);
            canvas.width  = Math.round(img.naturalWidth  * scale);
            canvas.height = Math.round(img.naturalHeight * scale);
            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        };
        img.onerror = function () {
            if (typeof Toast !== 'undefined') Toast.show('Could not load thumbnail', 'error');
        };
        img.src = url;
    }

    function sampleCanvasAt(canvasX, canvasY) {
        const canvas = document.getElementById('samplerCanvas');
        const ctx    = canvas.getContext('2d');
        const r = SAMPLE_RADIUS;
        let totalR = 0, totalG = 0, totalB = 0, count = 0;
        const x0 = Math.max(0, Math.round(canvasX - r));
        const y0 = Math.max(0, Math.round(canvasY - r));
        const x1 = Math.min(canvas.width  - 1, Math.round(canvasX + r));
        const y1 = Math.min(canvas.height - 1, Math.round(canvasY + r));
        const imageData = ctx.getImageData(x0, y0, x1 - x0 + 1, y1 - y0 + 1);
        const data = imageData.data;
        for (let py = y0; py <= y1; py++) {
            for (let px = x0; px <= x1; px++) {
                const dx = px - canvasX, dy = py - canvasY;
                if (dx * dx + dy * dy <= r * r) {
                    const idx = ((py - y0) * (x1 - x0 + 1) + (px - x0)) * 4;
                    totalR += data[idx]; totalG += data[idx + 1]; totalB += data[idx + 2];
                    count++;
                }
            }
        }
        if (count === 0) return null;
        const avgR = Math.round(totalR / count);
        const avgG = Math.round(totalG / count);
        const avgB = Math.round(totalB / count);
        return '#' + [avgR, avgG, avgB].map(v => v.toString(16).padStart(2, '0')).join('').toUpperCase();
    }

    function drawIndicator(canvasX, canvasY, hex) {
        const canvas = document.getElementById('samplerCanvas');
        const ctx    = canvas.getContext('2d');
        if (samplerImg) ctx.drawImage(samplerImg, 0, 0, canvas.width, canvas.height);
        ctx.beginPath();
        ctx.arc(canvasX, canvasY, SAMPLE_RADIUS + 2, 0, Math.PI * 2);
        ctx.strokeStyle = 'rgba(0,0,0,0.7)';
        ctx.lineWidth = 2.5;
        ctx.stroke();
        ctx.beginPath();
        ctx.arc(canvasX, canvasY, SAMPLE_RADIUS, 0, Math.PI * 2);
        ctx.strokeStyle = '#ffffff';
        ctx.lineWidth = 1.5;
        ctx.stroke();
    }

    document.getElementById('samplerCanvas').addEventListener('click', function(e) {
        const canvas = this;
        const rect   = canvas.getBoundingClientRect();
        const canvasX = e.clientX - rect.left;
        const canvasY = e.clientY - rect.top;
        const hex = sampleCanvasAt(canvasX, canvasY);
        if (!hex) return;
        samplerPickedColor = hex;
        drawIndicator(canvasX, canvasY, hex);
        document.getElementById('samplerSwatch').style.background = hex;
        document.getElementById('samplerHex').textContent = hex;
    });

    document.getElementById('samplerCanvas').addEventListener('touchend', function(e) {
        e.preventDefault();
        const touch  = e.changedTouches[0];
        const canvas = this;
        const rect   = canvas.getBoundingClientRect();
        const canvasX = touch.clientX - rect.left;
        const canvasY = touch.clientY - rect.top;
        const hex = sampleCanvasAt(canvasX, canvasY);
        if (!hex) return;
        samplerPickedColor = hex;
        drawIndicator(canvasX, canvasY, hex);
        document.getElementById('samplerSwatch').style.background = hex;
        document.getElementById('samplerHex').textContent = hex;
    }, { passive: false });

    function useSampledColor() {
        setRembgColor(samplerPickedColor);
        closeSamplerModal();
    }

    // Expose rembg/sampler helpers globally
    window.openRembgModal    = openRembgModal;
    window.closeRembgModal   = closeRembgModal;
    window.onRembgHexInput   = onRembgHexInput;
    window.syncSwatchFromInput = syncSwatchFromInput;
    window.confirmRembg      = confirmRembg;
    window.openSamplerModal  = openSamplerModal;
    window.closeSamplerModal = closeSamplerModal;
    window.useSampledColor   = useSampledColor;

    // ════════════════════════════════════
    // FILTER MODAL & LOGIC
    // ════════════════════════════════════
    let filterTreeInited      = false;
    let pendingFilterNodeId   = null;
    let pendingFilterNodeName = '';
    let pendingSeqId          = null;
    let pendingSeqName        = '';
    let pendingFuzzCandId     = null;
    let pendingFuzzCandName   = '';
    let pendingSbId           = null;
    let pendingSbName         = '';
    let activeFilterTab       = 'tree';

    function openFilterModal() {
        document.getElementById('onlyFlaggedCb').checked = onlyFlagged;
        document.getElementById('inclDescendantsCb').checked = inclDesc;
        pendingFilterNodeId   = filterNodeId;
        pendingFilterNodeName = filterNodeName;
        pendingSeqId          = filterSeqId;
        pendingSeqName        = filterSeqName;
        pendingFuzzCandId     = filterFuzzCandId;
        pendingFuzzCandName   = filterFuzzCandName;
        pendingSbId           = filterSbId;
        pendingSbName         = filterSbName;
        updateFilterStrip();
        document.getElementById('filterModal').classList.add('active');

        if (activeFilterTab === 'tree') {
            if (!filterTreeInited) initFilterTree();
            else $('#filterTree').jstree('refresh');
        } else if (activeFilterTab === 'narratives') {
            loadNarrativeSequences(seqCurPage, document.getElementById('seqSearchInput').value);
        } else if (activeFilterTab === 'fuzz') {
            loadFuzzCandidates(fuzzCurPage, document.getElementById('fuzzSearchInput').value);
        } else if (activeFilterTab === 'storyboard') {
            loadSbFilterList(sbFilterCurPage, document.getElementById('sbFilterSearchInput').value);
        }
    }

    function closeFilterModal() { document.getElementById('filterModal').classList.remove('active'); }

    // ── Tab switching ──
    function switchFilterTab(tab) {
        activeFilterTab = tab;
        document.querySelectorAll('.rv-filter-tab').forEach(t => {
            t.classList.toggle('active', t.dataset.tab === tab);
        });
        document.querySelectorAll('.rv-filter-tab-panel').forEach(p => {
            p.classList.toggle('active', p.id === 'filterTab' + tab.charAt(0).toUpperCase() + tab.slice(1));
        });

        if (tab === 'tree') {
            if (!filterTreeInited) initFilterTree();
            else $('#filterTree').jstree('refresh');
        } else if (tab === 'narratives') {
            loadNarrativeSequences(seqCurPage, document.getElementById('seqSearchInput').value);
        } else if (tab === 'fuzz') {
            loadFuzzCandidates(fuzzCurPage, document.getElementById('fuzzSearchInput').value);
        } else if (tab === 'storyboard') {
            loadSbFilterList(sbFilterCurPage, document.getElementById('sbFilterSearchInput').value);
        }
    }
    window.switchFilterTab = switchFilterTab;

    // ── Active strip ──
    function updateFilterStrip() {
        let label = '';
        if (pendingFuzzCandId) {
            label = '🔮 ' + (pendingFuzzCandName || 'Fuzz #' + pendingFuzzCandId);
        } else if (pendingSeqId) {
            label = '📖 ' + (pendingSeqName || 'Seq #' + pendingSeqId);
        } else if (pendingSbId) {
            label = '🎬 ' + (pendingSbName || 'SB #' + pendingSbId);
        } else if (pendingFilterNodeId) {
            label = '🌲 ' + (pendingFilterNodeName || 'Node #' + pendingFilterNodeId);
        }
        const el = document.getElementById('filterActiveText');
        if (label) {
            el.innerHTML = '<span class="node-name">' + escH(label) + '</span>';
        } else {
            el.innerHTML = '<span style="color:var(--muted);">No filter active</span>';
        }
    }

    function initFilterTree() {
        filterTreeInited = true;
        $('#filterTree').jstree({
            core: {
                data: {
                    url: '?api_action=tree_fetch',
                    dataType: 'json',
                    dataFilter: function(raw) {
                        try {
                            const j = JSON.parse(raw); return JSON.stringify(j.status === 'ok' ? j.tree :[]);
                        } catch(e) { return '[]'; }
                    }
                },
                themes: { name: 'default', dots: true, icons: true },
                check_callback: false,
            },
            plugins: ['types'],
            types: {
                folder:   { icon: 'bi bi-folder2' },
                episode:  { icon: 'bi bi-film' },
                sequence: { icon: 'bi bi-collection-play' },
                scene:    { icon: 'bi bi-camera-video' },
                other:    { icon: 'bi bi-tag' },
            },
        }).on('select_node.jstree', function(e, data) {
            pendingFilterNodeId   = data.node.data.db_id;
            pendingFilterNodeName = data.node.text;
            // Clear other pending selections
            pendingSeqId      = null;
            pendingSeqName    = '';
            pendingFuzzCandId = null;
            pendingFuzzCandName = '';
            document.querySelectorAll('.rv-seq-item.selected').forEach(el => el.classList.remove('selected'));
            document.querySelectorAll('.rv-fuzz-item.selected').forEach(el => el.classList.remove('selected'));
            updateFilterStrip();
        }).on('deselect_node.jstree', function() {
            pendingFilterNodeId   = null;
            pendingFilterNodeName = '';
            updateFilterStrip();
        });
    }

    // ── Narrative Sequences tab ──
    function debounceSeqSearch(val) {
        clearTimeout(seqDebounceTimer);
        seqDebounceTimer = setTimeout(() => loadNarrativeSequences(1, val), 300);
    }
    window.debounceSeqSearch = debounceSeqSearch;

    function loadNarrativeSequences(page = 1, search = '') {
        seqCurPage = page;
        const listEl = document.getElementById('seqList');
        listEl.innerHTML = '<div style="color:var(--muted);font-size:0.7rem;padding:12px;text-align:center;"><div class="rv-spinner" style="margin:0 auto 8px;"></div>Loading…</div>';

        const p = new URLSearchParams({ api_action: 'list_narrative_sequences', page, search });

        fetch('?' + p)
            .then(r => r.json())
            .then(d => {
                if (d.status !== 'ok') throw new Error(d.message || 'Error');
                const seqs = d.sequences ||[];
                seqTotalPages = d.total_pages || 1;

                document.getElementById('seq-pg-input').value = seqCurPage;
                document.getElementById('seq-pg-of').textContent = '/ ' + seqTotalPages;
                document.getElementById('seq-prev').disabled = seqCurPage <= 1;
                document.getElementById('seq-next').disabled = seqCurPage >= seqTotalPages;
                document.getElementById('seq-pg').style.display = seqTotalPages > 1 ? 'flex' : 'none';

                if (!seqs.length) {
                    listEl.innerHTML = '<div style="color:var(--muted);font-size:0.7rem;padding:12px;text-align:center;">No narrative sequences found.</div>';
                    return;
                }
                listEl.innerHTML = seqs.map(s => `
                    <div class="rv-seq-item ${pendingSeqId == s.id ? 'selected' : ''}"
                         data-id="${s.id}" data-name="${escH(s.name)}"
                         onclick="selectSeqFilter(${s.id}, ${JSON.stringify(s.name).replace(/"/g, '&quot;')})">
                        <div class="rv-seq-item-name">${escH(s.name)}</div>
                        ${s.description ? '<div class="rv-seq-item-desc">' + escH(s.description) + '</div>' : ''}
                    </div>
                `).join('');
            })
            .catch(err => {
                listEl.innerHTML = '<div style="color:var(--danger);font-size:0.7rem;padding:12px;">Error: ' + escH(err.message) + '</div>';
                document.getElementById('seq-pg').style.display = 'none';
            });
    }

    document.getElementById('seq-prev').addEventListener('click', () => loadNarrativeSequences(seqCurPage - 1, document.getElementById('seqSearchInput').value));
    document.getElementById('seq-next').addEventListener('click', () => loadNarrativeSequences(seqCurPage + 1, document.getElementById('seqSearchInput').value));
    document.getElementById('seq-pg-input').addEventListener('change', function() {
        const v = parseInt(this.value, 10);
        if (!isNaN(v) && v >= 1 && v <= seqTotalPages) loadNarrativeSequences(v, document.getElementById('seqSearchInput').value);
        else this.value = seqCurPage;
    });

    function selectSeqFilter(id, name) {
        pendingSeqId      = id;
        pendingSeqName    = name;
        pendingFilterNodeId   = null;
        pendingFilterNodeName = '';
        pendingFuzzCandId     = null;
        pendingFuzzCandName   = '';
        if (filterTreeInited) $('#filterTree').jstree('deselect_all');
        document.querySelectorAll('.rv-fuzz-item.selected').forEach(el => el.classList.remove('selected'));
        document.querySelectorAll('.rv-seq-item').forEach(el => {
            el.classList.toggle('selected', parseInt(el.dataset.id) === id);
        });
        updateFilterStrip();
    }
    window.selectSeqFilter = selectSeqFilter;

    // ── Fuzz Candidates tab ──
    function debounceFuzzSearch(val) {
        clearTimeout(fuzzDebounceTimer);
        fuzzDebounceTimer = setTimeout(() => loadFuzzCandidates(1, val), 300);
    }
    window.debounceFuzzSearch = debounceFuzzSearch;

    function loadFuzzCandidates(page = 1, search = '') {
        fuzzCurPage = page;
        const listEl = document.getElementById('fuzzList');
        listEl.innerHTML = '<div style="color:var(--muted);font-size:0.7rem;padding:12px;text-align:center;"><div class="rv-spinner" style="margin:0 auto 8px;"></div>Loading…</div>';

        const p = new URLSearchParams({ api_action: 'list_fuzz_candidates', page, search });

        fetch('?' + p)
            .then(r => r.json())
            .then(d => {
                if (d.status !== 'ok') throw new Error(d.message || 'Error');
                const cands = d.candidates ||[];
                fuzzTotalPages = d.total_pages || 1;

                document.getElementById('fuzz-pg-input').value = fuzzCurPage;
                document.getElementById('fuzz-pg-of').textContent = '/ ' + fuzzTotalPages;
                document.getElementById('fuzz-prev').disabled = fuzzCurPage <= 1;
                document.getElementById('fuzz-next').disabled = fuzzCurPage >= fuzzTotalPages;
                document.getElementById('fuzz-pg').style.display = fuzzTotalPages > 1 ? 'flex' : 'none';

                if (!cands.length) {
                    listEl.innerHTML = '<div style="color:var(--muted);font-size:0.7rem;padding:12px;text-align:center;">No candidates found.</div>';
                    return;
                }
                listEl.innerHTML = cands.map(c => `
                    <div class="rv-fuzz-item ${pendingFuzzCandId == c.id ? 'selected' : ''}"
                         data-id="${c.id}" data-name="${escH(c.label)}"
                         onclick="selectFuzzFilter(${c.id}, ${JSON.stringify(c.label).replace(/"/g, '&quot;')})">
                        <span class="rv-fuzz-item-label">${escH(c.label)}</span>
                        <span class="rv-fuzz-item-meta">${c.mention_count ? c.mention_count + ' ment.' : ''}${c.concept_type ? ' · ' + escH(c.concept_type) : ''}</span>
                    </div>
                `).join('');
            })
            .catch(err => {
                listEl.innerHTML = '<div style="color:var(--danger);font-size:0.7rem;padding:12px;">Error: ' + escH(err.message) + '</div>';
                document.getElementById('fuzz-pg').style.display = 'none';
            });
    }

    document.getElementById('fuzz-prev').addEventListener('click', () => loadFuzzCandidates(fuzzCurPage - 1, document.getElementById('fuzzSearchInput').value));
    document.getElementById('fuzz-next').addEventListener('click', () => loadFuzzCandidates(fuzzCurPage + 1, document.getElementById('fuzzSearchInput').value));
    document.getElementById('fuzz-pg-input').addEventListener('change', function() {
        const v = parseInt(this.value, 10);
        if (!isNaN(v) && v >= 1 && v <= fuzzTotalPages) loadFuzzCandidates(v, document.getElementById('fuzzSearchInput').value);
        else this.value = fuzzCurPage;
    });

    function selectFuzzFilter(id, name) {
        pendingFuzzCandId   = id;
        pendingFuzzCandName = name;
        pendingFilterNodeId   = null;
        pendingFilterNodeName = '';
        pendingSeqId          = null;
        pendingSeqName        = '';
        if (filterTreeInited) $('#filterTree').jstree('deselect_all');
        document.querySelectorAll('.rv-seq-item.selected').forEach(el => el.classList.remove('selected'));
        document.querySelectorAll('.rv-fuzz-item').forEach(el => {
            el.classList.toggle('selected', parseInt(el.dataset.id) === id);
        });
        updateFilterStrip();
    }
    window.selectFuzzFilter = selectFuzzFilter;

    // ── Storyboard filter tab ──
    function debounceSbFilterSearch(val) {
        clearTimeout(sbFilterDebounceTimer);
        sbFilterDebounceTimer = setTimeout(() => loadSbFilterList(1, val), 300);
    }
    window.debounceSbFilterSearch = debounceSbFilterSearch;

    function loadSbFilterList(page = 1, search = '') {
        sbFilterCurPage = page;
        const listEl = document.getElementById('sbFilterList');
        listEl.innerHTML = '<div style="color:var(--muted);font-size:0.7rem;padding:12px;text-align:center;"><div class="rv-spinner" style="margin:0 auto 8px;"></div>Loading…</div>';

        const p = new URLSearchParams({ api_action: 'get_storyboards', limit: 20, offset: (page - 1) * 20, search });

        fetch('?' + p)
            .then(r => r.json())
            .then(d => {
                if (d.status !== 'ok') throw new Error(d.message || 'Error');
                const sbs = d.data || [];
                sbFilterTotalPages = Math.ceil(d.total / 20) || 1;

                document.getElementById('sb-filter-pg-input').value = sbFilterCurPage;
                document.getElementById('sb-filter-pg-of').textContent = '/ ' + sbFilterTotalPages;
                document.getElementById('sb-filter-prev').disabled = sbFilterCurPage <= 1;
                document.getElementById('sb-filter-next').disabled = sbFilterCurPage >= sbFilterTotalPages;
                document.getElementById('sb-filter-pg').style.display = sbFilterTotalPages > 1 ? 'flex' : 'none';

                if (!sbs.length) {
                    listEl.innerHTML = '<div style="color:var(--muted);font-size:0.7rem;padding:12px;text-align:center;">No storyboards found.</div>';
                    return;
                }
                listEl.innerHTML = sbs.map(s => `
                    <div class="rv-seq-item ${pendingSbId == s.id ? 'selected' : ''}"
                         data-id="${s.id}" data-name="${escH(s.name)}"
                         onclick="selectSbFilter(${s.id}, ${JSON.stringify(s.name || '').replace(/"/g, '&quot;')})">
                        <div class="rv-seq-item-name">#${s.id} ${escH(s.name || 'Untitled')}</div>
                        <div class="rv-seq-item-desc">${s.frame_count} frames</div>
                    </div>
                `).join('');
            })
            .catch(err => {
                listEl.innerHTML = '<div style="color:var(--danger);font-size:0.7rem;padding:12px;">Error: ' + escH(err.message) + '</div>';
                document.getElementById('sb-filter-pg').style.display = 'none';
            });
    }

    document.getElementById('sb-filter-prev').addEventListener('click', () =>
        loadSbFilterList(sbFilterCurPage - 1, document.getElementById('sbFilterSearchInput').value));
    document.getElementById('sb-filter-next').addEventListener('click', () =>
        loadSbFilterList(sbFilterCurPage + 1, document.getElementById('sbFilterSearchInput').value));
    document.getElementById('sb-filter-pg-input').addEventListener('change', function() {
        const v = parseInt(this.value, 10);
        if (!isNaN(v) && v >= 1 && v <= sbFilterTotalPages)
            loadSbFilterList(v, document.getElementById('sbFilterSearchInput').value);
        else this.value = sbFilterCurPage;
    });

    function selectSbFilter(id, name) {
        pendingSbId           = id;
        pendingSbName         = name;
        pendingFilterNodeId   = null;
        pendingFilterNodeName = '';
        pendingSeqId          = null;
        pendingSeqName        = '';
        pendingFuzzCandId     = null;
        pendingFuzzCandName   = '';
        if (filterTreeInited) $('#filterTree').jstree('deselect_all');
        document.querySelectorAll('.rv-seq-item.selected').forEach(el => el.classList.remove('selected'));
        document.querySelectorAll('.rv-fuzz-item.selected').forEach(el => el.classList.remove('selected'));
        document.querySelectorAll('#sbFilterList .rv-seq-item').forEach(el => {
            el.classList.toggle('selected', parseInt(el.dataset.id) === id);
        });
        updateFilterStrip();
    }
    window.selectSbFilter = selectSbFilter;

    // ── Build banner text from current filter state ──
    function buildBannerText(flagged, nodeId, nodeName, seqId, seqName, fuzzId, fuzzName, sbId, sbName) {
        const parts =[];
        if (flagged)       parts.push('★ Flagged Only');
        if (fuzzId)        parts.push('🔮 Fuzz: ' + (fuzzName || '#' + fuzzId));
        else if (seqId)    parts.push('📖 Seq: ' + (seqName || '#' + seqId));
        else if (sbId)     parts.push('🎬 SB: ' + (sbName || '#' + sbId));
        else if (nodeId)   parts.push('🌲 Node: ' + (nodeName || '#' + nodeId));
        return parts.length ? parts.join(' | ') : '📚 Browsing All Videos (Global)';
    }

    function applyFilter() {
        onlyFlagged      = document.getElementById('onlyFlaggedCb').checked;
        inclDesc         = document.getElementById('inclDescendantsCb').checked;
        filterNodeId     = pendingFilterNodeId;
        filterNodeName   = pendingFilterNodeName;
        filterSeqId      = pendingSeqId;
        filterSeqName    = pendingSeqName;
        filterFuzzCandId   = pendingFuzzCandId;
        filterFuzzCandName = pendingFuzzCandName;
        filterSbId         = pendingSbId;
        filterSbName       = pendingSbName;
        closeFilterModal();

        if (onlyFlagged || filterNodeId || filterSeqId || filterFuzzCandId || filterSbId) {
            isFilterMode = true;
            document.getElementById('btnFilter').classList.add('active-filter');
            document.getElementById('mapRunPanel').style.display = 'none';
            document.getElementById('filterActiveBanner').style.display = 'block';
            document.getElementById('filterBannerText').textContent =
                buildBannerText(onlyFlagged, filterNodeId, filterNodeName, filterSeqId, filterSeqName, filterFuzzCandId, filterFuzzCandName, filterSbId, filterSbName);
            document.getElementById('gridPagination').style.display = 'flex';
            loadFilteredVideos(1);
        } else {
            resetFilter();
        }
    }

    function browseAll() {
        onlyFlagged      = false;
        inclDesc         = true;
        filterNodeId     = null;
        filterNodeName   = '';
        filterSeqId      = null;
        filterSeqName    = '';
        filterFuzzCandId   = null;
        filterFuzzCandName = '';
        filterSbId         = null;
        filterSbName       = '';
        pendingFilterNodeId   = null;
        pendingFilterNodeName = '';
        pendingSeqId          = null;
        pendingSeqName        = '';
        pendingFuzzCandId     = null;
        pendingFuzzCandName   = '';
        pendingSbId           = null;
        pendingSbName         = '';

        document.getElementById('onlyFlaggedCb').checked = false;
        if (filterTreeInited) $('#filterTree').jstree('deselect_all');
        document.querySelectorAll('.rv-seq-item.selected').forEach(el => el.classList.remove('selected'));
        document.querySelectorAll('.rv-fuzz-item.selected').forEach(el => el.classList.remove('selected'));
        document.querySelectorAll('#sbFilterList .rv-seq-item.selected').forEach(el => el.classList.remove('selected'));
        updateFilterStrip();

        closeFilterModal();

        isFilterMode = true;
        document.getElementById('btnFilter').classList.add('active-filter');
        document.getElementById('mapRunPanel').style.display = 'none';
        document.getElementById('filterActiveBanner').style.display = 'block';
        document.getElementById('filterBannerText').textContent = '📚 Browsing All Videos (Global)';
        document.getElementById('gridPagination').style.display = 'flex';
        loadFilteredVideos(1);
    }

    function resetFilter() {
        onlyFlagged      = false;
        inclDesc         = true;
        filterNodeId     = null;
        filterNodeName   = '';
        filterSeqId      = null;
        filterSeqName    = '';
        filterFuzzCandId   = null;
        filterFuzzCandName = '';
        filterSbId         = null;
        filterSbName       = '';
        pendingFilterNodeId   = null;
        pendingFilterNodeName = '';
        pendingSeqId          = null;
        pendingSeqName        = '';
        pendingFuzzCandId     = null;
        pendingFuzzCandName   = '';
        pendingSbId           = null;
        pendingSbName         = '';
        isFilterMode = false;

        if (filterTreeInited) $('#filterTree').jstree('deselect_all');
        document.querySelectorAll('.rv-seq-item.selected').forEach(el => el.classList.remove('selected'));
        document.querySelectorAll('.rv-fuzz-item.selected').forEach(el => el.classList.remove('selected'));
        document.querySelectorAll('#sbFilterList .rv-seq-item.selected').forEach(el => el.classList.remove('selected'));

        document.getElementById('btnFilter').classList.remove('active-filter');
        document.getElementById('mapRunPanel').style.display = 'flex';
        document.getElementById('filterActiveBanner').style.display = 'none';
        document.getElementById('gridPagination').style.display = 'none';

        closeFilterModal();
        if (currentRunId) loadVideosForRun(currentRunId);
        else loadMapRuns(mrCurPage);
    }

    function loadFilteredVideos(page) {
        gridState.innerHTML = '<div class="rv-spinner"></div><span>Loading filtered…</span>';
        gridState.style.display = 'flex';
        gridEl.style.display = 'none';
        selectedVideoIds.clear();

        const p = new URLSearchParams({
            api_action: 'list_videos',
            page: page,
            limit: 24,
            only_review: onlyFlagged ? 1 : 0,
            include_descendants: inclDesc ? 1 : 0
        });
        if (filterFuzzCandId) {
            p.set('fuzz_cand_id', filterFuzzCandId);
        } else if (filterSeqId) {
            p.set('seq_id', filterSeqId);
        } else if (filterSbId) {
            p.set('storyboard_id', filterSbId);
        } else if (filterNodeId) {
            p.set('node_id', filterNodeId);
        }

        fetch('?' + p).then(r=>r.json()).then(res => {
            if (res.status !== 'ok') return;
            videos = res.videos ||[];
            gridPage = res.page;
            gridTotalPages = res.total_pages || 1;
            
            document.getElementById('gpInput').value = gridPage;
            document.getElementById('gpTotalPages').textContent = '/ ' + gridTotalPages;
            document.getElementById('gpPrev').disabled = (gridPage <= 1);
            document.getElementById('gpNext').disabled = (gridPage >= gridTotalPages);
            
            document.getElementById('selectedCount').textContent = `0 / ${videos.length} sel`;
            renderGrid();

            if (_afterLoad !== null) {
                const target = Math.min(_afterLoad, videos.length - 1);
                _afterLoad = null;
                if (videos.length > 0) playVideo(target, true);
            } else {
                if (videos.length > 0) playVideo(0, false);
                else {
                    curIndex = -1;
                    placeholder.style.display = 'flex'; player.style.display = 'none';
                    if (!player.paused) player.pause();
                    videoNameEl.textContent = '—'; videoIdEl.textContent = '—';
                    btnReview.disabled = true; btnAssign.disabled = true;
                    btnAnimatic.disabled = true;
                }
            }
        });
    }

    function changeGridPage(d) { const n = gridPage + d; if (n >= 1 && n <= gridTotalPages) loadFilteredVideos(n); }
    function jumpToGridPage() { const v = parseInt(document.getElementById('gpInput').value); if (v >= 1 && v <= gridTotalPages) loadFilteredVideos(v); }

    // ════════════════════════════════════
    // MAP RUN NAV (Default Mode)
    // ════════════════════════════════════
    function debounceSearch() { clearTimeout(mrDebounceTimer); mrDebounceTimer = setTimeout(() => loadMapRuns(1), 300); }
    function changeMapRunPage(d) { const n = mrCurPage + d; if (n >= 1 && n <= mrTotalPages) loadMapRuns(n); }
    function jumpToMapRunPage() { const v = parseInt(document.getElementById('mrPageInput').value); if (v >= 1 && v <= mrTotalPages) loadMapRuns(v); }

    function loadMapRuns(page) {
        if (isFilterMode) return;
        const list = document.getElementById('mrList');
        const search = document.getElementById('mrSearch').value.trim();
        
        fetch(`?api_action=get_map_runs&limit=3&offset=${(page-1)*3}&search=${encodeURIComponent(search)}`)
            .then(r => r.json()).then(res => {
                if(res.status !== 'ok') return;
                mrCurPage = page; mrTotalPages = Math.ceil(res.total/3) || 1;
                document.getElementById('mrPageInput').value = mrCurPage;
                document.getElementById('mrTotalPages').textContent = `/ ${mrTotalPages}`;
                
                list.innerHTML = '';
                if(!res.data.length) { list.innerHTML = '<div class="rv-state" style="padding:10px;">No runs found</div>'; return; }
                
                res.data.forEach(run => {
                    const el = document.createElement('div');
                    el.className = `mr-item ${run.id == currentRunId ? 'active' : ''}`;
                    el.onclick = () => selectRun(run.id, el);
                    el.innerHTML = `<div class="mr-id">#${run.id}</div><div class="mr-note">${escH(run.note)||'No note'}</div><div class="mr-meta">${run.item_count} vids</div>`;
                    list.appendChild(el);
                });

                if (!currentRunId && res.data.length > 0) selectRun(res.data[0].id, list.firstChild);
            });
    }

    function selectRun(runId, el) {
        document.querySelectorAll('.mr-item').forEach(i => i.classList.remove('active'));
        if (el) el.classList.add('active');
        currentRunId = runId;
        loadVideosForRun(runId);
    }

    function loadVideosForRun(runId) {
        gridState.innerHTML = '<div class="rv-spinner"></div><span>Loading videos…</span>';
        gridState.style.display = 'flex';
        gridEl.style.display    = 'none';
        selectedVideoIds.clear();

        fetch(`?api_action=get_videos&map_run_id=${runId}`).then(r => r.json()).then(data => {
            if (data.status !== 'ok') return;
            videos = data.videos ||[];
            document.getElementById('selectedCount').textContent = `0 / ${videos.length} sel`;
            renderGrid();
            if (videos.length > 0) playVideo(0, false);
            else {
                curIndex = -1;
                placeholder.style.display = 'flex'; player.style.display = 'none';
                if (!player.paused) player.pause();
                videoNameEl.textContent = '—'; videoIdEl.textContent = '—';
                btnReview.disabled = true; btnAssign.disabled = true; btnAnimatic.disabled = true;
            }
        });
    }

    // ════════════════════════════════════
    // GRID & BATCH SELECTION
    // ════════════════════════════════════
    function renderGrid() {
        gridState.style.display = 'none';
        gridEl.style.display    = 'grid';

        if (!videos.length) {
            gridEl.innerHTML = '<div style="color:var(--muted);padding:20px;font-size:0.75rem;grid-column:1/-1;">No videos found.</div>';
            return;
        }

        gridEl.innerHTML = videos.map((v, i) => `
            <div class="rv-card ${v.review==1?'flagged':''} ${i===curIndex?'active':''} ${selectedVideoIds.has(v.id)?'selected':''}"
                 data-index="${i}" data-id="${v.id}">
                <img src="${escH(v.thumbnail||'')}" loading="lazy" alt="${v.id}">
                <span class="rv-card-id">#${v.id}</span>
            </div>
        `).join('');

        gridEl.querySelectorAll('.rv-card').forEach(card => {
            card.addEventListener('click', () => handleCardClick(parseInt(card.dataset.index), card));
        });
    }

    function toggleSelectMode() { selectMode = document.getElementById('selectModeToggle').checked; }
    
    function selectAll() {
        if (!videos.length) return;
        videos.forEach(v => selectedVideoIds.add(v.id));
        updateGridSelection();
    }
    function selectNone() {
        selectedVideoIds.clear();
        updateGridSelection();
    }
    function updateGridSelection() {
        gridEl.querySelectorAll('.rv-card').forEach(card => {
            card.classList.toggle('selected', selectedVideoIds.has(parseInt(card.dataset.id)));
        });
        document.getElementById('selectedCount').textContent = `${selectedVideoIds.size} / ${videos.length} sel`;
    }

    function handleCardClick(index, card) {
        if (selectMode) {
            const id = videos[index].id;
            if (selectedVideoIds.has(id)) { selectedVideoIds.delete(id); card.classList.remove('selected'); }
            else { selectedVideoIds.add(id); card.classList.add('selected'); }
            document.getElementById('selectedCount').textContent = `${selectedVideoIds.size} / ${videos.length} sel`;
        } else {
            playVideo(index, true);
        }
    }

    // ════════════════════════════════════
    // PLAYER LOGIC
    // ════════════════════════════════════
    function playVideo(index, autoPlay) {
        if (index < 0 || index >= videos.length) return;
        curIndex = index;
        const v  = videos[index];

        placeholder.style.display = 'none';
        player.style.display      = 'block';
        player.src  = v.url;
        player.load();
        if (autoPlay) player.play().catch(() => {});

        updateInfoBar(v);
        highlightCard(index);
        updateNavButtons();
    }

    function updateInfoBar(v) {
        videoNameEl.textContent  = v.name || ('Video #' + v.id);
        videoIdEl.textContent    = '#' + v.id;
        videoDurEl.textContent   = fmtDur(v.duration);
        videoSizeEl.textContent  = fmtSize(v.file_size);

        const flagged = v.review == 1;
        flaggedBadge.style.display = flagged ? 'inline' : 'none';
        btnReview.textContent = flagged ? '✅' : '🏁';
        btnReview.classList.toggle('flagged', flagged);
        btnReview.disabled  = false;
        btnAssign.disabled  = false;
        btnAnimatic.disabled = false;
        btnAnimatic.dataset.animaticId = v.animatic_id || '';

        fetchAssignment(v.id);
    }

    function highlightCard(index) {
        gridEl.querySelectorAll('.rv-card').forEach((c, i) => { c.classList.toggle('active', i === index); });
        const card = gridEl.querySelector('.rv-card[data-index="' + index + '"]');
        if (card) card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    function updateNavButtons() {
        btnPrev.disabled = curIndex <= 0 && (!isFilterMode || gridPage <= 1);
        btnNext.disabled = curIndex >= videos.length - 1 && (!isFilterMode || gridPage >= gridTotalPages);
    }

    function navigate(dir) {
        const next = curIndex + dir;
        if (next >= 0 && next < videos.length) {
            playVideo(next, true);
        } else if (isFilterMode) {
            const targetPage = gridPage + dir;
            if (targetPage >= 1 && targetPage <= gridTotalPages) {
                _afterLoad = dir > 0 ? 0 : 9999;
                loadFilteredVideos(targetPage);
            }
        }
    }

    function toggleReview() {
        let targets =[];
        if (selectedVideoIds.size > 0) targets = Array.from(selectedVideoIds);
        else if (curIndex >= 0 && curIndex < videos.length) targets = [videos[curIndex].id];
        else return;
        
        const firstVid = videos.find(v => v.id === targets[0]);
        if (!firstVid) return;
        const newVal = firstVid.review == 1 ? 0 : 1;
        
        targets.forEach(id => {
            const v = videos.find(vid => vid.id === id);
            if (v) v.review = newVal;
            const card = gridEl.querySelector(`.rv-card[data-id="${id}"]`);
            if (card) card.classList.toggle('flagged', newVal === 1);
        });

        if (curIndex >= 0 && targets.includes(videos[curIndex].id)) updateInfoBar(videos[curIndex]);
        
        if (newVal === 1) {
            reviewFlash.className = 'rv-flash on';
            setTimeout(() => { reviewFlash.className = 'rv-flash off'; }, 80);
            setTimeout(() => { reviewFlash.className = 'rv-flash'; }, 600);
        }
        
        fetch('?api_action=toggle_review_batch', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ ids: targets, value: newVal }),
        }).then(r => r.json()).then(data => {
            if (data.status !== 'ok') if (typeof Toast !== 'undefined') Toast.show(data.message || 'Error', 'error');
        });
    }

    player.addEventListener('timeupdate', () => {
        if (player.duration) progressFill.style.width = (player.currentTime / player.duration * 100) + '%';
    });
    player.addEventListener('ended', () => { if (autoAdvanceCb.checked) navigate(1); });
    progressTrack.addEventListener('click', e => {
        if (!player.duration) return;
        const r = progressTrack.getBoundingClientRect();
        player.currentTime = ((e.clientX - r.left) / r.width) * player.duration;
    });

    document.addEventListener('keydown', e => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        switch (e.key) {
            case 'ArrowRight': e.preventDefault(); navigate(1); break;
            case 'ArrowLeft':  e.preventDefault(); navigate(-1); break;
            case 'r': case 'R': e.preventDefault(); toggleReview(); break;
            case 'a': case 'A': e.preventDefault(); openAssignModal(); break;
            case 'Escape':      closeAssignModal(); closeFilterModal(); closeRembgModal(); closeSamplerModal(); break;
            case ' ':
                e.preventDefault();
                player.paused ? player.play().catch(()=>{}) : player.pause();
                break;
        }
    });

    // ════════════════════════════════════
    // ASSIGN TREE MODAL
    // ════════════════════════════════════
    const assignedBadge   = document.getElementById('assignedBadge');
    const assignmentCache = {};

    function updateAssignBadge(assignment) {
        if (assignment && assignment.node_name) {
            assignedBadge.textContent = '⬡ ' + assignment.node_name;
            assignedBadge.style.display = 'inline';
            btnAssign.classList.add('assigned');
        } else {
            assignedBadge.style.display = 'none';
            btnAssign.classList.remove('assigned');
        }
    }

    function fetchAssignment(videoId) {
        if (videoId in assignmentCache) { updateAssignBadge(assignmentCache[videoId]); return; }
        fetch('?api_action=tree_get_assignment&video_id=' + videoId).then(r => r.json()).then(d => {
            if (d.status === 'ok') { assignmentCache[videoId] = d.assignment; updateAssignBadge(d.assignment); }
        });
    }

    let assignTreeInited = false;
    let assignNodeId     = null;
    let assignNodeName   = '';

    function openAssignModal() {
        if (selectedVideoIds.size > 0) {
            document.getElementById('assignCurrentText').innerHTML = `<span style="color:var(--accent);">Batch assigning ${selectedVideoIds.size} videos</span>`;
            document.getElementById('btnUnassign').style.display = 'inline-block';
        } else {
            if (curIndex < 0) return;
            const cached = assignmentCache[videos[curIndex].id];
            if (cached) {
                document.getElementById('assignCurrentText').innerHTML = 'Currently: <span class="node-name">' + escH(cached.node_name) + '</span>';
                document.getElementById('btnUnassign').style.display = 'inline-block';
            } else {
                document.getElementById('assignCurrentText').innerHTML = '<span style="color:var(--muted);">No assignment yet</span>';
                document.getElementById('btnUnassign').style.display = 'none';
            }
        }

        assignNodeId   = null;
        assignNodeName = '';
        document.getElementById('btnAssignConfirm').disabled = true;
        document.getElementById('assignModal').classList.add('active');

        if (!assignTreeInited) initAssignTree(); else $('#assignTree').jstree('refresh');
    }

    function closeAssignModal() { document.getElementById('assignModal').classList.remove('active'); }

    function initAssignTree() {
        assignTreeInited = true;
        $('#assignTree').jstree({
            core: {
                data: {
                    url: '?api_action=tree_fetch',
                    dataType: 'json',
                    dataFilter: function(raw) {
                        try { const j = JSON.parse(raw); return JSON.stringify(j.status === 'ok' ? j.tree :[]); } catch(e) { return '[]'; }
                    }
                },
                themes: { name: 'default', dots: true, icons: true },
                check_callback: false,
            },
            plugins:['types', 'state'],
            types: {
                folder:   { icon: 'bi bi-folder2' },
                episode:  { icon: 'bi bi-film' },
                sequence: { icon: 'bi bi-collection-play' },
                scene:    { icon: 'bi bi-camera-video' },
                other:    { icon: 'bi bi-tag' },
            },
        }).on('select_node.jstree', function(e, data) {
            assignNodeId   = data.node.data.db_id;
            assignNodeName = data.node.text;
            document.getElementById('btnAssignConfirm').disabled = false;
        }).on('deselect_node.jstree', function() {
            assignNodeId   = null;
            document.getElementById('btnAssignConfirm').disabled = true;
        });
    }

    function createTreeNode() {
        const name     = document.getElementById('newNodeName').value.trim();
        const nodeType = document.getElementById('newNodeType').value;
        if (!name) return;

        const sel  = $('#assignTree').jstree('get_selected', true);
        const parentId = sel.length ? sel[0].data.db_id : null;

        fetch('?api_action=tree_create_node', {
            method:  'POST', headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ name, node_type: nodeType, parent_id: parentId }),
        }).then(r => r.json()).then(d => {
            if (d.status === 'ok') {
                document.getElementById('newNodeName').value = '';
                $('#assignTree').jstree('refresh');
                if (filterTreeInited) $('#filterTree').jstree('refresh');
                if (typeof Toast !== 'undefined') Toast.show('Node created', 'success');
            } else { if (typeof Toast !== 'undefined') Toast.show(d.message || 'Error', 'error'); }
        });
    }

    function confirmAssign() {
        if (!assignNodeId) return;

        let targets =[];
        if (selectedVideoIds.size > 0) targets = Array.from(selectedVideoIds);
        else if (curIndex >= 0 && curIndex < videos.length) targets = [videos[curIndex].id];
        else return;

        document.getElementById('btnAssignConfirm').disabled = true;
        document.getElementById('btnAssignConfirm').textContent = 'Assigning…';

        fetch('?api_action=tree_assign_batch', {
            method:  'POST', headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ node_id: assignNodeId, video_ids: targets }),
        }).then(r => r.json()).then(d => {
            if (d.status === 'ok') {
                targets.forEach(id => { assignmentCache[id] = { node_id: assignNodeId, node_name: assignNodeName }; });
                if (curIndex >= 0 && targets.includes(videos[curIndex].id)) updateAssignBadge(assignmentCache[videos[curIndex].id]);
                closeAssignModal();
                if (typeof Toast !== 'undefined') Toast.show('Assigned to: ' + assignNodeName, 'success');
            } else { if (typeof Toast !== 'undefined') Toast.show(d.message || 'Error', 'error'); }
        }).finally(() => {
            document.getElementById('btnAssignConfirm').disabled = false;
            document.getElementById('btnAssignConfirm').textContent = 'Assign to Selected Node';
        });
    }

    function unassignVideo() {
        let targets =[];
        if (selectedVideoIds.size > 0) targets = Array.from(selectedVideoIds);
        else if (curIndex >= 0 && curIndex < videos.length) targets = [videos[curIndex].id];
        else return;

        fetch('?api_action=tree_unassign_batch', {
            method:  'POST', headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ video_ids: targets }),
        }).then(r => r.json()).then(d => {
            if (d.status === 'ok') {
                targets.forEach(id => { assignmentCache[id] = null; });
                if (curIndex >= 0 && targets.includes(videos[curIndex].id)) {
                    updateAssignBadge(null);
                    document.getElementById('btnUnassign').style.display = 'none';
                    document.getElementById('assignCurrentText').innerHTML = '<span style="color:var(--muted);">No assignment yet</span>';
                }
                if (typeof Toast !== 'undefined') Toast.show('Unassigned', 'success');
            }
        });
    }

    // Expose globals
    window.navigate           = navigate;
    window.toggleReview       = toggleReview;
    window.openVideoDetail    = openVideoDetail;
    window.debounceSearch     = debounceSearch;
    window.changeMapRunPage   = changeMapRunPage;
    window.jumpToMapRunPage   = jumpToMapRunPage;
    window.toggleSelectMode   = toggleSelectMode;
    window.selectAll          = selectAll;
    window.selectNone         = selectNone;
    window.openAssignModal    = openAssignModal;
    window.closeAssignModal   = closeAssignModal;
    window.createTreeNode     = createTreeNode;
    window.confirmAssign      = confirmAssign;
    window.unassignVideo      = unassignVideo;
    window.openFilterModal    = openFilterModal;
    window.closeFilterModal   = closeFilterModal;
    window.applyFilter        = applyFilter;
    window.browseAll          = browseAll;
    window.resetFilter        = resetFilter;
    window.changeGridPage     = changeGridPage;
    window.jumpToGridPage     = jumpToGridPage;

    // Boot
    loadMapRuns(1);

})();
</script>

<?php
$content = ob_get_clean();
// modal_frame_details provides showVideoDetailsModal (and showEntityFormInModal used elsewhere)
ob_start();
include __DIR__ . '/modal_frame_details.php';
$content .= ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>
