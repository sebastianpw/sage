<?php
// public/view_video_review.php
// Fast Video Review — mobile-first, Android Chrome
// SQL:
//   ALTER TABLE videos ADD COLUMN `review` TINYINT(1) NOT NULL DEFAULT 0;
//
//   CREATE TABLE video_tree_nodes (
//     id          INT AUTO_INCREMENT PRIMARY KEY,
//     parent_id   INT DEFAULT NULL,
//     name        VARCHAR(255) NOT NULL,
//     node_type   ENUM('folder','episode','sequence','scene','other') DEFAULT 'folder',
//     description TEXT DEFAULT NULL,
//     sort_order  INT DEFAULT 0,
//     created_at  TIMESTAMP DEFAULT current_timestamp(),
//     KEY idx_parent (parent_id)
//   );
//
//   CREATE TABLE video_tree_items (
//     id         INT AUTO_INCREMENT PRIMARY KEY,
//     node_id    INT NOT NULL,
//     video_id   INT NOT NULL,
//     note       TEXT DEFAULT NULL,
//     sort_order INT DEFAULT 0,
//     created_at TIMESTAMP DEFAULT current_timestamp(),
//     UNIQUE KEY uq_node_video (node_id, video_id),
//     KEY idx_node  (node_id),
//     KEY idx_video (video_id)
//   );
require_once __DIR__ . '/bootstrap.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();
$pageTitle = "Video Review";

// ═══════════════════════════════════════════════════════
// INLINE API HANDLER
// ═══════════════════════════════════════════════════════
if (isset($_REQUEST['api_action'])) {
    header('Content-Type: application/json');
    $action = $_REQUEST['api_action'];
    try {

        if ($action === 'list_videos') {
            $page        = max(1, (int)($_GET['page']  ?? 1));
            $limit       = max(1, min(200, (int)($_GET['limit'] ?? 9)));
            $offset      = ($page - 1) * $limit;
            $onlyReview  = (int)($_GET['only_review'] ?? 0);
            $nodeId      = (int)($_GET['node_id'] ?? 0);
            $inclDesc    = (int)($_GET['include_descendants'] ?? 1);
            $seqId       = (int)($_GET['seq_id'] ?? 0);
            $fuzzCandId  = (int)($_GET['fuzz_cand_id'] ?? 0);

            $whereParts = [];
            $params     = [];

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

            } elseif ($nodeId) {
                if ($inclDesc) {
                    // Recursive CTE — all descendants + the node itself
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
                    // Shallow — exact node only
                    $whereParts[] = 'v.id IN (SELECT vti.video_id FROM video_tree_items vti WHERE vti.node_id = ?)';
                    $params[] = $nodeId;
                }
            }

            $where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM videos v $where");
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();

            $dataStmt = $pdo->prepare(
                "SELECT v.id, v.name, v.thumbnail, v.url, v.duration, v.file_size, v.`review`,
                        va.to_id as animatic_id
                 FROM videos v
                 LEFT JOIN videos_2_animatics va ON va.from_id = v.id
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

        if ($action === 'toggle_review') {
            $input = json_decode(file_get_contents('php://input'), true);
            $id    = (int)($input['id']    ?? 0);
            $val   = (int)($input['value'] ?? 0);
            if (!$id) throw new Exception('Missing id');
            $stmt  = $pdo->prepare("UPDATE videos SET `review` = ? WHERE id = ?");
            $stmt->execute([$val, $id]);
            echo json_encode(['status' => 'ok', 'id' => $id, 'review' => $val]);
            exit;
        }

        if ($action === 'flagged_count') {
            $count = $pdo->query("SELECT COUNT(*) FROM videos WHERE `review` = 1")->fetchColumn();
            echo json_encode(['status' => 'ok', 'count' => (int)$count]);
            exit;
        }

        // ── Narrative sequences list ──
        if ($action === 'list_narrative_sequences') {
            $stmt = $pdo->query("SELECT id, name, description FROM narrative_sequences ORDER BY id DESC");
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'ok', 'sequences' => $rows]);
            exit;
        }

        // ── Fuzz candidates list ──
        if ($action === 'list_fuzz_candidates') {
            $search = trim($_GET['search'] ?? '');
            $params = [];
            $whereSQL = "WHERE status NOT IN ('rejected','promoted','canonized')";
            if ($search !== '') {
                $whereSQL .= " AND label LIKE ?";
                $params[] = '%' . $search . '%';
            }
            $stmt = $pdo->prepare(
                "SELECT c.id, c.label, c.concept_type, c.status,
                        (SELECT COUNT(*) FROM fuzz_mentions m WHERE m.candidate_id = c.id) as mention_count
                 FROM fuzz_candidates c
                 $whereSQL
                 ORDER BY mention_count DESC, c.updated_at DESC
                 LIMIT 200"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['status' => 'ok', 'candidates' => $rows]);
            exit;
        }

        // ── Video Tree: fetch full tree for jsTree ──
        if ($action === 'tree_fetch') {
            $rows = $pdo->query(
                "SELECT id, parent_id, name, node_type FROM video_tree_nodes ORDER BY sort_order ASC, name ASC"
            )->fetchAll(PDO::FETCH_ASSOC);

            $nodes = [];
            foreach ($rows as $r) {
                $icon = match($r['node_type']) {
                    'episode'  => 'bi bi-film',
                    'sequence' => 'bi bi-collection-play',
                    'scene'    => 'bi bi-camera-video',
                    'other'    => 'bi bi-tag',
                    default    => 'bi bi-folder2',
                };
                $nodes[] = [
                    'id'     => 'n_' . $r['id'],
                    'parent' => $r['parent_id'] ? 'n_' . $r['parent_id'] : '#',
                    'text'   => $r['name'],
                    'icon'   => $icon,
                    'type'   => $r['node_type'],
                    'data'   => ['db_id' => (int)$r['id'], 'node_type' => $r['node_type']],
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
            $nodeType  = in_array($input['node_type'] ?? '', ['folder','episode','sequence','scene','other'])
                         ? $input['node_type'] : 'folder';
            if (!$name) throw new Exception('Name required');
            $stmt = $pdo->prepare(
                "INSERT INTO video_tree_nodes (parent_id, name, node_type) VALUES (?, ?, ?)"
            );
            $stmt->execute([$parentId, $name, $nodeType]);
            echo json_encode(['status' => 'ok', 'id' => (int)$pdo->lastInsertId(), 'name' => $name]);
            exit;
        }

        // ── Video Tree: assign video to node ──
        if ($action === 'tree_assign') {
            $input   = json_decode(file_get_contents('php://input'), true);
            $nodeId  = (int)($input['node_id']  ?? 0);
            $videoId = (int)($input['video_id'] ?? 0);
            if (!$nodeId || !$videoId) throw new Exception('Missing node_id or video_id');
            $stmt = $pdo->prepare(
                "INSERT IGNORE INTO video_tree_items (node_id, video_id) VALUES (?, ?)"
            );
            $stmt->execute([$nodeId, $videoId]);
            echo json_encode(['status' => 'ok', 'node_id' => $nodeId, 'video_id' => $videoId]);
            exit;
        }

        // ── Video Tree: unassign video from node ──
        if ($action === 'tree_unassign') {
            $input   = json_decode(file_get_contents('php://input'), true);
            $videoId = (int)($input['video_id'] ?? 0);
            if (!$videoId) throw new Exception('Missing video_id');
            $stmt = $pdo->prepare("DELETE FROM video_tree_items WHERE video_id = ?");
            $stmt->execute([$videoId]);
            echo json_encode(['status' => 'ok']);
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
    --muted:     #4a4a6a;
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
    min-height: 100dvh;
}

/* ════════════════════════════════════
   LAYOUT WRAPPER
════════════════════════════════════ */
.rv-layout {
    display: flex;
    flex-direction: column;
}

@media (min-width: 900px) {
    .rv-layout {
        flex-direction: row;
        align-items: flex-start;
        min-height: 100dvh;
    }
    .rv-left-col {
        width: 400px;
        flex-shrink: 0;
        position: sticky;
        top: 0;
        max-height: 100dvh;
        overflow-y: auto;
        border-right: 1px solid var(--border);
    }
    .rv-right-col {
        flex: 1;
        min-width: 0;
    }
}

/* ════════════════════════════════════
   PLAYER
════════════════════════════════════ */
.rv-player-wrap {
    position: relative;
    background: #000;
    width: 100%;
}
.rv-player-wrap video {
    width: 100%;
    aspect-ratio: 4/3;
    display: block;
    background: #000;
}
.rv-player-placeholder {
    aspect-ratio: 4/3;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #000;
    color: var(--muted);
    font-size: 0.7rem;
    letter-spacing: 2px;
    text-transform: uppercase;
}

/* Green flash on flag */
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
}
.rv-video-name {
    font-size: 0.78rem;
    font-weight: 700;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 3px;
}
.rv-meta-row {
    display: flex;
    gap: 10px;
    font-size: 0.65rem;
    color: var(--muted);
    flex-wrap: wrap;
}
.rv-flagged-badge { color: var(--green); font-weight: 700; }
.rv-assigned-badge {
    color: var(--accent);
    font-weight: 700;
    font-size: 0.65rem;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.rv-progress-track {
    height: 4px;
    background: var(--border);
    cursor: pointer;
    margin-top: 7px;
    border-radius: 2px;
    overflow: hidden;
}
.rv-progress-fill {
    height: 100%;
    background: var(--accent);
    width: 0%;
    pointer-events: none;
    transition: width 0.15s linear;
}

/* ════════════════════════════════════
   ACTION BUTTONS
════════════════════════════════════ */
.rv-actions {
    display: grid;
    grid-template-columns: 1.2fr 0.8fr 0.8fr 0.8fr 1.2fr;
    gap: 4px;
    padding: 6px 8px;
    background: var(--card);
    border-bottom: 1px solid var(--border);
}
.rv-btn {
    min-height: var(--tap);
    border-radius: 4px;
    border: 1px solid var(--border);
    background: transparent;
    color: var(--muted);
    font-family: inherit;
    font-size: 0.7rem;
    font-weight: 700;
    letter-spacing: 1px;
    text-transform: uppercase;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    transition: background 0.1s, border-color 0.1s, color 0.1s;
    -webkit-tap-highlight-color: transparent;
    user-select: none;
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

/* Animatic — amber/gold, distinctive */
.rv-btn-animatic              { border-color: var(--yellow); color: var(--yellow); font-size: 1rem; }
.rv-btn-animatic:active,
.rv-btn-animatic:hover        { background: rgba(255,209,102,0.12); }
.rv-btn-animatic:disabled     { opacity: 0.2; pointer-events: none; }

/* ════════════════════════════════════
   STATS STRIP
════════════════════════════════════ */
.rv-stats {
    display: flex;
    background: rgba(0,0,0,0.3);
    border-bottom: 1px solid var(--border);
}
.rv-stat {
    flex: 1;
    text-align: center;
    padding: 5px 4px;
    font-size: 0.55rem;
    color: var(--muted);
    border-right: 1px solid var(--border);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.rv-stat:last-child { border-right: none; }
.rv-stat-val {
    display: block;
    font-size: 0.85rem;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 1px;
}
.rv-stat-val.green { color: var(--green); }

/* ════════════════════════════════════
   PAGINATION BAR
════════════════════════════════════ */
.rv-pg-bar {
    position: sticky;
    top: 0;
    z-index: 50;
    background: var(--card);
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
}
.rv-pg-btn {
    min-width: var(--tap);
    min-height: 38px;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--muted);
    border-radius: 4px;
    cursor: pointer;
    font-size: 1rem;
    display: flex;
    align-items: center;
    justify-content: center;
    -webkit-tap-highlight-color: transparent;
    transition: border-color 0.1s, color 0.1s;
}
.rv-pg-btn:active { border-color: var(--accent); color: var(--accent); }
.rv-pg-btn:disabled { opacity: 0.3; pointer-events: none; }

.rv-pg-input {
    width: 50px;
    text-align: center;
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--accent);
    border-radius: 4px;
    font-family: inherit;
    font-size: 0.85rem;
    font-weight: 700;
    padding: 6px 2px;
    height: 38px;
    -moz-appearance: textfield;
}
.rv-pg-input::-webkit-outer-spin-button,
.rv-pg-input::-webkit-inner-spin-button { -webkit-appearance: none; }
.rv-pg-input:focus { outline: none; border-color: var(--accent); }

.rv-pg-of    { font-size: 0.65rem; color: var(--muted); white-space: nowrap; }
.rv-pg-count { font-size: 0.65rem; color: var(--muted); margin-left: auto; white-space: nowrap; }

.rv-auto-label {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 0.6rem;
    color: var(--muted);
    cursor: pointer;
    white-space: nowrap;
    -webkit-tap-highlight-color: transparent;
}
.rv-auto-label input { accent-color: var(--accent); width: 15px; height: 15px; }

/* ════════════════════════════════════
   VIDEO GRID
════════════════════════════════════ */
.rv-grid-section {
    padding: 6px;
    padding-bottom: calc(12px + env(safe-area-inset-bottom));
}

.rv-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 4px;
}

@media (min-width: 500px)  { .rv-grid { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 700px)  { .rv-grid { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 900px)  { .rv-grid { grid-template-columns: repeat(3, 1fr); } }
@media (min-width: 1100px) { .rv-grid { grid-template-columns: repeat(3, 1fr); } }

.rv-card {
    position: relative;
    aspect-ratio: 16/9;
    background: #111;
    border-radius: 3px;
    overflow: hidden;
    cursor: pointer;
    border: 2px solid transparent;
    -webkit-tap-highlight-color: transparent;
    transition: border-color 0.1s;
}
.rv-card.active  { border-color: var(--accent); }
.rv-card.flagged { border-color: var(--green); }
.rv-card.flagged::after {
    content: '★';
    position: absolute;
    top: 2px; right: 3px;
    font-size: 9px;
    color: var(--green);
    text-shadow: 0 0 5px rgba(0,229,160,0.9);
    pointer-events: none;
    z-index: 3;
}
.rv-card img {
    width: 100%; height: 100%;
    object-fit: cover;
    display: block;
}
.rv-card-id {
    position: absolute;
    bottom: 2px; left: 3px;
    font-size: 0.5rem;
    color: rgba(255,255,255,0.45);
    pointer-events: none;
}

/* ════════════════════════════════════
   STATE MESSAGES
════════════════════════════════════ */
.rv-state {
    padding: 40px 20px;
    text-align: center;
    color: var(--muted);
    font-size: 0.75rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
}
.rv-spinner {
    width: 20px; height: 20px;
    border: 2px solid var(--border);
    border-top-color: var(--accent);
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* ════════════════════════════════════
   MODALS (shared base)
════════════════════════════════════ */
.rv-modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.75);
    z-index: 200;
    display: none;
    align-items: flex-end;
    justify-content: center;
    padding: 0;
}
.rv-modal-overlay.active { display: flex; }

@media (min-width: 600px) {
    .rv-modal-overlay { align-items: center; padding: 20px; }
}

.rv-modal-sheet {
    width: 100%;
    max-width: 480px;
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: 12px 12px 0 0;
    display: flex;
    flex-direction: column;
    max-height: 85dvh;
    overflow: hidden;
}
@media (min-width: 600px) {
    .rv-modal-sheet { border-radius: 10px; max-height: 80dvh; }
}

.rv-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 14px 10px;
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.rv-modal-title { font-size: 0.85rem; font-weight: 700; color: var(--text); letter-spacing: 1px; text-transform: uppercase; }
.rv-modal-close {
    width: 32px; height: 32px;
    background: transparent;
    border: 1px solid var(--border);
    color: var(--muted);
    border-radius: 4px;
    cursor: pointer;
    font-size: 1rem;
    display: flex; align-items: center; justify-content: center;
    -webkit-tap-highlight-color: transparent;
}
.rv-modal-close:active { color: var(--danger); border-color: var(--danger); }

/* Current assignment / active filter strip */
.rv-assign-current {
    padding: 8px 14px;
    font-size: 0.7rem;
    color: var(--muted);
    border-bottom: 1px solid var(--border);
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    min-height: 36px;
}
.rv-assign-current .node-name { color: var(--green); font-weight: 700; }
.rv-unassign-btn {
    padding: 3px 8px;
    border: 1px solid var(--danger);
    background: transparent;
    color: var(--danger);
    border-radius: 3px;
    font-size: 0.6rem;
    font-family: inherit;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    white-space: nowrap;
}

/* New node toolbar */
.rv-tree-toolbar {
    padding: 6px 10px;
    border-bottom: 1px solid var(--border);
    display: flex;
    gap: 6px;
    flex-shrink: 0;
    flex-wrap: wrap;
}
.rv-tree-toolbar input {
    flex: 1;
    min-width: 100px;
    padding: 5px 8px;
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: 4px;
    font-family: inherit;
    font-size: 0.75rem;
}
.rv-tree-toolbar input:focus { outline: none; border-color: var(--accent); }
.rv-tree-toolbar select {
    padding: 5px 6px;
    background: var(--bg);
    border: 1px solid var(--border);
    color: var(--text);
    border-radius: 4px;
    font-family: inherit;
    font-size: 0.7rem;
}
.rv-tree-add-btn {
    padding: 5px 12px;
    background: var(--accent);
    border: none;
    color: #fff;
    border-radius: 4px;
    font-family: inherit;
    font-size: 0.7rem;
    font-weight: 700;
    cursor: pointer;
    white-space: nowrap;
    -webkit-tap-highlight-color: transparent;
}

/* jsTree container */
.rv-tree-scroll {
    flex: 1;
    overflow-y: auto;
    padding: 8px 6px;
    background: var(--bg);
}
.rv-tree-scroll::-webkit-scrollbar { width: 3px; }
.rv-tree-scroll::-webkit-scrollbar-thumb { background: var(--border); }

/* jsTree dark theme overrides */
.jstree-default .jstree-anchor { color: var(--text) !important; line-height: 28px; height: 28px; }
.jstree-default .jstree-hovered { background: rgba(108,99,255,0.12) !important; border-radius: 4px; }
.jstree-default .jstree-clicked { background: rgba(108,99,255,0.25) !important; color: var(--accent) !important; border-radius: 4px; }
.jstree-default .jstree-icon { color: var(--muted); }
.jstree-default { background: transparent !important; color: var(--text); }
.jstree-container-ul { background: transparent !important; }

/* Modal footer with action button */
.rv-modal-footer {
    padding: 10px 14px;
    border-top: 1px solid var(--border);
    flex-shrink: 0;
    display: flex;
    gap: 8px;
}
.rv-assign-confirm-btn {
    flex: 1;
    min-height: var(--tap);
    background: var(--green);
    border: none;
    color: #000;
    font-family: inherit;
    font-size: 0.8rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 1px;
    border-radius: 4px;
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    transition: opacity 0.1s;
}
.rv-assign-confirm-btn:disabled { opacity: 0.35; cursor: not-allowed; }
.rv-assign-confirm-btn:active   { opacity: 0.8; }

/* Gear stat cell */
.rv-stat-gear {
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    transition: color 0.15s;
}
.rv-stat-gear:active { opacity: 0.7; }
.rv-gear-icon {
    display: block;
    font-size: 1rem;
    font-weight: 700;
    color: var(--muted);
    margin-bottom: 1px;
    transition: color 0.15s;
}
.rv-gear-label {
    font-size: 0.55rem;
    color: var(--muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
/* Active filter state — gear glows */
.rv-stat-gear.filter-active .rv-gear-icon  { color: var(--accent); }
.rv-stat-gear.filter-active .rv-gear-label { color: var(--accent); }

/* Filter modal checkboxes */
.rv-filter-check-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.72rem;
    color: var(--text);
    cursor: pointer;
    -webkit-tap-highlight-color: transparent;
    user-select: none;
}
.rv-filter-check-label input {
    accent-color: var(--accent);
    width: 16px;
    height: 16px;
}

/* ════════════════════════════════════
   FILTER MODAL — TAB BAR
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
</style>

<!-- ══════════════════════ HTML ══════════════════════ -->
<div class="rv-layout">

    <!-- LEFT COL / TOP on mobile: Player + Controls -->
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
            <button class="rv-btn rv-btn-nav"      id="btnPrev"     disabled onclick="navigate(-1)">◀ Prev</button>
            <button class="rv-btn rv-btn-review"   id="btnReview"   disabled onclick="toggleReview()" title="Flag for review">🏁</button>
            <button class="rv-btn rv-btn-animatic" id="btnAnimatic" disabled onclick="openAnimatic()" title="Open animatic">🎬</button>
            <button class="rv-btn rv-btn-assign"   id="btnAssign"   disabled onclick="openAssignModal()" title="Assign to story node">⬡</button>
            <button class="rv-btn rv-btn-nav"      id="btnNext"     disabled onclick="navigate(1)">Next ▶</button>
        </div>

        <div class="rv-stats">
            <div class="rv-stat"><span class="rv-stat-val" id="statPos">—</span>pos</div>
            <div class="rv-stat"><span class="rv-stat-val" id="statTotal">—</span>total</div>
            <div class="rv-stat"><span class="rv-stat-val green" id="statFlagged">—</span>flagged</div>
            <div class="rv-stat"><span class="rv-stat-val" id="statPage">—</span>page</div>
            <div class="rv-stat rv-stat-gear" id="gearStatCell" onclick="openFilterModal()">
                <span class="rv-gear-icon" id="gearIcon">⚙</span>
                <span class="rv-gear-label" id="gearLabel">filter</span>
            </div>
        </div>

    </div><!-- /.rv-left-col -->

    <!-- RIGHT COL / BOTTOM on mobile: Pagination + Grid -->
    <div class="rv-right-col">

        <div class="rv-pg-bar">
            <button class="rv-pg-btn" id="pgPrev">‹</button>
            <input  type="number" class="rv-pg-input" id="pgInput" value="1"
                    onkeydown="if(event.key==='Enter'){window._rvLoadPage(parseInt(this.value));this.blur();}">
            <span class="rv-pg-of" id="pgTotal">/ 1</span>
            <button class="rv-pg-btn" id="pgNext">›</button>
            <span class="rv-pg-count" id="pgCount"></span>
            <label class="rv-auto-label">
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

        <!-- Current assignment -->
        <div class="rv-assign-current" id="assignCurrentStrip">
            <span id="assignCurrentText" style="color:var(--muted);">No assignment</span>
            <button class="rv-unassign-btn" id="btnUnassign" style="display:none;" onclick="unassignVideo()">Unassign</button>
        </div>

        <!-- New node toolbar -->
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

        <!-- jsTree -->
        <div class="rv-tree-scroll">
            <div id="assignTree">Loading…</div>
        </div>

        <!-- Footer -->
        <div class="rv-modal-footer">
            <button class="rv-assign-confirm-btn" id="btnAssignConfirm" disabled onclick="confirmAssign()">
                Assign to Selected Node
            </button>
        </div>

    </div>
</div>

<!-- jsTree deps (jQuery + plugin) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>

<!-- ══ FILTER MODAL ══ -->
<div class="rv-modal-overlay" id="filterModal">
    <div class="rv-modal-sheet">

        <div class="rv-modal-header">
            <span class="rv-modal-title">⚙ Filter / Focus</span>
            <button class="rv-modal-close" onclick="closeFilterModal()">✕</button>
        </div>

        <!-- Active filter chip -->
        <div class="rv-assign-current" id="filterActiveStrip">
            <span id="filterActiveText" style="color:var(--muted);">No filter active</span>
            <button class="rv-unassign-btn" id="btnResetFilter" style="display:none;" onclick="resetFilter()">Reset</button>
        </div>

        <!-- Checkboxes row -->
        <div style="display:flex; gap:16px; padding:8px 14px; border-bottom:1px solid var(--border); flex-shrink:0;">
            <label class="rv-filter-check-label">
                <input type="checkbox" id="onlyFlagged">
                <span>Flagged only</span>
            </label>
            <label class="rv-filter-check-label">
                <input type="checkbox" id="inclDescendants" checked>
                <span>Include descendants</span>
            </label>
        </div>

        <!-- Tab bar -->
        <div class="rv-filter-tabs">
            <div class="rv-filter-tab active" data-tab="tree"       onclick="switchFilterTab('tree')">🌲 Tree</div>
            <div class="rv-filter-tab"        data-tab="narratives" onclick="switchFilterTab('narratives')">📖 Narratives</div>
            <div class="rv-filter-tab"        data-tab="fuzz"       onclick="switchFilterTab('fuzz')">🔮 Fuzz</div>
        </div>

        <!-- ── Tab: Story Tree ── -->
        <div class="rv-filter-tab-panel active" id="filterTabTree">
            <!-- New node toolbar -->
            <div class="rv-tree-toolbar">
                <input type="text" id="filterNewNodeName" placeholder="New node name…">
                <select id="filterNewNodeType">
                    <option value="folder">Folder</option>
                    <option value="episode">Episode</option>
                    <option value="sequence">Sequence</option>
                    <option value="scene">Scene</option>
                    <option value="other">Other</option>
                </select>
                <button class="rv-tree-add-btn" onclick="createFilterTreeNode()">+ Add</button>
            </div>
            <div class="rv-tree-scroll">
                <div id="filterTree">Loading…</div>
            </div>
        </div>

        <!-- ── Tab: Narrative Sequences ── -->
        <div class="rv-filter-tab-panel" id="filterTabNarratives">
            <div class="rv-seq-list" id="seqList">
                <div style="color:var(--muted);font-size:0.7rem;padding:12px;text-align:center;">Loading sequences…</div>
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
        </div>

        <!-- Footer -->
        <div class="rv-modal-footer">
            <button class="rv-assign-confirm-btn" id="btnApplyFilter" onclick="applyFilter()"
                    style="background:var(--accent); color:#fff;">
                Apply Filter
            </button>
        </div>

    </div>
</div>

<script>
(function () {
    'use strict';

    const PER_PAGE = 9;

    // ── State ──
    let curPage      = 1;
    let totalPages   = 1;
    let totalVideos  = 0;
    let videos       = [];
    let curIndex     = -1;
    let flaggedTotal = 0;
    let _afterLoad   = null;
    let loading      = false;

    // ── Filter state ──
    let onlyFlagged    = false;
    let filterNodeId   = null;
    let filterNodeName = '';
    let inclDesc       = true;
    let filterSeqId    = null;
    let filterSeqName  = '';
    let filterFuzzCandId   = null;
    let filterFuzzCandName = '';

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
    const progressFill  = document.getElementById('progressFill');
    const progressTrack = document.getElementById('progressTrack');
    const gridEl        = document.getElementById('videoGrid');
    const gridState     = document.getElementById('gridState');
    const pgInput       = document.getElementById('pgInput');
    const pgTotalEl     = document.getElementById('pgTotal');
    const pgPrevBtn     = document.getElementById('pgPrev');
    const pgNextBtn     = document.getElementById('pgNext');
    const pgCountEl     = document.getElementById('pgCount');
    const statPos       = document.getElementById('statPos');
    const statTotal     = document.getElementById('statTotal');
    const statFlagged   = document.getElementById('statFlagged');
    const statPageEl    = document.getElementById('statPage');
    const autoAdvanceCb   = document.getElementById('autoAdvance');
    // Filter modal elements
    const onlyFlaggedCb   = document.getElementById('onlyFlagged');
    const inclDescCb      = document.getElementById('inclDescendants');
    const gearStatCell    = document.getElementById('gearStatCell');
    const filterActiveText = document.getElementById('filterActiveText');
    const btnResetFilter  = document.getElementById('btnResetFilter');

    // ── Helpers ──
    function fmtDur(s) {
        if (!s) return '0:00';
        const m = Math.floor(s / 60), sc = Math.floor(s % 60);
        return `${m}:${sc.toString().padStart(2,'0')}`;
    }
    function fmtSize(b) { return b ? (b/1024/1024).toFixed(1)+' MB' : ''; }
    function clamp(v, lo, hi) { return Math.max(lo, Math.min(hi, v)); }
    function escH(s) {
        return String(s||'')
            .replace(/&/g,'&amp;').replace(/"/g,'&quot;')
            .replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // ── Load page ──
    function loadPage(page) {
        page = parseInt(page) || 1;
        if (page < 1) page = 1;
        if (totalPages > 1 && page > totalPages) page = totalPages;
        if (loading) return;
        loading  = true;
        curPage  = page;
        pgInput.value = page;

        gridState.innerHTML = '<div class="rv-spinner"></div><span>Loading…</span>';
        gridState.style.display = 'flex';
        gridEl.style.display    = 'none';

        const p = new URLSearchParams({
            api_action:          'list_videos',
            page:                page,
            limit:               PER_PAGE,
            only_review:         onlyFlagged ? 1 : 0,
            include_descendants: inclDesc ? 1 : 0,
        });
        if (filterFuzzCandId) {
            p.set('fuzz_cand_id', filterFuzzCandId);
        } else if (filterSeqId) {
            p.set('seq_id', filterSeqId);
        } else if (filterNodeId) {
            p.set('node_id', filterNodeId);
        }

        fetch('?' + p)
            .then(r => r.json())
            .then(data => {
                if (data.status !== 'ok') throw new Error(data.message || 'API error');

                videos      = data.videos;
                totalVideos = data.total;
                totalPages  = data.total_pages || 1;

                pgTotalEl.textContent = '/ ' + totalPages;
                pgInput.value         = curPage;
                pgPrevBtn.disabled    = curPage <= 1;
                pgNextBtn.disabled    = curPage >= totalPages;
                pgCountEl.textContent = totalVideos + ' videos';
                statTotal.textContent = totalVideos;
                statPageEl.textContent = curPage + '/' + totalPages;

                renderGrid();
                loading = false;

                if (_afterLoad !== null) {
                    const idx = clamp(_afterLoad, 0, videos.length - 1);
                    _afterLoad = null;
                    playVideo(idx, true);
                }
            })
            .catch(err => {
                gridState.innerHTML = '<span style="color:var(--danger);">Error: ' + escH(err.message) + '</span>';
                gridState.style.display = 'flex';
                loading = false;
            });
    }

    // ── Render grid ──
    function renderGrid() {
        gridState.style.display = 'none';
        gridEl.style.display    = 'grid';

        if (!videos.length) {
            gridEl.innerHTML = '<div style="color:var(--muted);padding:20px;font-size:0.75rem;grid-column:1/-1;">No videos found.</div>';
            return;
        }

        gridEl.innerHTML = videos.map((v, i) => `
            <div class="rv-card ${v.review==1?'flagged':''} ${i===curIndex?'active':''}"
                 data-index="${i}" data-id="${v.id}">
                <img src="${escH(v.thumbnail||'')}" loading="lazy" alt="${v.id}">
                <span class="rv-card-id">#${v.id}</span>
            </div>
        `).join('');

        gridEl.querySelectorAll('.rv-card').forEach(card => {
            card.addEventListener('click', () => playVideo(parseInt(card.dataset.index), true));
        });

        updateStats();
    }

    // ── Play video ──
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
        updateStats();

        if (window.innerWidth < 900) {
            document.querySelector('.rv-player-wrap').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function updateInfoBar(v) {
        videoNameEl.textContent  = v.name || ('Video #' + v.id);
        videoIdEl.textContent    = '#' + v.id;
        videoDurEl.textContent   = fmtDur(v.duration);
        videoSizeEl.textContent  = fmtSize(v.file_size);

        const flagged = v.review == 1;
        flaggedBadge.style.display = flagged ? 'inline' : 'none';
        btnReview.textContent = flagged ? '✅' : '🏁';
        btnReview.title       = flagged ? 'Unflag' : 'Flag for review';
        btnReview.classList.toggle('flagged', flagged);
        btnReview.disabled  = false;
        btnAssign.disabled  = false;

        const hasAnimatic = !!(v.animatic_id);
        btnAnimatic.disabled           = !hasAnimatic;
        btnAnimatic.dataset.animaticId = v.animatic_id || '';
        btnAnimatic.title              = hasAnimatic ? 'Open Animatic #' + v.animatic_id : 'No linked animatic';

        fetchAssignment(v.id);
    }

    function highlightCard(index) {
        gridEl.querySelectorAll('.rv-card').forEach((c, i) => {
            c.classList.toggle('active', i === index);
        });
        const card = gridEl.querySelector('.rv-card[data-index="' + index + '"]');
        if (card) card.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    function updateNavButtons() {
        btnPrev.disabled = curIndex <= 0 && curPage <= 1;
        btnNext.disabled = curIndex >= videos.length - 1 && curPage >= totalPages;
    }

    function updateStats() {
        statPos.textContent     = curIndex >= 0 ? (curIndex + 1 + (curPage-1)*PER_PAGE) : '—';
        statFlagged.textContent = flaggedTotal >= 0 ? flaggedTotal : '…';
    }

    // ── Navigate prev/next ──
    function navigate(dir) {
        const next = curIndex + dir;

        if (next >= 0 && next < videos.length) {
            playVideo(next, true);
            return;
        }

        const targetPage = curPage + dir;
        if (targetPage < 1 || targetPage > totalPages) return;

        _afterLoad = dir > 0 ? 0 : 9999;
        curIndex   = -1;
        loadPage(targetPage);
    }

    // ── Toggle review flag ──
    function toggleReview() {
        if (curIndex < 0 || curIndex >= videos.length) return;
        const v      = videos[curIndex];
        const newVal = v.review == 1 ? 0 : 1;

        videos[curIndex].review = newVal;
        updateInfoBar(v);
        const card = gridEl.querySelector('.rv-card[data-index="' + curIndex + '"]');
        if (card) card.classList.toggle('flagged', newVal === 1);

        if (newVal === 1) {
            reviewFlash.className = 'rv-flash on';
            setTimeout(() => { reviewFlash.className = 'rv-flash off'; }, 80);
            setTimeout(() => { reviewFlash.className = 'rv-flash'; }, 600);
            flaggedTotal++;
        } else {
            flaggedTotal = Math.max(0, flaggedTotal - 1);
        }
        updateStats();

        fetch('?api_action=toggle_review', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ id: v.id, value: newVal }),
        })
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'ok') {
                videos[curIndex].review = newVal === 1 ? 0 : 1;
                updateInfoBar(videos[curIndex]);
                if (card) card.classList.toggle('flagged', videos[curIndex].review == 1);
                flaggedTotal += newVal === 1 ? -1 : 1;
                updateStats();
                if (typeof Toast !== 'undefined') Toast.show(data.message || 'Error', 'error');
                return;
            }
            if (onlyFlagged && newVal === 0) {
                videos.splice(curIndex, 1);
                renderGrid();
                if (videos.length > 0) playVideo(clamp(curIndex, 0, videos.length-1), false);
                else loadPage(curPage);
            }
        });
    }

    // ── Player events ──
    player.addEventListener('timeupdate', () => {
        if (player.duration) {
            progressFill.style.width = (player.currentTime / player.duration * 100) + '%';
        }
    });
    player.addEventListener('ended', () => {
        if (autoAdvanceCb.checked) navigate(1);
    });
    progressTrack.addEventListener('click', e => {
        if (!player.duration) return;
        const r = progressTrack.getBoundingClientRect();
        player.currentTime = ((e.clientX - r.left) / r.width) * player.duration;
    });

    // ── Keyboard shortcuts ──
    document.addEventListener('keydown', e => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        switch (e.key) {
            case 'ArrowRight': e.preventDefault(); navigate(1);        break;
            case 'ArrowLeft':  e.preventDefault(); navigate(-1);       break;
            case 'r': case 'R': e.preventDefault(); toggleReview();    break;
            case 'a': case 'A': e.preventDefault(); openAssignModal(); break;
            case 'Escape':
                closeAssignModal();
                closeFilterModal();
                break;
            case ' ':
                e.preventDefault();
                player.paused ? player.play().catch(()=>{}) : player.pause();
                break;
        }
    });

    // ════════════════════════════════════
    // FILTER MODAL
    // ════════════════════════════════════
    let filterTreeInited      = false;
    let pendingFilterNodeId   = null;
    let pendingFilterNodeName = '';
    let pendingSeqId          = null;
    let pendingSeqName        = '';
    let pendingFuzzCandId     = null;
    let pendingFuzzCandName   = '';
    let activeFilterTab       = 'tree';

    const filterModal = document.getElementById('filterModal');

    function openFilterModal() {
        // Sync checkbox states
        onlyFlaggedCb.checked = onlyFlagged;
        inclDescCb.checked    = inclDesc;

        // Sync pending selections from current filter state
        pendingFilterNodeId   = filterNodeId;
        pendingFilterNodeName = filterNodeName;
        pendingSeqId          = filterSeqId;
        pendingSeqName        = filterSeqName;
        pendingFuzzCandId     = filterFuzzCandId;
        pendingFuzzCandName   = filterFuzzCandName;

        updateFilterStrip();
        filterModal.classList.add('active');

        // Init / refresh the active tab
        if (activeFilterTab === 'tree') {
            if (!filterTreeInited) initFilterTree();
            else $('#filterTree').jstree('refresh');
        } else if (activeFilterTab === 'narratives') {
            loadNarrativeSequences();
        } else if (activeFilterTab === 'fuzz') {
            loadFuzzCandidates('');
        }
    }

    function closeFilterModal() {
        filterModal.classList.remove('active');
        pendingFilterNodeId = null;
        pendingSeqId        = null;
        pendingFuzzCandId   = null;
    }

    filterModal.addEventListener('click', e => {
        if (e.target === filterModal) closeFilterModal();
    });

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
            loadNarrativeSequences();
        } else if (tab === 'fuzz') {
            loadFuzzCandidates(document.getElementById('fuzzSearchInput').value || '');
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
        } else if (pendingFilterNodeId) {
            label = '🌲 ' + (pendingFilterNodeName || 'Node #' + pendingFilterNodeId);
        }

        if (label) {
            filterActiveText.innerHTML = '<span class="node-name">' + escH(label) + '</span>';
            btnResetFilter.style.display = 'inline-block';
        } else {
            filterActiveText.innerHTML = '<span style="color:var(--muted);">No filter active</span>';
            btnResetFilter.style.display = 'none';
        }
    }

    function updateGearState() {
        const active = onlyFlagged || filterNodeId || filterSeqId || filterFuzzCandId;
        gearStatCell.classList.toggle('filter-active', !!active);

        let label = 'filter';
        if (filterFuzzCandId) {
            label = filterFuzzCandName.length > 6 ? filterFuzzCandName.substring(0, 5) + '…' : (filterFuzzCandName || 'fuzz');
        } else if (filterSeqId) {
            label = filterSeqName.length > 6 ? filterSeqName.substring(0, 5) + '…' : (filterSeqName || 'seq');
        } else if (filterNodeId) {
            label = filterNodeName.length > 6 ? filterNodeName.substring(0, 5) + '…' : (filterNodeName || 'node');
        } else if (onlyFlagged) {
            label = '★ flag';
        }
        document.getElementById('gearLabel').textContent = label;
    }

    // ── Story Tree tab ──
    function initFilterTree() {
        filterTreeInited = true;
        $('#filterTree').jstree({
            core: {
                data: {
                    url: '?api_action=tree_fetch',
                    dataType: 'json',
                    dataFilter: function(raw) {
                        try {
                            const j = JSON.parse(raw);
                            return JSON.stringify(j.status === 'ok' ? j.tree : []);
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
            // Deselect other tabs' UI
            document.querySelectorAll('.rv-seq-item.selected').forEach(el => el.classList.remove('selected'));
            document.querySelectorAll('.rv-fuzz-item.selected').forEach(el => el.classList.remove('selected'));
            updateFilterStrip();
        }).on('deselect_node.jstree', function() {
            pendingFilterNodeId   = null;
            pendingFilterNodeName = '';
            updateFilterStrip();
        });
    }

    function createFilterTreeNode() {
        const name     = document.getElementById('filterNewNodeName').value.trim();
        const nodeType = document.getElementById('filterNewNodeType').value;
        if (!name) return;
        const sel      = $('#filterTree').jstree('get_selected', true);
        const parentId = sel.length ? sel[0].data.db_id : null;

        fetch('?api_action=tree_create_node', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ name, node_type: nodeType, parent_id: parentId }),
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'ok') {
                document.getElementById('filterNewNodeName').value = '';
                $('#filterTree').jstree('refresh');
                if (treeInited) $('#assignTree').jstree('refresh');
                if (typeof Toast !== 'undefined') Toast.show('Node created', 'success');
            } else {
                if (typeof Toast !== 'undefined') Toast.show(d.message || 'Error', 'error');
            }
        });
    }

    // ── Narrative Sequences tab ──
    let seqListLoaded = false;

    function loadNarrativeSequences() {
        const listEl = document.getElementById('seqList');
        listEl.innerHTML = '<div style="color:var(--muted);font-size:0.7rem;padding:12px;text-align:center;"><div class="rv-spinner" style="margin:0 auto 8px;"></div>Loading…</div>';

        fetch('?api_action=list_narrative_sequences')
            .then(r => r.json())
            .then(d => {
                if (d.status !== 'ok') throw new Error(d.message || 'Error');
                const seqs = d.sequences || [];
                if (!seqs.length) {
                    listEl.innerHTML = '<div style="color:var(--muted);font-size:0.7rem;padding:12px;text-align:center;">No narrative sequences found.</div>';
                    return;
                }
                listEl.innerHTML = seqs.map(s => `
                    <div class="rv-seq-item ${pendingSeqId == s.id ? 'selected' : ''}"
                         data-id="${s.id}" data-name="${escH(s.name)}"
                         onclick="selectSeqFilter(${s.id}, ${JSON.stringify(s.name)})">
                        <div class="rv-seq-item-name">${escH(s.name)}</div>
                        ${s.description ? '<div class="rv-seq-item-desc">' + escH(s.description) + '</div>' : ''}
                    </div>
                `).join('');
                seqListLoaded = true;
            })
            .catch(err => {
                listEl.innerHTML = '<div style="color:var(--danger);font-size:0.7rem;padding:12px;">Error: ' + escH(err.message) + '</div>';
            });
    }

    function selectSeqFilter(id, name) {
        pendingSeqId      = id;
        pendingSeqName    = name;
        // Clear others
        pendingFilterNodeId   = null;
        pendingFilterNodeName = '';
        pendingFuzzCandId     = null;
        pendingFuzzCandName   = '';
        if (filterTreeInited) $('#filterTree').jstree('deselect_all');
        document.querySelectorAll('.rv-fuzz-item.selected').forEach(el => el.classList.remove('selected'));
        // Highlight selection
        document.querySelectorAll('.rv-seq-item').forEach(el => {
            el.classList.toggle('selected', parseInt(el.dataset.id) === id);
        });
        updateFilterStrip();
    }
    window.selectSeqFilter = selectSeqFilter;

    // ── Fuzz Candidates tab ──
    let fuzzDebounceTimer = null;

    function debounceFuzzSearch(val) {
        clearTimeout(fuzzDebounceTimer);
        fuzzDebounceTimer = setTimeout(() => loadFuzzCandidates(val), 300);
    }
    window.debounceFuzzSearch = debounceFuzzSearch;

    function loadFuzzCandidates(search) {
        const listEl = document.getElementById('fuzzList');
        listEl.innerHTML = '<div style="color:var(--muted);font-size:0.7rem;padding:12px;text-align:center;"><div class="rv-spinner" style="margin:0 auto 8px;"></div>Loading…</div>';

        const p = new URLSearchParams({ api_action: 'list_fuzz_candidates' });
        if (search) p.set('search', search);

        fetch('?' + p)
            .then(r => r.json())
            .then(d => {
                if (d.status !== 'ok') throw new Error(d.message || 'Error');
                const cands = d.candidates || [];
                if (!cands.length) {
                    listEl.innerHTML = '<div style="color:var(--muted);font-size:0.7rem;padding:12px;text-align:center;">No candidates found.</div>';
                    return;
                }
                listEl.innerHTML = cands.map(c => `
                    <div class="rv-fuzz-item ${pendingFuzzCandId == c.id ? 'selected' : ''}"
                         data-id="${c.id}" data-name="${escH(c.label)}"
                         onclick="selectFuzzFilter(${c.id}, ${JSON.stringify(c.label)})">
                        <span class="rv-fuzz-item-label">${escH(c.label)}</span>
                        <span class="rv-fuzz-item-meta">${c.mention_count ? c.mention_count + ' ment.' : ''}${c.concept_type ? ' · ' + escH(c.concept_type) : ''}</span>
                    </div>
                `).join('');
            })
            .catch(err => {
                listEl.innerHTML = '<div style="color:var(--danger);font-size:0.7rem;padding:12px;">Error: ' + escH(err.message) + '</div>';
            });
    }

    function selectFuzzFilter(id, name) {
        pendingFuzzCandId   = id;
        pendingFuzzCandName = name;
        // Clear others
        pendingFilterNodeId   = null;
        pendingFilterNodeName = '';
        pendingSeqId          = null;
        pendingSeqName        = '';
        if (filterTreeInited) $('#filterTree').jstree('deselect_all');
        document.querySelectorAll('.rv-seq-item.selected').forEach(el => el.classList.remove('selected'));
        // Highlight selection
        document.querySelectorAll('.rv-fuzz-item').forEach(el => {
            el.classList.toggle('selected', parseInt(el.dataset.id) === id);
        });
        updateFilterStrip();
    }
    window.selectFuzzFilter = selectFuzzFilter;

    // ── Apply / Reset ──
    function applyFilter() {
        onlyFlagged      = onlyFlaggedCb.checked;
        inclDesc         = inclDescCb.checked;
        filterNodeId     = pendingFilterNodeId;
        filterNodeName   = pendingFilterNodeName;
        filterSeqId      = pendingSeqId;
        filterSeqName    = pendingSeqName;
        filterFuzzCandId  = pendingFuzzCandId;
        filterFuzzCandName = pendingFuzzCandName;

        closeFilterModal();
        updateGearState();
        curIndex = -1;
        videos   = [];
        loadPage(1);
    }

    function resetFilter() {
        onlyFlaggedCb.checked = false;
        inclDescCb.checked    = true;
        onlyFlagged      = false;
        inclDesc         = true;
        filterNodeId     = null;
        filterNodeName   = '';
        filterSeqId      = null;
        filterSeqName    = '';
        filterFuzzCandId  = null;
        filterFuzzCandName = '';
        pendingFilterNodeId   = null;
        pendingFilterNodeName = '';
        pendingSeqId          = null;
        pendingSeqName        = '';
        pendingFuzzCandId     = null;
        pendingFuzzCandName   = '';

        if (filterTreeInited) $('#filterTree').jstree('deselect_all');
        document.querySelectorAll('.rv-seq-item.selected').forEach(el => el.classList.remove('selected'));
        document.querySelectorAll('.rv-fuzz-item.selected').forEach(el => el.classList.remove('selected'));

        updateFilterStrip();
        updateGearState();
        closeFilterModal();
        curIndex = -1;
        videos   = [];
        loadPage(1);
    }

    // Expose filter modal functions
    window.openFilterModal      = openFilterModal;
    window.closeFilterModal     = closeFilterModal;
    window.applyFilter          = applyFilter;
    window.resetFilter          = resetFilter;
    window.createFilterTreeNode = createFilterTreeNode;

    // ── Flagged count ──
    function fetchFlaggedCount() {
        fetch('?api_action=flagged_count')
            .then(r => r.json())
            .then(d => { if (d.status === 'ok') { flaggedTotal = d.count; updateStats(); } });
    }

    // ── DOM (assign) ──
    const btnAssign       = document.getElementById('btnAssign');
    const btnAnimatic     = document.getElementById('btnAnimatic');
    const assignedBadge   = document.getElementById('assignedBadge');

    // ── Per-video assignment cache ──
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
        if (videoId in assignmentCache) {
            updateAssignBadge(assignmentCache[videoId]);
            return;
        }
        fetch('?api_action=tree_get_assignment&video_id=' + videoId)
            .then(r => r.json())
            .then(d => {
                if (d.status === 'ok') {
                    assignmentCache[videoId] = d.assignment;
                    updateAssignBadge(d.assignment);
                }
            });
    }

    // ════════════════════════════════════
    // ASSIGN MODAL
    // ════════════════════════════════════
    let treeInited        = false;
    let selectedNodeId    = null;
    let selectedNodeName  = '';

    const assignModal       = document.getElementById('assignModal');
    const assignTree        = document.getElementById('assignTree');
    const assignCurrentText = document.getElementById('assignCurrentText');
    const btnUnassign       = document.getElementById('btnUnassign');
    const btnAssignConfirm  = document.getElementById('btnAssignConfirm');

    function openAssignModal() {
        if (curIndex < 0) return;
        const v = videos[curIndex];

        const cached = assignmentCache[v.id];
        if (cached) {
            assignCurrentText.innerHTML = 'Currently: <span class="node-name">' + escH(cached.node_name) + '</span>';
            btnUnassign.style.display = 'inline-block';
        } else {
            assignCurrentText.innerHTML = '<span style="color:var(--muted);">No assignment yet</span>';
            btnUnassign.style.display = 'none';
        }

        selectedNodeId   = null;
        selectedNodeName = '';
        btnAssignConfirm.disabled = true;

        assignModal.classList.add('active');

        if (!treeInited) {
            initAssignTree();
        } else {
            $('#assignTree').jstree('refresh');
        }
    }

    function closeAssignModal() {
        assignModal.classList.remove('active');
        selectedNodeId = null;
    }

    assignModal.addEventListener('click', e => {
        if (e.target === assignModal) closeAssignModal();
    });

    function initAssignTree() {
        treeInited = true;
        $('#assignTree').jstree({
            core: {
                data: {
                    url: '?api_action=tree_fetch',
                    dataType: 'json',
                    dataFilter: function(raw) {
                        try {
                            const j = JSON.parse(raw);
                            return JSON.stringify(j.status === 'ok' ? j.tree : []);
                        } catch(e) { return '[]'; }
                    }
                },
                themes: { name: 'default', dots: true, icons: true },
                check_callback: false,
            },
            plugins: ['types', 'state'],
            types: {
                folder:   { icon: 'bi bi-folder2' },
                episode:  { icon: 'bi bi-film' },
                sequence: { icon: 'bi bi-collection-play' },
                scene:    { icon: 'bi bi-camera-video' },
                other:    { icon: 'bi bi-tag' },
            },
        }).on('select_node.jstree', function(e, data) {
            selectedNodeId   = data.node.data.db_id;
            selectedNodeName = data.node.text;
            btnAssignConfirm.disabled = false;
        }).on('deselect_node.jstree', function() {
            selectedNodeId   = null;
            btnAssignConfirm.disabled = true;
        });
    }

    function createTreeNode() {
        const name     = document.getElementById('newNodeName').value.trim();
        const nodeType = document.getElementById('newNodeType').value;
        if (!name) return;

        const sel  = $('#assignTree').jstree('get_selected', true);
        const parentId = sel.length ? sel[0].data.db_id : null;

        fetch('?api_action=tree_create_node', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ name, node_type: nodeType, parent_id: parentId }),
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'ok') {
                document.getElementById('newNodeName').value = '';
                $('#assignTree').jstree('refresh');
                if (typeof Toast !== 'undefined') Toast.show('Node created', 'success');
            } else {
                if (typeof Toast !== 'undefined') Toast.show(d.message || 'Error', 'error');
            }
        });
    }

    function confirmAssign() {
        if (!selectedNodeId || curIndex < 0) return;
        const v = videos[curIndex];

        btnAssignConfirm.disabled = true;
        btnAssignConfirm.textContent = 'Assigning…';

        fetch('?api_action=tree_assign', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ node_id: selectedNodeId, video_id: v.id }),
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'ok') {
                assignmentCache[v.id] = { node_id: selectedNodeId, node_name: selectedNodeName };
                updateAssignBadge(assignmentCache[v.id]);
                closeAssignModal();
                if (typeof Toast !== 'undefined') Toast.show('Assigned to: ' + selectedNodeName, 'success');
            } else {
                if (typeof Toast !== 'undefined') Toast.show(d.message || 'Error', 'error');
            }
        })
        .finally(() => {
            btnAssignConfirm.disabled = false;
            btnAssignConfirm.textContent = 'Assign to Selected Node';
        });
    }

    function unassignVideo() {
        if (curIndex < 0) return;
        const v = videos[curIndex];

        fetch('?api_action=tree_unassign', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ video_id: v.id }),
        })
        .then(r => r.json())
        .then(d => {
            if (d.status === 'ok') {
                assignmentCache[v.id] = null;
                updateAssignBadge(null);
                btnUnassign.style.display = 'none';
                assignCurrentText.innerHTML = '<span style="color:var(--muted);">No assignment yet</span>';
                if (typeof Toast !== 'undefined') Toast.show('Unassigned', 'success');
            }
        });
    }

    // Expose modal functions for inline onclick
    window.openAssignModal  = openAssignModal;
    window.closeAssignModal = closeAssignModal;
    window.createTreeNode   = createTreeNode;
    window.confirmAssign    = confirmAssign;
    window.unassignVideo    = unassignVideo;

    // ── Pagination button listeners ──
    pgPrevBtn.addEventListener('click', () => loadPage(curPage - 1));
    pgNextBtn.addEventListener('click', () => loadPage(curPage + 1));

    // ── Animatic ──
    function openAnimatic() {
        const animaticId = btnAnimatic.dataset.animaticId;
        if (!animaticId) return;
        if (player && !player.paused) player.pause();
        if (typeof window.showEntityFormInModal === 'function') {
            window.showEntityFormInModal('animatics', animaticId);
        } else {
            window.open('animatics_crud.php?id=' + animaticId, '_blank');
        }
    }

    // ── Expose globals ──
    window.navigate      = navigate;
    window.toggleReview  = toggleReview;
    window.loadPage      = loadPage;
    window._rvLoadPage   = loadPage;
    window.openAnimatic  = openAnimatic;

    // ── Boot ──
    loadPage(1);
    fetchFlaggedCount();

})();
</script>

<?php
$content = ob_get_clean();
// modal_frame_details provides showEntityFormInModal used by animatic button
ob_start();
include __DIR__ . '/modal_frame_details.php';
$content .= ob_get_clean();
$spw->renderLayout($content, $pageTitle);
?>
