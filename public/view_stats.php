<?php
// public/view_stats.php
// ─────────────────────────────────────────────────────────────────────────────
// SAGE AI — Production Statistics Dashboard
// Covers: Sketches, Animatics, and any other entity type
// Mobile-first, Android Chrome, dark theme consistent with generator_forge.php
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { header('Location: /login.php'); exit; }

// ═══════════════════════════════════════════════════════════════════════════
// INLINE API HANDLER
// ═══════════════════════════════════════════════════════════════════════════
if (isset($_REQUEST['api_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $action = $_REQUEST['api_action'];

    try {

        // ── OVERVIEW COUNTS ──────────────────────────────────────────────────
        if ($action === 'overview') {
            $data = [];

            // Sketches
            $data['sketches_total']      = (int)$pdo->query("SELECT COUNT(*) FROM sketches")->fetchColumn();
            $data['sketches_with_frames'] = (int)$pdo->query("
                SELECT COUNT(DISTINCT f2s.to_id) FROM frames_2_sketches f2s
            ")->fetchColumn();
            $data['sketches_no_frames']   = $data['sketches_total'] - $data['sketches_with_frames'];

            // Sketch analysis coverage
            $data['sketches_with_analysis']          = (int)$pdo->query("SELECT COUNT(*) FROM sketch_analysis")->fetchColumn();
            $data['sketches_with_seq_analysis']      = (int)$pdo->query("SELECT COUNT(*) FROM sketch_sequence_analysis")->fetchColumn();
            $data['sketches_with_ingredients']       = (int)$pdo->query("SELECT COUNT(DISTINCT sketch_id) FROM sketch_ingredients")->fetchColumn();
            $data['sketches_no_analysis']            = $data['sketches_total'] - $data['sketches_with_analysis'];
            $data['sketches_no_seq_analysis']        = $data['sketches_total'] - $data['sketches_with_seq_analysis'];
            $data['sketches_no_ingredients']         = $data['sketches_total'] - $data['sketches_with_ingredients'];

            // Animatics
            $data['animatics_total']      = (int)$pdo->query("SELECT COUNT(*) FROM animatics")->fetchColumn();
            $data['animatics_with_videos'] = (int)$pdo->query("
                SELECT COUNT(DISTINCT v2a.to_id) FROM videos_2_animatics v2a
            ")->fetchColumn();
            $data['animatics_no_videos']   = $data['animatics_total'] - $data['animatics_with_videos'];

            // Frames & Videos totals
            $data['frames_total']  = (int)$pdo->query("SELECT COUNT(*) FROM frames")->fetchColumn();
            $data['videos_total']  = (int)$pdo->query("SELECT COUNT(*) FROM videos")->fetchColumn();
            $data['map_runs_total'] = (int)$pdo->query("SELECT COUNT(*) FROM map_runs")->fetchColumn();
            $data['map_runs_sketches'] = (int)$pdo->query("SELECT COUNT(*) FROM map_runs WHERE entity_type='sketches'")->fetchColumn();
            $data['map_runs_animatics'] = (int)$pdo->query("SELECT COUNT(*) FROM map_runs WHERE entity_type='animatics'")->fetchColumn();

            // Narrative sequences
            $data['narrative_sequences_total'] = (int)$pdo->query("SELECT COUNT(*) FROM narrative_sequences")->fetchColumn();

            // Boards — sketches linked directly OR via map_run items OR via storyboard items
            $data['boards_total'] = (int)$pdo->query("SELECT COUNT(*) FROM boards WHERE status='active'")->fetchColumn();
            $data['boards_items_sketches'] = (int)$pdo->query("
                SELECT COUNT(DISTINCT sketch_id) FROM (
                    -- 1. Direct sketch items
                    SELECT item_id AS sketch_id
                    FROM boards_items
                    WHERE item_type = 'sketches'

                    UNION

                    -- 2. Via map_run: frames with entity_type='sketches' mapped to sketches
                    SELECT DISTINCT f2s.to_id AS sketch_id
                    FROM boards_items bi
                    JOIN frames f ON f.map_run_id = bi.item_id
                    JOIN frames_2_sketches f2s ON f2s.from_id = f.id
                    WHERE bi.item_type = 'map_run'
                      AND f.entity_type = 'sketches'

                    UNION

                    -- 3. Via storyboard: storyboard_frames → frames → frames_2_sketches
                    SELECT DISTINCT f2s.to_id AS sketch_id
                    FROM boards_items bi
                    JOIN storyboard_frames sf ON sf.storyboard_id = bi.item_id
                    JOIN frames_2_sketches f2s ON f2s.from_id = sf.frame_id
                    WHERE bi.item_type = 'storyboard'
                ) _all
            ")->fetchColumn();

            // Storyboards
            $data['storyboards_total'] = (int)$pdo->query("SELECT COUNT(*) FROM storyboards WHERE is_archived=0")->fetchColumn();
            $data['storyboard_frames_total'] = (int)$pdo->query("SELECT COUNT(*) FROM storyboard_frames")->fetchColumn();

            // Editorial structure
            $data['editorial_episodes']  = (int)$pdo->query("SELECT COUNT(*) FROM editorial_episodes")->fetchColumn();
            $data['editorial_sequences'] = (int)$pdo->query("SELECT COUNT(*) FROM editorial_sequences")->fetchColumn();
            $data['editorial_scenes']    = (int)$pdo->query("SELECT COUNT(*) FROM editorial_scenes")->fetchColumn();
            $data['editorial_shots']     = (int)$pdo->query("SELECT COUNT(*) FROM editorial_shots")->fetchColumn();

            // Sketches in narrative sequences
            // sequence_data is JSON array of sketch IDs or {sketch_id:...} objects
            $nsCount = (int)$pdo->query("
                SELECT COUNT(DISTINCT
                    CASE WHEN JSON_TYPE(jt.val) = 'INTEGER'
                         THEN JSON_VALUE(jt.val, '$')
                         ELSE JSON_VALUE(jt.val, '$.sketch_id')
                    END
                )
                FROM narrative_sequences ns,
                JSON_TABLE(ns.sequence_data, '$[*]' COLUMNS(val JSON PATH '$')) jt
            ")->fetchColumn();
            $data['sketches_in_narrative_sequences'] = $nsCount;

            // Sketches in boards
            $data['sketches_in_boards'] = (int)$pdo->query("
                SELECT COUNT(DISTINCT item_id) FROM boards_items WHERE item_type='sketches'
            ")->fetchColumn();

            // Sketches in storyboards (via frames_2_sketches + storyboard_frames)
            $data['sketches_in_storyboards'] = (int)$pdo->query("
                SELECT COUNT(DISTINCT f2s.to_id)
                FROM storyboard_frames sf
                JOIN frames_2_sketches f2s ON sf.frame_id = f2s.from_id
            ")->fetchColumn();

            echo json_encode(['ok' => true, 'data' => $data]);
            exit;
        }

        // ── SKETCHES WITHOUT FRAMES (paginated) ──────────────────────────────
        if ($action === 'sketches_no_frames') {
            $limit  = max(1, (int)($_GET['limit'] ?? 50));
            $offset = max(0, (int)($_GET['offset'] ?? 0));
            $total  = (int)$pdo->query("
                SELECT COUNT(*) FROM sketches s
                WHERE NOT EXISTS (SELECT 1 FROM frames_2_sketches f2s WHERE f2s.to_id = s.id)
            ")->fetchColumn();
            $stmt = $pdo->prepare("
                SELECT s.id, s.name, s.created_at, s.updated_at,
                       s.regenerate_images
                FROM sketches s
                WHERE NOT EXISTS (SELECT 1 FROM frames_2_sketches f2s WHERE f2s.to_id = s.id)
                ORDER BY s.id DESC
                LIMIT :lim OFFSET :off
            ");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total]);
            exit;
        }

        // ── ANIMATICS WITHOUT VIDEOS (paginated) ─────────────────────────────
        if ($action === 'animatics_no_videos') {
            $limit  = max(1, (int)($_GET['limit'] ?? 50));
            $offset = max(0, (int)($_GET['offset'] ?? 0));
            $total  = (int)$pdo->query("
                SELECT COUNT(*) FROM animatics a
                WHERE NOT EXISTS (SELECT 1 FROM videos_2_animatics v2a WHERE v2a.to_id = a.id)
            ")->fetchColumn();
            $stmt = $pdo->prepare("
                SELECT a.id, a.name, a.created_at, a.updated_at,
                       a.regenerate_videos
                FROM animatics a
                WHERE NOT EXISTS (SELECT 1 FROM videos_2_animatics v2a WHERE v2a.to_id = a.id)
                ORDER BY a.id DESC
                LIMIT :lim OFFSET :off
            ");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total]);
            exit;
        }

        // ── SKETCHES WITHOUT ANALYSIS ─────────────────────────────────────────
        if ($action === 'sketches_no_analysis') {
            $type   = $_GET['type'] ?? 'analysis'; // analysis | seq_analysis | ingredients
            $limit  = max(1, (int)($_GET['limit'] ?? 50));
            $offset = max(0, (int)($_GET['offset'] ?? 0));

            $subquery = match($type) {
                'seq_analysis'  => "SELECT 1 FROM sketch_sequence_analysis ssa WHERE ssa.sketch_id = s.id",
                'ingredients'   => "SELECT 1 FROM sketch_ingredients si WHERE si.sketch_id = s.id",
                default         => "SELECT 1 FROM sketch_analysis sa WHERE sa.sketch_id = s.id",
            };

            $total = (int)$pdo->query("SELECT COUNT(*) FROM sketches s WHERE NOT EXISTS ($subquery)")->fetchColumn();
            $stmt  = $pdo->prepare("
                SELECT s.id, s.name, s.created_at, s.updated_at
                FROM sketches s
                WHERE NOT EXISTS ($subquery)
                ORDER BY s.id DESC
                LIMIT :lim OFFSET :off
            ");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'total' => $total]);
            exit;
        }

        // ── MAP RUNS BREAKDOWN ────────────────────────────────────────────────
        if ($action === 'map_runs_breakdown') {
            $stmt = $pdo->query("
                SELECT m.entity_type,
                       COUNT(m.id) as run_count,
                       SUM(CASE WHEN m.entity_type='sketches'  THEN (SELECT COUNT(*) FROM frames  WHERE map_run_id=m.id) ELSE 0 END) as frames_sum,
                       SUM(CASE WHEN m.entity_type='animatics' THEN (SELECT COUNT(*) FROM videos  WHERE map_run_id=m.id) ELSE 0 END) as videos_sum
                FROM map_runs m
                GROUP BY m.entity_type
                ORDER BY run_count DESC
            ");
            echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        // ── SKETCHES LINKAGE BREAKDOWN ────────────────────────────────────────
        if ($action === 'sketches_linkage') {
            $data = [];

            // In at least one narrative sequence
            $data['in_narrative_seq'] = (int)$pdo->query("
                SELECT COUNT(DISTINCT
                    CASE WHEN JSON_TYPE(jt.val) = 'INTEGER'
                         THEN JSON_VALUE(jt.val, '$')
                         ELSE JSON_VALUE(jt.val, '$.sketch_id')
                    END
                )
                FROM narrative_sequences ns,
                JSON_TABLE(ns.sequence_data, '$[*]' COLUMNS(val JSON PATH '$')) jt
            ")->fetchColumn();

            // In any board (direct + via map_run + via storyboard)
            $data['in_boards'] = (int)$pdo->query("
                SELECT COUNT(DISTINCT sketch_id) FROM (
                    SELECT item_id AS sketch_id
                    FROM boards_items WHERE item_type = 'sketches'
                    UNION
                    SELECT DISTINCT f2s.to_id AS sketch_id
                    FROM boards_items bi
                    JOIN frames f ON f.map_run_id = bi.item_id
                    JOIN frames_2_sketches f2s ON f2s.from_id = f.id
                    WHERE bi.item_type = 'map_run' AND f.entity_type = 'sketches'
                    UNION
                    SELECT DISTINCT f2s.to_id AS sketch_id
                    FROM boards_items bi
                    JOIN storyboard_frames sf ON sf.storyboard_id = bi.item_id
                    JOIN frames_2_sketches f2s ON f2s.from_id = sf.frame_id
                    WHERE bi.item_type = 'storyboard'
                ) _all
            ")->fetchColumn();

            // In storyboards (via frames)
            $data['in_storyboards'] = (int)$pdo->query("
                SELECT COUNT(DISTINCT f2s.to_id)
                FROM storyboard_frames sf
                JOIN frames_2_sketches f2s ON sf.frame_id = f2s.from_id
            ")->fetchColumn();

            // Sketches whose frames appear in editorial shots
            $data['in_editorial'] = (int)$pdo->query("
                SELECT COUNT(DISTINCT f2s.to_id)
                FROM editorial_shots es
                JOIN videos v ON es.video_id = v.id
                JOIN videos_2_animatics v2a ON v2a.from_id = v.id
                JOIN animatics a ON v2a.to_id = a.id
                JOIN frames f ON a.img2img_frame_id = f.id
                JOIN frames_2_sketches f2s ON f2s.from_id = f.id
            ")->fetchColumn();

            $data['total'] = (int)$pdo->query("SELECT COUNT(*) FROM sketches")->fetchColumn();

            echo json_encode(['ok' => true, 'data' => $data]);
            exit;
        }

        // ── RECENT MAP RUNS ───────────────────────────────────────────────────
        if ($action === 'recent_map_runs') {
            $limit = max(1, (int)($_GET['limit'] ?? 10));
            $entityType = $_GET['entity_type'] ?? '';
            $where = $entityType ? "WHERE m.entity_type = " . $pdo->quote($entityType) : "";
            $stmt = $pdo->prepare("
                SELECT m.id, m.entity_type, m.note, m.created_at,
                       (SELECT COUNT(*) FROM frames  WHERE map_run_id = m.id) as frame_count,
                       (SELECT COUNT(*) FROM videos  WHERE map_run_id = m.id) as video_count
                FROM map_runs m
                $where
                ORDER BY m.id DESC
                LIMIT :lim
            ");
            $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(['ok' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            exit;
        }

        // ── GAP CLUSTERS: contiguous ID ranges with no frames/videos ────────
        // entity_type: sketches|animatics
        // Returns groups: [{range_start, range_end, count, ids[]}], paginated
        if ($action === 'gap_clusters') {
            $entityType = $_GET['entity_type'] ?? 'sketches';
            $page       = max(1, (int)($_GET['page']  ?? 1));
            $perPage    = max(1, (int)($_GET['per_page'] ?? 20));
            $offset     = ($page - 1) * $perPage;

            if (!in_array($entityType, ['sketches', 'animatics'], true)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Invalid entity_type']);
                exit;
            }

            // Fetch all gap IDs ordered — we cluster in PHP (no window functions needed)
            if ($entityType === 'sketches') {
                $regenCol = 'regenerate_images';
                $stmt = $pdo->query("
                    SELECT s.id, s.name, s.{$regenCol}
                    FROM sketches s
                    WHERE NOT EXISTS (SELECT 1 FROM frames_2_sketches f2s WHERE f2s.to_id = s.id)
                    ORDER BY s.id ASC
                ");
            } else {
                $regenCol = 'regenerate_videos';
                $stmt = $pdo->query("
                    SELECT a.id, a.name, a.{$regenCol}
                    FROM animatics a
                    WHERE NOT EXISTS (SELECT 1 FROM videos_2_animatics v2a WHERE v2a.to_id = a.id)
                    ORDER BY a.id ASC
                ");
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ── Fetch queued entity IDs from map_run_queue (pending + processing)
            // asset_type matches the expected output asset for this entity_type
            $assetType = $entityType === 'sketches' ? 'frames' : 'videos';
            $qStmt = $pdo->prepare("
                SELECT DISTINCT entity_id
                FROM map_run_queue
                WHERE entity_type = :et
                  AND asset_type  = :at
                  AND status IN ('pending', 'processing')
            ");
            $qStmt->execute([':et' => $entityType, ':at' => $assetType]);
            $queuedIds = array_flip(array_map('intval', $qStmt->fetchAll(PDO::FETCH_COLUMN)));

            // Cluster consecutive IDs into groups (gap tolerance = 1, i.e. purely consecutive)
            // We use a small tolerance: IDs within a gap of <=3 are merged into same cluster
            // so scattered singles near each other group naturally
            $GAP_TOLERANCE = 3;
            $clusters = [];
            if (!empty($rows)) {
                $cur = [
                    'range_start' => (int)$rows[0]['id'],
                    'range_end'   => (int)$rows[0]['id'],
                    'rows'        => [$rows[0]],
                ];
                for ($i = 1; $i < count($rows); $i++) {
                    $id = (int)$rows[$i]['id'];
                    if ($id - $cur['range_end'] <= $GAP_TOLERANCE) {
                        $cur['range_end'] = $id;
                        $cur['rows'][]    = $rows[$i];
                    } else {
                        $clusters[] = $cur;
                        $cur = [
                            'range_start' => $id,
                            'range_end'   => $id,
                            'rows'        => [$rows[$i]],
                        ];
                    }
                }
                $clusters[] = $cur;
            }

            // Sort by cluster size descending (biggest gaps first = most actionable)
            usort($clusters, fn($a, $b) => count($b['rows']) - count($a['rows']));

            $totalClusters = count($clusters);
            $totalPages    = (int)ceil($totalClusters / $perPage) ?: 1;
            $page          = min($page, $totalPages);
            $slice         = array_slice($clusters, ($page - 1) * $perPage, $perPage);

            // Build output — include ids, regen states, names, and queued flags per cluster
            $output = array_map(function($c) use ($regenCol, $queuedIds) {
                $ids        = array_column($c['rows'], 'id');
                $regenFlags = array_combine(
                    array_map('intval', $ids),
                    array_map(fn($r) => (int)$r[$regenCol], $c['rows'])
                );
                // Compact id→name map so the UI can show real names per row
                $names = array_combine(
                    array_map('intval', $ids),
                    array_column($c['rows'], 'name')
                );
                // Which IDs in this cluster are already queued (pending/processing)
                $clusterQueuedIds = array_values(array_filter(
                    array_map('intval', $ids),
                    fn($id) => isset($queuedIds[$id])
                ));
                return [
                    'range_start'       => $c['range_start'],
                    'range_end'         => $c['range_end'],
                    'count'             => count($c['rows']),
                    'ids'               => array_map('intval', $ids),
                    'regen_flags'       => $regenFlags,
                    'names'             => $names,
                    'queued_ids'        => $clusterQueuedIds,
                    'all_flagged'       => !in_array(0, array_values($regenFlags)),
                    'none_flagged'      => !in_array(1, array_values($regenFlags)),
                    'sample_name'       => $c['rows'][0]['name'] ?? '',
                ];
            }, $slice);

            echo json_encode([
                'ok'          => true,
                'data'        => $output,
                'total'       => $totalClusters,
                'total_ids'   => count($rows),
                'page'        => $page,
                'total_pages' => $totalPages,
                'per_page'    => $perPage,
                'entity_type' => $entityType,
                'regen_col'   => $regenCol,
            ]);
            exit;
        }

        // ── SET REGEN FLAG ─────────────────────────────────────────────────────
        // POST: entity_type (sketches|animatics), ids[] (array), value (0|1)
        if ($action === 'set_regen') {
            $input      = json_decode(file_get_contents('php://input'), true) ?? [];
            $entityType = $input['entity_type'] ?? '';
            $ids        = array_filter(array_map('intval', $input['ids'] ?? []), fn($v) => $v > 0);
            $value      = (int)(bool)($input['value'] ?? 0);

            if (!in_array($entityType, ['sketches', 'animatics'], true)) {
                http_response_code(400);
                echo json_encode(['ok' => false, 'error' => 'Invalid entity_type']);
                exit;
            }
            if (empty($ids)) {
                echo json_encode(['ok' => true, 'affected' => 0]);
                exit;
            }

            $col   = $entityType === 'sketches' ? 'regenerate_images' : 'regenerate_videos';
            $table = $entityType;
            $in    = implode(',', $ids);
            $affected = $pdo->exec("UPDATE `$table` SET `$col` = $value WHERE id IN ($in)");

            echo json_encode(['ok' => true, 'affected' => $affected, 'value' => $value]);
            exit;
        }

        echo json_encode(['ok' => false, 'error' => 'Unknown action']);

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// PAGE RENDER
// ─────────────────────────────────────────────────────────────────────────────
$pageTitle = 'Production Stats';
ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=0.8, viewport-fit=cover">
<title>Production Stats — SAGE AI</title>
<script>
(function() {
    try {
        var theme = localStorage.getItem('spw_theme');
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else if (theme === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    } catch (e) {}
})();
</script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@300;400;500&family=Bebas+Neue&family=Barlow+Condensed:wght@300;400;600;700;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════════════════════════════════
   SAGE STATS — Design System
   Aesthetic: Signal Intelligence — dark ops, neon pulse, data terminals
   Palette: near-black voids, electric cyan, ember orange, signal green
═══════════════════════════════════════════════════════════════════════════ */
:root {
    --bg:           #05070d;
    --surface:      #090c14;
    --card:         #0c1020;
    --card-lit:     #0f1428;
    --border:       #161e30;
    --border-hi:    #1e2c48;
    --text:         #b8c8e0;
    --text-dim:     #3a4a62;
    --text-bright:  #ddeeff;
    --cyan:         #00d4ff;
    --cyan-dim:     rgba(0,212,255,0.08);
    --cyan-mid:     rgba(0,212,255,0.18);
    --cyan-glow:    rgba(0,212,255,0.45);
    --orange:       #ff6b2b;
    --orange-dim:   rgba(255,107,43,0.09);
    --orange-mid:   rgba(255,107,43,0.18);
    --green:        #00e5a0;
    --green-dim:    rgba(0,229,160,0.09);
    --red:          #ff3d5a;
    --red-dim:      rgba(255,61,90,0.09);
    --purple:       #a855f7;
    --purple-dim:   rgba(168,85,247,0.09);
    --yellow:       #f5c400;
    --yellow-dim:   rgba(245,196,0,0.10);
    --yellow-mid:   rgba(245,196,0,0.22);
    --mono:         'DM Mono', 'Fira Mono', monospace;
    --head:         'Bebas Neue', 'Barlow Condensed', sans-serif;
    --body:         'Barlow Condensed', system-ui, sans-serif;
    --radius:       5px;
    --radius-lg:    10px;
    /* Header uses its own var so light mode can override without affecting surface */
    --surface-header: rgba(5,7,13,0.95);
}

/* ── LIGHT THEME — driven by global dashboard via [data-theme="light"] on <html>
   Matches the same selector pattern as base.css. No toggle here.         ── */
:root[data-theme="light"],
html[data-theme="light"],
body[data-theme="light"] {
    --bg:           #f0f4f8;
    --surface:      #ffffff;
    --card:         #ffffff;
    --card-lit:     #f5f8fc;
    --border:       #d0d8e4;
    --border-hi:    #aab8cc;
    --text:         #1a2533;
    --text-dim:     #7a8fa8;
    --text-bright:  #0d1824;
    /* Accent colours stay vibrant but shift to work on light backgrounds */
    --cyan:         #0094b3;
    --cyan-dim:     rgba(0,148,179,0.09);
    --cyan-mid:     rgba(0,148,179,0.2);
    --cyan-glow:    rgba(0,148,179,0.3);
    --orange:       #c94a00;
    --orange-dim:   rgba(201,74,0,0.08);
    --orange-mid:   rgba(201,74,0,0.18);
    --green:        #007a50;
    --green-dim:    rgba(0,122,80,0.09);
    --red:          #c0162f;
    --red-dim:      rgba(192,22,47,0.08);
    --purple:       #7c3aed;
    --purple-dim:   rgba(124,58,237,0.09);
    --yellow:       #b38a00;
    --yellow-dim:   rgba(179,138,0,0.09);
    --yellow-mid:   rgba(179,138,0,0.20);
    --surface-header: rgba(240,244,248,0.96);
}

/* Suppress dark scanline overlay in light mode */
html[data-theme="light"] body::before,
body[data-theme="light"]::before {
    display: none;
}
/* Soften icon pulse glow in light mode so it doesn't look harsh */
html[data-theme="light"] .header-logo-icon,
body[data-theme="light"] .header-logo-icon {
    box-shadow: 0 0 8px var(--cyan-glow);
    animation: none;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html { scroll-behavior: smooth; }

html, body {
    background: var(--bg);
    color: var(--text);
    font-family: var(--body);
    font-size: 15px;
    line-height: 1.5;
    -webkit-font-smoothing: antialiased;
    min-height: 100%;
    overflow-x: hidden;
}

::-webkit-scrollbar { width: 3px; height: 3px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border-hi); border-radius: 3px; }

/* ─── SCANLINE OVERLAY ─── */
body::before {
    content: '';
    position: fixed; inset: 0; pointer-events: none; z-index: 1000;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 2px,
        rgba(0,212,255,0.012) 2px,
        rgba(0,212,255,0.012) 4px
    );
}

/* ─── LAYOUT ─── */
.stats-layout {
    display: grid;
    grid-template-rows: 56px 1fr;
    min-height: 100vh;
    width: 100vw;
    max-width: 100vw;
    overflow-x: hidden;
}

/* ─── HEADER ─── */
.stats-header {
    position: sticky; top: 0; z-index: 200;
    display: flex; align-items: center; justify-content: space-between;
    padding: 0 20px;
    background: var(--surface-header);
    backdrop-filter: blur(12px);
    border-bottom: 1px solid var(--border);
    height: 56px;
    min-width: 0;
    max-width: 100vw;
}
.header-logo {
    display: flex; align-items: center; gap: 10px;
}
.header-logo-icon {
    width: 32px; height: 32px;
    background: var(--cyan-dim);
    border: 1px solid var(--cyan-mid);
    border-radius: var(--radius);
    display: flex; align-items: center; justify-content: center;
    font-size: 15px; color: var(--cyan);
    box-shadow: 0 0 12px var(--cyan-glow);
    animation: iconPulse 4s ease-in-out infinite;
}
@keyframes iconPulse {
    0%,100% { box-shadow: 0 0 8px var(--cyan-glow); }
    50%      { box-shadow: 0 0 20px var(--cyan-glow), 0 0 40px rgba(0,212,255,0.2); }
}
.header-logo-text {
    font-family: var(--head);
    font-size: 1.4rem;
    letter-spacing: 4px;
    color: var(--text-bright);
}
.header-logo-sub {
    font-family: var(--mono);
    font-size: 0.6rem;
    color: var(--cyan);
    letter-spacing: 3px;
    text-transform: uppercase;
    opacity: 0.7;
    display: none;
}
@media (min-width: 500px) { .header-logo-sub { display: block; } }

.header-right {
    display: flex; align-items: center; gap: 8px;
}
.header-time {
    font-family: var(--mono);
    font-size: 0.65rem;
    color: var(--text-dim);
    letter-spacing: 1px;
}
.btn-refresh {
    width: 34px; height: 34px;
    border: 1px solid var(--border-hi);
    background: var(--card);
    border-radius: var(--radius);
    color: var(--text-dim);
    cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 15px;
    transition: all 0.2s;
}
.btn-refresh:hover { border-color: var(--cyan); color: var(--cyan); background: var(--cyan-dim); }
.btn-refresh.spinning i { animation: spin 0.6s linear infinite; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ─── MAIN CONTENT ─── */
.stats-main {
    padding: 20px 16px 40px;
    max-width: 1400px;
    margin: 0 auto;
    width: 100%;
    min-width: 0;        /* Critical: lets this grid child shrink below content size */
    overflow-x: hidden;  /* Any overflow is absorbed here, not pushed to the grid   */
    box-sizing: border-box;
}

/* ─── SECTION HEADER ─── */
.section-head {
    display: flex; align-items: center; gap: 10px;
    margin-bottom: 14px; margin-top: 32px;
}
.section-head:first-child { margin-top: 0; }
.section-label {
    font-family: var(--head);
    font-size: 1.5rem;
    letter-spacing: 5px;
    color: var(--text-bright);
    text-transform: uppercase;
}
.section-line {
    flex: 1; height: 1px;
    background: linear-gradient(to right, var(--border-hi), transparent);
}
.section-badge {
    font-family: var(--mono);
    font-size: 0.6rem;
    color: var(--cyan);
    border: 1px solid var(--cyan-mid);
    background: var(--cyan-dim);
    padding: 2px 8px;
    border-radius: 20px;
    letter-spacing: 1px;
}

/* ─── METRIC CARDS GRID ─── */
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(155px, 1fr));
    gap: 10px;
    margin-bottom: 12px;
}
.metric-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 14px 14px 12px;
    position: relative;
    overflow: hidden;
    transition: border-color 0.2s, background 0.2s;
    cursor: default;
}
.metric-card::before {
    content: '';
    position: absolute; top: 0; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, transparent, var(--accent-color, var(--cyan)), transparent);
    opacity: 0;
    transition: opacity 0.3s;
}
.metric-card:hover { background: var(--card-lit); border-color: var(--border-hi); }
.metric-card:hover::before { opacity: 1; }

.metric-card.cyan   { --accent-color: var(--cyan);   }
.metric-card.orange { --accent-color: var(--orange);  }
.metric-card.green  { --accent-color: var(--green);   }
.metric-card.red    { --accent-color: var(--red);     }
.metric-card.purple { --accent-color: var(--purple);  }

.metric-icon {
    font-size: 18px;
    margin-bottom: 8px;
    opacity: 0.7;
}
.metric-card.cyan   .metric-icon { color: var(--cyan);   }
.metric-card.orange .metric-icon { color: var(--orange);  }
.metric-card.green  .metric-icon { color: var(--green);  }
.metric-card.red    .metric-icon { color: var(--red);    }
.metric-card.purple .metric-icon { color: var(--purple); }

.metric-value {
    font-family: var(--head);
    font-size: 2.4rem;
    line-height: 1;
    color: var(--text-bright);
    letter-spacing: 2px;
    margin-bottom: 4px;
    transition: color 0.3s;
}
.metric-card.cyan   .metric-value { color: var(--cyan); }
.metric-card.orange .metric-value { color: var(--orange); }
.metric-card.green  .metric-value { color: var(--green); }

.metric-label {
    font-family: var(--mono);
    font-size: 0.62rem;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 1.5px;
    line-height: 1.3;
}

.metric-sub {
    margin-top: 6px;
    font-family: var(--mono);
    font-size: 0.58rem;
    color: var(--text-dim);
    display: flex; align-items: center; gap: 4px;
}
.metric-sub .good  { color: var(--green); }
.metric-sub .warn  { color: var(--orange); }
.metric-sub .bad   { color: var(--red); }

/* Progress bar inside card */
.metric-bar {
    margin-top: 8px;
    height: 3px;
    background: var(--border);
    border-radius: 3px;
    overflow: hidden;
}
.metric-bar-fill {
    height: 100%;
    border-radius: 3px;
    transition: width 1s cubic-bezier(0.25, 1, 0.5, 1);
    width: 0%;
}
.metric-card.cyan   .metric-bar-fill { background: var(--cyan); box-shadow: 0 0 8px var(--cyan-glow); }
.metric-card.orange .metric-bar-fill { background: var(--orange); }
.metric-card.green  .metric-bar-fill { background: var(--green); }
.metric-card.red    .metric-bar-fill { background: var(--red); }
.metric-card.purple .metric-bar-fill { background: var(--purple); }

/* ─── LOADING SKELETON ─── */
.skeleton {
    background: linear-gradient(90deg, var(--card) 25%, var(--card-lit) 50%, var(--card) 75%);
    background-size: 200% 100%;
    animation: shimmer 1.5s infinite;
    border-radius: var(--radius);
}
@keyframes shimmer {
    0%   { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}
.skeleton-val { height: 36px; width: 60%; margin-bottom: 6px; }
.skeleton-label { height: 10px; width: 80%; }

/* ─── COVERAGE GAUGE ─── */
.coverage-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-bottom: 10px;
}
@media (max-width: 500px) {
    .coverage-row { grid-template-columns: 1fr; }
}

.coverage-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 16px;
    position: relative;
    overflow: hidden;
}
.coverage-title {
    font-family: var(--mono);
    font-size: 0.62rem;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 2px;
    margin-bottom: 12px;
    display: flex; align-items: center; gap: 6px;
}
.coverage-title i { font-size: 12px; }

.coverage-ring-wrap {
    display: flex; align-items: center; gap: 16px;
}
.ring-svg { flex-shrink: 0; }
.ring-bg   { fill: none; stroke: var(--border-hi); stroke-width: 6; }
.ring-fill { fill: none; stroke-width: 6; stroke-linecap: round; transition: stroke-dashoffset 1.2s cubic-bezier(0.25, 1, 0.5, 1); }
.ring-pct {
    font-family: var(--head);
    font-size: 0.9rem;
    letter-spacing: 1px;
    text-anchor: middle;
    dominant-baseline: middle;
}

.coverage-stats { flex: 1; }
.cov-stat-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 5px 0; border-bottom: 1px solid var(--border);
    font-size: 0.78rem;
}
.cov-stat-row:last-child { border-bottom: none; }
.cov-stat-label { color: var(--text-dim); font-family: var(--mono); font-size: 0.62rem; text-transform: uppercase; letter-spacing: 1px; }
.cov-stat-val   { font-family: var(--head); font-size: 1.1rem; letter-spacing: 1px; }

/* ─── DATA TABLE PANEL ─── */
.data-panel {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    overflow: hidden;
    margin-bottom: 10px;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
}
.data-panel-head {
    display: flex; align-items: center; justify-content: space-between;
    padding: 12px 16px;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
    gap: 10px;
}
.data-panel-head:hover { background: var(--card); }
.data-panel-title {
    font-family: var(--mono);
    font-size: 0.72rem;
    color: var(--text);
    text-transform: uppercase;
    letter-spacing: 2px;
    display: flex; align-items: center; gap: 8px;
    flex: 1;
}
.data-panel-title .dp-count {
    font-size: 0.65rem;
    color: var(--orange);
    background: var(--orange-dim);
    border: 1px solid var(--orange-mid);
    padding: 1px 7px;
    border-radius: 20px;
}
.data-panel-title .dp-count.ok {
    color: var(--green);
    background: var(--green-dim);
    border-color: rgba(0,229,160,0.25);
}
.data-panel-chevron {
    color: var(--text-dim);
    font-size: 14px;
    transition: transform 0.2s;
}
.data-panel.open .data-panel-chevron { transform: rotate(180deg); }

.data-panel-body {
    display: none;
    overflow: hidden;
}
.data-panel.open .data-panel-body { display: block; }

.data-table {
    width: 100%;
    border-collapse: collapse;
    font-family: var(--mono);
    font-size: 0.72rem;
}
.data-table th {
    padding: 8px 14px;
    text-align: left;
    font-size: 0.6rem;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 2px;
    border-bottom: 1px solid var(--border);
    background: var(--surface);
}
.data-table td {
    padding: 9px 14px;
    border-bottom: 1px solid var(--border);
    color: var(--text);
    vertical-align: middle;
}
.data-table tr:last-child td { border-bottom: none; }
.data-table tr:hover td { background: var(--card-lit); }

.data-table .td-id      { color: var(--text-dim); font-size: 0.62rem; }
.data-table .td-name    { color: var(--text-bright); font-weight: 500; max-width: 190px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.data-table .td-date    { color: var(--text-dim); font-size: 0.62rem; }
.data-table .td-flag    { }
.flag-dot {
    display: inline-block;
    width: 7px; height: 7px;
    border-radius: 50%;
    background: var(--orange);
    box-shadow: 0 0 6px var(--orange);
}
.flag-dot.regen { background: var(--cyan); box-shadow: 0 0 6px var(--cyan-glow); }

.table-load-more {
    width: 100%; padding: 10px;
    background: transparent;
    border: none; border-top: 1px solid var(--border);
    color: var(--text-dim);
    font-family: var(--mono); font-size: 0.65rem;
    text-transform: uppercase; letter-spacing: 2px;
    cursor: pointer;
    transition: background 0.15s, color 0.15s;
}
.table-load-more:hover { background: var(--surface); color: var(--cyan); }
.table-load-more:disabled { opacity: 0.4; cursor: not-allowed; }

.table-loading {
    padding: 20px;
    text-align: center;
    color: var(--text-dim);
    font-family: var(--mono);
    font-size: 0.7rem;
    display: flex; align-items: center; justify-content: center; gap: 8px;
}
.mini-spinner {
    width: 14px; height: 14px;
    border: 2px solid var(--border-hi);
    border-top-color: var(--cyan);
    border-radius: 50%;
    animation: spin 0.7s linear infinite;
    flex-shrink: 0;
}

/* ─── LINKAGE RADAR ─── */
.linkage-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 10px;
    margin-bottom: 10px;
}
.linkage-card {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 14px;
    position: relative; overflow: hidden;
}
.linkage-icon {
    font-size: 22px;
    margin-bottom: 8px;
}
.linkage-value {
    font-family: var(--head);
    font-size: 2rem;
    line-height: 1;
    letter-spacing: 2px;
    color: var(--text-bright);
    margin-bottom: 3px;
}
.linkage-label {
    font-family: var(--mono);
    font-size: 0.6rem;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 1.5px;
}
.linkage-sub {
    margin-top: 6px;
    font-family: var(--mono);
    font-size: 0.58rem;
    color: var(--text-dim);
}
.linkage-bar {
    margin-top: 8px; height: 2px;
    background: var(--border);
    border-radius: 2px; overflow: hidden;
}
.linkage-bar-fill {
    height: 100%; border-radius: 2px;
    transition: width 1.2s cubic-bezier(0.25, 1, 0.5, 1);
    width: 0%;
}

/* ─── MAP RUNS TABLE ─── */
.run-badge {
    display: inline-block;
    padding: 2px 7px;
    border-radius: 3px;
    font-size: 0.6rem;
    font-family: var(--mono);
    text-transform: uppercase;
    letter-spacing: 1px;
    border: 1px solid;
}
.run-badge.sketches  { color: var(--cyan);   border-color: var(--cyan-mid);           background: var(--cyan-dim); }
.run-badge.animatics { color: var(--orange); border-color: var(--orange-mid);          background: var(--orange-dim); }
.run-badge.other     { color: var(--text-dim); border-color: var(--border); }

/* ─── ANALYSIS TABS ─── */
.analysis-tabs {
    display: flex; gap: 6px; margin-bottom: 10px; flex-wrap: wrap;
}
.analysis-tab {
    padding: 5px 14px;
    border-radius: 20px;
    border: 1px solid var(--border-hi);
    background: transparent;
    color: var(--text-dim);
    font-family: var(--mono);
    font-size: 0.65rem;
    cursor: pointer;
    transition: all 0.15s;
    -webkit-tap-highlight-color: transparent;
    text-transform: uppercase;
    letter-spacing: 1px;
}
.analysis-tab:hover { border-color: var(--orange); color: var(--orange); }
.analysis-tab.active { background: var(--orange-mid); border-color: var(--orange); color: var(--orange); }

/* ─── TOAST ─── */
.stats-toast {
    position: fixed; bottom: 20px; right: 16px; z-index: 9999;
    background: var(--card);
    border: 1px solid var(--border-hi);
    border-radius: var(--radius);
    padding: 10px 16px;
    font-family: var(--mono);
    font-size: 0.75rem;
    color: var(--text);
    display: none;
    animation: toastIn 0.2s ease;
    box-shadow: 0 4px 20px rgba(0,0,0,0.5);
    max-width: 300px;
}
@keyframes toastIn { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:none; } }

/* ─── RESPONSIVE ─── */
@media (min-width: 700px) {
    .stats-main { padding: 24px 24px 60px; }
    .metrics-grid { grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); }
}
@media (min-width: 1100px) {
    .metrics-grid { grid-template-columns: repeat(auto-fill, minmax(185px, 1fr)); }
}

/* ─── EMPTY STATE ─── */
.empty-state {
    padding: 30px 20px;
    text-align: center;
    color: var(--text-dim);
    font-family: var(--mono);
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 2px;
}
.empty-state i { display: block; font-size: 2rem; margin-bottom: 10px; opacity: 0.3; }

/* ─── REGEN CHECKBOX ─── */
.regen-chk {
    appearance: none; -webkit-appearance: none;
    width: 18px; height: 18px;
    border: 1px solid var(--border-hi);
    border-radius: 3px;
    background: var(--surface);
    cursor: pointer;
    position: relative;
    transition: background 0.15s, border-color 0.15s;
    vertical-align: middle;
    flex-shrink: 0;
}
.regen-chk:checked {
    background: var(--cyan);
    border-color: var(--cyan);
    box-shadow: 0 0 8px var(--cyan-glow);
}
.regen-chk:checked::after {
    content: '✓';
    position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    font-size: 11px; font-weight: 900; color: #000;
    line-height: 1;
}
.regen-chk.saving { opacity: 0.5; pointer-events: none; }

/* ─── FLAG-ALL TOOLBAR ─── */
.flag-toolbar {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    padding: 8px 14px;
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    font-family: var(--mono); font-size: 0.65rem;
}
.flag-toolbar-label { color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; flex: 1; }
.flag-toolbar-label span { color: var(--cyan); }
.btn-flag-all {
    padding: 4px 12px;
    border-radius: 3px;
    border: 1px solid var(--cyan-mid);
    background: var(--cyan-dim);
    color: var(--cyan);
    font-family: var(--mono); font-size: 0.65rem;
    text-transform: uppercase; letter-spacing: 1px;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
    -webkit-tap-highlight-color: transparent;
}
.btn-flag-all:hover { background: var(--cyan-mid); border-color: var(--cyan); }
.btn-flag-all.unflag { border-color: var(--border-hi); background: transparent; color: var(--text-dim); }
.btn-flag-all.unflag:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }

/* ─── DEEP DIVE BUTTON (in panel header) ─── */
.btn-deep-dive {
    display: flex; align-items: center; gap: 5px;
    padding: 4px 10px; border-radius: 3px;
    border: 1px solid rgba(0,212,255,0.3); background: rgba(0,212,255,0.06); color: var(--cyan);
    font-family: var(--mono); font-size: 0.62rem; text-transform: uppercase; letter-spacing: 1px;
    cursor: pointer; flex-shrink: 0; height: 28px;
    transition: background 0.15s, border-color 0.15s;
    -webkit-tap-highlight-color: transparent;
}
.btn-deep-dive:hover { background: var(--cyan-mid); border-color: var(--cyan); }
.btn-deep-dive i { font-size: 12px; }

/* ─── DEEP DIVE MODAL ─── */
.dd-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.85);
    backdrop-filter: blur(4px); z-index: 5000;
    display: none; align-items: flex-end; justify-content: center; padding: 0;
}
.dd-overlay.open { display: flex; }
@media (min-width: 600px) { .dd-overlay { align-items: center; padding: 16px; } }
.dd-sheet {
    width: 100%; max-width: 680px; background: var(--surface);
    border: 1px solid var(--border-hi); border-radius: 14px 14px 0 0;
    display: flex; flex-direction: column; max-height: 92dvh; overflow: hidden;
    box-shadow: 0 -10px 60px rgba(0,0,0,0.7);
    animation: ddSlideUp 0.22s cubic-bezier(0.25,1,0.5,1);
}
@media (min-width: 600px) { .dd-sheet { border-radius: 14px; max-height: 88dvh; animation: ddFadeIn 0.18s ease; } }
@keyframes ddSlideUp { from { transform: translateY(40px); opacity:0; } to { transform:none; opacity:1; } }
@keyframes ddFadeIn  { from { opacity:0; transform:scale(0.97); } to { opacity:1; transform:none; } }
.dd-header { display: flex; align-items: flex-start; gap: 10px; padding: 14px 16px 12px; border-bottom: 1px solid var(--border); flex-shrink: 0; }
.dd-title-block { flex: 1; }
.dd-title { font-family: var(--head); font-size: 1.3rem; letter-spacing: 4px; color: var(--text-bright); }
.dd-subtitle { font-family: var(--mono); font-size: 0.6rem; color: var(--cyan); text-transform: uppercase; letter-spacing: 2px; margin-top: 2px; }
.dd-close {
    width: 32px; height: 32px; border: 1px solid var(--border-hi); background: transparent;
    border-radius: var(--radius); color: var(--text-dim); cursor: pointer; font-size: 16px;
    display: flex; align-items: center; justify-content: center; transition: all 0.15s; flex-shrink: 0;
    -webkit-tap-highlight-color: transparent;
}
.dd-close:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }
.dd-pgbar {
    display: flex; align-items: center; gap: 8px; padding: 8px 14px;
    border-bottom: 1px solid var(--border); background: var(--card); flex-shrink: 0; flex-wrap: wrap;
}
.dd-pg-info { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); flex: 1; white-space: nowrap; }
.dd-pg-info span { color: var(--cyan); }
.dd-pg-nav { display: flex; align-items: center; gap: 4px; }
.dd-pg-btn {
    width: 32px; height: 32px; border: 1px solid var(--border-hi); background: transparent; color: var(--text-dim);
    border-radius: var(--radius); cursor: pointer; font-size: 13px;
    display: flex; align-items: center; justify-content: center; transition: all 0.12s;
    -webkit-tap-highlight-color: transparent;
}
.dd-pg-btn:hover:not(:disabled) { border-color: var(--cyan); color: var(--cyan); }
.dd-pg-btn:disabled { opacity: 0.25; pointer-events: none; }
.dd-pg-input {
    width: 40px; height: 32px; text-align: center; background: var(--bg);
    border: 1px solid var(--border-hi); border-radius: var(--radius); color: var(--cyan);
    font-family: var(--mono); font-size: 0.78rem; font-weight: 700; cursor: text;
    -moz-appearance: textfield; transition: border-color 0.12s;
}
.dd-pg-input:focus { outline: none; border-color: var(--cyan); }
.dd-pg-input::-webkit-inner-spin-button, .dd-pg-input::-webkit-outer-spin-button { -webkit-appearance: none; }
.dd-pg-sep { font-family: var(--mono); font-size: 0.65rem; color: var(--text-dim); white-space: nowrap; }
.dd-body { flex: 1; overflow-y: auto; padding: 10px; }
.dd-body::-webkit-scrollbar { width: 3px; }
.dd-body::-webkit-scrollbar-thumb { background: var(--border-hi); }
.dd-cluster {
    background: var(--card); border: 1px solid var(--border);
    border-radius: var(--radius-lg); margin-bottom: 8px; overflow: hidden; transition: border-color 0.15s;
}
.dd-cluster:hover { border-color: var(--border-hi); }
.dd-cluster-head {
    display: flex; align-items: center; gap: 8px; padding: 10px 12px;
    cursor: pointer; user-select: none; -webkit-tap-highlight-color: transparent;
}
.dd-cluster-head:hover { background: var(--card-lit); }
.dd-range { font-family: var(--head); font-size: 1.1rem; letter-spacing: 2px; color: var(--cyan); white-space: nowrap; flex-shrink: 0; }
.dd-range-sep { color: var(--text-dim); margin: 0 3px; font-size: 0.8rem; }
.dd-cluster-meta { flex: 1; min-width: 0; }
.dd-cluster-count { font-family: var(--mono); font-size: 0.6rem; color: var(--text-dim); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1px; }
.dd-cluster-sample { font-size: 0.78rem; color: var(--text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.dd-cluster-actions { display: flex; gap: 5px; flex-shrink: 0; align-items: center; }
.dd-flag-btn {
    padding: 3px 8px; border-radius: 3px;
    border: 1px solid var(--cyan-mid); background: var(--cyan-dim); color: var(--cyan);
    font-family: var(--mono); font-size: 0.58rem; text-transform: uppercase; letter-spacing: 0.5px;
    cursor: pointer; white-space: nowrap; transition: all 0.12s; -webkit-tap-highlight-color: transparent;
}
.dd-flag-btn:hover { background: var(--cyan-mid); border-color: var(--cyan); }
.dd-flag-btn.clear-btn { border-color: var(--border-hi); background: transparent; color: var(--text-dim); }
.dd-flag-btn.clear-btn:hover { border-color: var(--red); color: var(--red); background: var(--red-dim); }
.dd-flag-btn.saving { opacity: 0.4; pointer-events: none; }
.dd-cluster-chevron { color: var(--text-dim); font-size: 11px; transition: transform 0.18s; flex-shrink: 0; }
.dd-cluster.expanded .dd-cluster-chevron { transform: rotate(180deg); }
.dd-cluster-rows { display: none; border-top: 1px solid var(--border); background: var(--bg); }
.dd-cluster.expanded .dd-cluster-rows { display: block; }
.dd-cluster-row-item {
    display: flex; align-items: center; gap: 10px; padding: 7px 12px;
    border-bottom: 1px solid var(--border); font-size: 0.78rem;
}
.dd-cluster-row-item:last-child { border-bottom: none; }
.dd-row-id { font-family: var(--mono); font-size: 0.62rem; color: var(--text-dim); min-width: 48px; flex-shrink: 0; }
.dd-row-name { flex: 1; color: var(--text); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dd-row-chk {
    appearance: none; -webkit-appearance: none; width: 18px; height: 18px;
    border: 1px solid var(--border-hi); border-radius: 3px; background: var(--surface);
    cursor: pointer; position: relative; transition: background 0.15s, border-color 0.15s; flex-shrink: 0;
}
.dd-row-chk:checked { background: var(--cyan); border-color: var(--cyan); box-shadow: 0 0 8px var(--cyan-glow); }
.dd-row-chk:checked::after { content: '✓'; position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-size: 11px; font-weight: 900; color: #000; line-height: 1; }
.dd-row-chk.saving { opacity: 0.4; pointer-events: none; }
.dd-size-pill { font-family: var(--mono); font-size: 0.57rem; padding: 2px 7px; border-radius: 20px; border: 1px solid; flex-shrink: 0; }
.dd-size-pill.large  { color: var(--red);    border-color: rgba(255,61,90,0.3);  background: var(--red-dim);    }
.dd-size-pill.medium { color: var(--orange); border-color: var(--orange-mid);     background: var(--orange-dim); }
.dd-size-pill.small  { color: var(--text-dim); border-color: var(--border);       background: transparent;       }
.dd-loading { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 12px; padding: 50px 20px; color: var(--text-dim); font-family: var(--mono); font-size: 0.72rem; text-transform: uppercase; letter-spacing: 2px; }

/* ─── QUEUED PILL — shown when an item is pending/processing in map_run_queue ─── */
.queued-pill {
    display: inline-flex; align-items: center; gap: 3px;
    padding: 1px 6px; border-radius: 20px;
    font-family: var(--mono); font-size: 0.56rem; text-transform: uppercase; letter-spacing: 0.5px;
    border: 1px solid var(--yellow-mid);
    background: var(--yellow-dim);
    color: var(--yellow);
    white-space: nowrap; flex-shrink: 0;
}
.queued-pill i { font-size: 9px; }
/* Cluster-level queued summary pill (shown in header actions area) */
.dd-queued-summary {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 20px;
    font-family: var(--mono); font-size: 0.57rem; text-transform: uppercase; letter-spacing: 0.5px;
    border: 1px solid var(--yellow-mid);
    background: var(--yellow-dim);
    color: var(--yellow);
    white-space: nowrap; flex-shrink: 0;
}
.dd-queued-summary i { font-size: 10px; }

/* ─── ENTITY FORM MODAL ─── */
.ef-overlay {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.88);
    backdrop-filter: blur(4px);
    z-index: 6000;   /* above deep dive */
    display: none; align-items: flex-end; justify-content: center; padding: 0;
}
.ef-overlay.open { display: flex; }
@media (min-width: 600px) { .ef-overlay { align-items: center; padding: 16px; } }
.ef-sheet {
    width: 100%; max-width: 900px;
    background: var(--surface);
    border: 1px solid var(--border-hi);
    border-radius: 14px 14px 0 0;
    display: flex; flex-direction: column;
    height: 92dvh;
    overflow: hidden;
    box-shadow: 0 -10px 60px rgba(0,0,0,0.7);
    animation: ddSlideUp 0.22s cubic-bezier(0.25,1,0.5,1);
}
@media (min-width: 600px) { .ef-sheet { border-radius: 14px; height: 88dvh; animation: ddFadeIn 0.18s ease; } }
.ef-header {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 14px; border-bottom: 1px solid var(--border);
    flex-shrink: 0;
}
.ef-title {
    font-family: var(--mono); font-size: 0.7rem;
    text-transform: uppercase; letter-spacing: 2px;
    color: var(--cyan); flex: 1;
}
.ef-body { flex: 1; overflow: hidden; }
.ef-body iframe {
    width: 100%; height: 100%; border: none; background: var(--bg);
    display: block;
}

/* ─── TINY ENTITY OPEN BUTTON ─── */
.btn-entity {
    display: inline-flex; align-items: center; justify-content: center;
    width: 22px; height: 22px;
    border: 1px solid var(--border-hi);
    background: transparent;
    border-radius: 3px;
    color: var(--text-dim);
    cursor: pointer; font-size: 11px;
    transition: all 0.12s;
    flex-shrink: 0;
    -webkit-tap-highlight-color: transparent;
    vertical-align: middle;
}
.btn-entity:hover { border-color: var(--cyan); color: var(--cyan); background: var(--cyan-dim); }

/* ─── TABLE SCROLL WRAPPER ───
   Constrains tables to their column width so wide tables scroll
   horizontally within their own area without expanding the document.  ─── */
.tbl-scroll-wrap {
    overflow-x: auto;
    overflow-y: visible;
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    -webkit-overflow-scrolling: touch;
}
.tbl-scroll-wrap::-webkit-scrollbar { height: 3px; }
.tbl-scroll-wrap::-webkit-scrollbar-thumb { background: var(--border-hi); border-radius: 3px; }

.editorial-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
}
@media (max-width: 600px) {
    .editorial-row { grid-template-columns: repeat(2, 1fr); }
}
.ed-cell {
    background: var(--card);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 12px;
    text-align: center;
}
.ed-val {
    font-family: var(--head);
    font-size: 1.8rem;
    color: var(--purple);
    letter-spacing: 2px;
    line-height: 1;
    margin-bottom: 4px;
}
.ed-label {
    font-family: var(--mono);
    font-size: 0.58rem;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 1.5px;
}
</style>
</head>
<body>

<div class="stats-layout">

    <!-- ── HEADER ── -->
    <header class="stats-header">
        <div class="header-logo">
            <div class="header-logo-icon"><i class="bi bi-activity"></i></div>
            <div>
                <div class="header-logo-text">PRODUCTION STATS</div>
                <div class="header-logo-sub">SAGE AI SIGNAL INTELLIGENCE</div>
            </div>
        </div>
        <div class="header-right">
            <div class="header-time" id="headerClock">—</div>
            <button class="btn-refresh" id="btnRefresh" onclick="Stats.refresh()" title="Refresh all data">
                <i class="bi bi-arrow-clockwise"></i>
            </button>
        </div>
    </header>

    <!-- ── MAIN ── -->
    <main class="stats-main">

        <!-- ════════════════════════════════════
             1. OVERVIEW PULSE
        ════════════════════════════════════ -->
        <div class="section-head">
            <span class="section-label">Overview</span>
            <span class="section-line"></span>
            <span class="section-badge" id="lastRefreshed">LOADING</span>
        </div>

        <div class="metrics-grid" id="overviewGrid">
            <!-- JS rendered -->
            <div class="metric-card cyan"><div class="skeleton skeleton-val"></div><div class="skeleton skeleton-label"></div></div>
            <div class="metric-card cyan"><div class="skeleton skeleton-val"></div><div class="skeleton skeleton-label"></div></div>
            <div class="metric-card orange"><div class="skeleton skeleton-val"></div><div class="skeleton skeleton-label"></div></div>
            <div class="metric-card orange"><div class="skeleton skeleton-val"></div><div class="skeleton skeleton-label"></div></div>
            <div class="metric-card green"><div class="skeleton skeleton-val"></div><div class="skeleton skeleton-label"></div></div>
            <div class="metric-card green"><div class="skeleton skeleton-val"></div><div class="skeleton skeleton-label"></div></div>
        </div>

        <!-- ════════════════════════════════════
             2. SKETCH COVERAGE GAUGES
        ════════════════════════════════════ -->
        <div class="section-head">
            <span class="section-label">Sketch Coverage</span>
            <span class="section-line"></span>
            <span class="section-badge">FRAMES + ANALYSIS</span>
        </div>

        <div class="coverage-row" id="coverageRow">
            <!-- JS rendered -->
        </div>

        <!-- ════════════════════════════════════
             3. ANALYSIS GAPS
        ════════════════════════════════════ -->
        <div class="section-head">
            <span class="section-label">Analysis Gaps</span>
            <span class="section-line"></span>
        </div>

        <div class="analysis-tabs" id="analysisTabs">
            <button class="analysis-tab active" data-type="analysis" onclick="Stats.switchAnalysisTab(this)">Analysis</button>
            <button class="analysis-tab" data-type="seq_analysis" onclick="Stats.switchAnalysisTab(this)">Sequence Analysis</button>
            <button class="analysis-tab" data-type="ingredients" onclick="Stats.switchAnalysisTab(this)">Ingredients</button>
        </div>

        <div class="data-panel open" id="panelAnalysisGaps">
            <div class="data-panel-head" onclick="Stats.togglePanel(this)">
                <div class="data-panel-title">
                    <i class="bi bi-exclamation-triangle" style="color:var(--orange);"></i>
                    Sketches Missing Analysis
                    <span class="dp-count" id="dpCountAnalysis">—</span>
                </div>
                <i class="bi bi-chevron-down data-panel-chevron"></i>
            </div>
            <div class="data-panel-body">
                <div id="analysisGapsBody">
                    <div class="table-loading"><div class="mini-spinner"></div> Loading…</div>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════
             4. FRAME GAPS (Sketches without frames)
        ════════════════════════════════════ -->
        <div class="section-head">
            <span class="section-label">Frame Gaps</span>
            <span class="section-line"></span>
            <span class="section-badge">UNMAPPED SKETCHES</span>
        </div>

        <div class="data-panel" id="panelSketchesNoFrames">
            <div class="data-panel-head" onclick="Stats.togglePanel(this)">
                <div class="data-panel-title">
                    <i class="bi bi-image" style="color:var(--red);"></i>
                    Sketches Without Mapped Frames
                    <span class="dp-count" id="dpCountSketchesNoFrames">—</span>
                </div>
                <button class="btn-deep-dive" onclick="event.stopPropagation(); Stats.openDeepDive('sketches')" title="Deep Dive: ID range clusters">
                    <i class="bi bi-layers"></i> Deep Dive
                </button>
                <i class="bi bi-chevron-down data-panel-chevron"></i>
            </div>
            <div class="data-panel-body">
                <div id="sketchesNoFramesBody">
                    <div class="table-loading"><div class="mini-spinner"></div> Loading…</div>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════
             5. VIDEO GAPS (Animatics without videos)
        ════════════════════════════════════ -->
        <div class="section-head">
            <span class="section-label">Video Gaps</span>
            <span class="section-line"></span>
            <span class="section-badge">UNMAPPED ANIMATICS</span>
        </div>

        <div class="data-panel" id="panelAnimaticsNoVideos">
            <div class="data-panel-head" onclick="Stats.togglePanel(this)">
                <div class="data-panel-title">
                    <i class="bi bi-film" style="color:var(--red);"></i>
                    Animatics Without Mapped Videos
                    <span class="dp-count" id="dpCountAnimaticsNoVideos">—</span>
                </div>
                <button class="btn-deep-dive" onclick="event.stopPropagation(); Stats.openDeepDive('animatics')" title="Deep Dive: ID range clusters">
                    <i class="bi bi-layers"></i> Deep Dive
                </button>
                <i class="bi bi-chevron-down data-panel-chevron"></i>
            </div>
            <div class="data-panel-body">
                <div id="animaticsNoVideosBody">
                    <div class="table-loading"><div class="mini-spinner"></div> Loading…</div>
                </div>
            </div>
        </div>

        <!-- ════════════════════════════════════
             6. SKETCH LINKAGE
        ════════════════════════════════════ -->
        <div class="section-head">
            <span class="section-label">Sketch Linkage</span>
            <span class="section-line"></span>
            <span class="section-badge">NARRATIVE CONNECTIONS</span>
        </div>

        <div class="linkage-grid" id="linkageGrid">
            <!-- JS rendered -->
        </div>

        <!-- ════════════════════════════════════
             7. EDITORIAL STRUCTURE
        ════════════════════════════════════ -->
        <div class="section-head">
            <span class="section-label">Editorial</span>
            <span class="section-line"></span>
            <span class="section-badge">STRUCTURE</span>
        </div>

        <div class="editorial-row" id="editorialRow">
            <!-- JS rendered -->
        </div>

        <!-- ════════════════════════════════════
             8. RECENT MAP RUNS
        ════════════════════════════════════ -->
        <div class="section-head" style="margin-top:32px;">
            <span class="section-label">Recent Map Runs</span>
            <span class="section-line"></span>
        </div>

        <div class="data-panel open" id="panelMapRuns">
            <div class="data-panel-head" onclick="Stats.togglePanel(this)">
                <div class="data-panel-title">
                    <i class="bi bi-collection" style="color:var(--cyan);"></i>
                    Latest Inference Runs
                </div>
                <i class="bi bi-chevron-down data-panel-chevron"></i>
            </div>
            <div class="data-panel-body">
                <div id="mapRunsBody">
                    <div class="table-loading"><div class="mini-spinner"></div> Loading…</div>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- ── ENTITY FORM MODAL ── -->
<div class="ef-overlay" id="efOverlay" onclick="if(event.target===this)closeEntityModal()">
    <div class="ef-sheet">
        <div class="ef-header">
            <span class="ef-title" id="efTitle">Entity</span>
            <button class="dd-close" onclick="closeEntityModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="ef-body">
            <iframe id="efIframe" src="about:blank" allowfullscreen></iframe>
        </div>
    </div>
</div>

<!-- ── DEEP DIVE MODAL ── -->
<div class="dd-overlay" id="ddOverlay" onclick="if(event.target===this)Stats.closeDeepDive()">
    <div class="dd-sheet">
        <div class="dd-header">
            <div class="dd-title-block">
                <div class="dd-title" id="ddTitle">DEEP DIVE</div>
                <div class="dd-subtitle" id="ddSubtitle">ID RANGE CLUSTER ANALYSIS</div>
            </div>
            <button class="dd-close" onclick="Stats.closeDeepDive()"><i class="bi bi-x-lg"></i></button>
        </div>

        <div class="dd-pgbar" id="ddPgBar">
            <span class="dd-pg-info" id="ddPgInfo"><span>—</span> clusters across <span id="ddTotalIds">—</span> IDs</span>
            <div class="dd-pg-nav">
                <button class="dd-pg-btn" id="ddPgPrev" disabled onclick="Stats.ddNavigate(-1)"><i class="bi bi-chevron-left"></i></button>
                <input  type="number" class="dd-pg-input" id="ddPgInput" value="1" min="1">
                <span class="dd-pg-sep" id="ddPgSep">/ 1</span>
                <button class="dd-pg-btn" id="ddPgNext" disabled onclick="Stats.ddNavigate(1)"><i class="bi bi-chevron-right"></i></button>
            </div>
        </div>

        <div class="dd-body" id="ddBody">
            <div class="dd-loading"><div class="mini-spinner"></div>Loading clusters…</div>
        </div>
    </div>
</div>

<div class="stats-toast" id="statsToast"></div>

<!-- ── MAIN JS ── -->
<script>
const API = '';   // same-file inline API

const Stats = (() => {
    'use strict';

    let _overview  = null;
    let _analysisType = 'analysis';

    // Pagination state per table
    const _pages = {
        sketchesNoFrames:   { offset: 0, limit: 50, total: 0, loading: false },
        animaticsNoVideos:  { offset: 0, limit: 50, total: 0, loading: false },
        analysisGaps:       { offset: 0, limit: 50, total: 0, loading: false },
    };

    // ── helpers ──────────────────────────────────────────────────────────
    function esc(s) {
        if (s == null) return '';
        const d = document.createElement('div');
        d.textContent = String(s);
        return d.innerHTML;
    }
    function pct(a, b) { return b > 0 ? Math.min(100, Math.round(a / b * 100)) : 0; }
    function fmt(n) { return Number(n).toLocaleString(); }
    function fmtDate(s) { return s ? s.substring(0, 10) : '—'; }

    function toast(msg, col) {
        const el = document.getElementById('statsToast');
        el.textContent = msg;
        el.style.borderColor = col || '';
        el.style.display = 'block';
        clearTimeout(el._t);
        el._t = setTimeout(() => { el.style.display = 'none'; }, 2800);
    }

    async function api(action, params = {}) {
        const qs = new URLSearchParams({ api_action: action, ...params });
        const r  = await fetch('?' + qs.toString());
        return r.json();
    }

    async function apiPost(action, body = {}) {
        const r = await fetch('?api_action=' + encodeURIComponent(action), {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify(body),
        });
        return r.json();
    }

    // ── Clock ─────────────────────────────────────────────────────────────
    function startClock() {
        const el = document.getElementById('headerClock');
        const tick = () => {
            const now = new Date();
            el.textContent = now.toLocaleTimeString('en-GB', { hour12: false });
        };
        tick();
        setInterval(tick, 1000);
    }

    // ── Refresh ───────────────────────────────────────────────────────────
    async function refresh() {
        const btn = document.getElementById('btnRefresh');
        btn.classList.add('spinning');
        _pages.sketchesNoFrames.offset  = 0;
        _pages.animaticsNoVideos.offset = 0;
        _pages.analysisGaps.offset      = 0;

        await Promise.all([
            loadOverview(),
            loadSketchesNoFrames(true),
            loadAnimaticsNoVideos(true),
            loadAnalysisGaps(true),
            loadLinkage(),
            loadMapRuns(),
        ]);

        btn.classList.remove('spinning');
        document.getElementById('lastRefreshed').textContent = new Date().toLocaleTimeString('en-GB', { hour12: false });
        toast('Stats refreshed');
    }

    // ── Overview ──────────────────────────────────────────────────────────
    async function loadOverview() {
        const res = await api('overview');
        if (!res.ok) return;
        _overview = res.data;
        renderOverview(res.data);
        renderCoverage(res.data);
        renderEditorial(res.data);
    }

    function renderOverview(d) {
        const grid = document.getElementById('overviewGrid');
        grid.innerHTML = [
            metricCard('cyan',   'bi-pencil-square', fmt(d.sketches_total),  'Total Sketches',  `${fmt(d.sketches_with_frames)} mapped`, pct(d.sketches_with_frames, d.sketches_total)),
            metricCard('cyan',   'bi-image',         fmt(d.frames_total),    'Total Frames',    `across ${fmt(d.map_runs_sketches)} sketch runs`, 100),
            metricCard('orange', 'bi-play-btn',       fmt(d.animatics_total), 'Total Animatics', `${fmt(d.animatics_with_videos)} with video`, pct(d.animatics_with_videos, d.animatics_total)),
            metricCard('orange', 'bi-film',           fmt(d.videos_total),   'Total Videos',    `across ${fmt(d.map_runs_animatics)} animatic runs`, 100),
            metricCard('green',  'bi-collection',     fmt(d.map_runs_total), 'Map Runs',        `${fmt(d.map_runs_sketches)} sketch / ${fmt(d.map_runs_animatics)} animatic`, 100),
            metricCard('green',  'bi-diagram-3',      fmt(d.narrative_sequences_total), 'Narrative Seqs', `${fmt(d.sketches_in_narrative_sequences)} sketches linked`, null),
            metricCard('purple', 'bi-kanban',         fmt(d.boards_total),   'Active Boards',   `${fmt(d.boards_items_sketches)} sketches via all items`, null),
            metricCard('purple', 'bi-images',         fmt(d.storyboards_total), 'Storyboards',  `${fmt(d.storyboard_frames_total)} frames`, null),
        ].join('');

        requestAnimationFrame(() => {
            grid.querySelectorAll('.metric-bar-fill[data-w]').forEach(el => {
                el.style.width = el.dataset.w + '%';
            });
        });
    }

    function metricCard(color, icon, value, label, sub, barPct) {
        const barHtml = barPct !== null && barPct !== undefined
            ? `<div class="metric-bar"><div class="metric-bar-fill" data-w="${barPct}" style="width:0%"></div></div>`
            : '';
        const subColor = barPct !== null && barPct !== undefined
            ? (barPct >= 80 ? 'good' : barPct >= 40 ? 'warn' : 'bad')
            : '';
        return `
            <div class="metric-card ${color}">
                <div class="metric-icon"><i class="bi ${icon}"></i></div>
                <div class="metric-value">${value}</div>
                <div class="metric-label">${esc(label)}</div>
                ${sub ? `<div class="metric-sub"><span class="${subColor}">${esc(sub)}</span></div>` : ''}
                ${barHtml}
            </div>`;
    }

    // ── Coverage gauges ───────────────────────────────────────────────────
    function renderCoverage(d) {
        const row = document.getElementById('coverageRow');
        const total = d.sketches_total || 1;

        const gauges = [
            {
                label: 'Frame Coverage',
                icon: 'bi-image',
                color: '#00d4ff',
                has: d.sketches_with_frames,
                total,
                rows: [
                    { label: 'Mapped',   val: d.sketches_with_frames, color: '#00d4ff' },
                    { label: 'Missing',  val: d.sketches_no_frames,   color: '#ff3d5a' },
                ]
            },
            {
                label: 'Analysis Coverage',
                icon: 'bi-cpu',
                color: '#ff6b2b',
                has: d.sketches_with_analysis,
                total,
                rows: [
                    { label: 'Analysis',   val: d.sketches_with_analysis,     color: '#ff6b2b' },
                    { label: 'Seq. Anal.', val: d.sketches_with_seq_analysis,  color: '#a855f7' },
                    { label: 'Ingred.',    val: d.sketches_with_ingredients,   color: '#00e5a0' },
                ]
            },
        ];

        row.innerHTML = gauges.map(g => {
            const p = pct(g.has, g.total);
            const r = 36, circ = 2 * Math.PI * r;
            const offset = circ * (1 - p / 100);
            return `
                <div class="coverage-card">
                    <div class="coverage-title"><i class="bi ${g.icon}"></i>${esc(g.label)}</div>
                    <div class="coverage-ring-wrap">
                        <svg class="ring-svg" width="88" height="88" viewBox="0 0 88 88">
                            <circle class="ring-bg" cx="44" cy="44" r="${r}"/>
                            <circle class="ring-fill" cx="44" cy="44" r="${r}"
                                stroke="${g.color}"
                                stroke-dasharray="${circ}"
                                stroke-dashoffset="${circ}"
                                data-offset="${offset}"
                                transform="rotate(-90 44 44)"
                                style="filter:drop-shadow(0 0 6px ${g.color}88)"/>
                            <text class="ring-pct" x="44" y="44" fill="${g.color}" font-family="'Bebas Neue',sans-serif" font-size="20" letter-spacing="2">${p}%</text>
                        </svg>
                        <div class="coverage-stats">
                            ${g.rows.map(row => `
                                <div class="cov-stat-row">
                                    <span class="cov-stat-label">${esc(row.label)}</span>
                                    <span class="cov-stat-val" style="color:${row.color}">${fmt(row.val)}</span>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        requestAnimationFrame(() => {
            setTimeout(() => {
                document.querySelectorAll('.ring-fill[data-offset]').forEach(el => {
                    el.style.transition = 'stroke-dashoffset 1.2s cubic-bezier(0.25,1,0.5,1)';
                    el.style.strokeDashoffset = el.dataset.offset;
                });
            }, 80);
        });
    }

    // ── Editorial row ─────────────────────────────────────────────────────
    function renderEditorial(d) {
        document.getElementById('editorialRow').innerHTML = [
            `<div class="ed-cell"><div class="ed-val">${fmt(d.editorial_episodes)}</div><div class="ed-label">Episodes</div></div>`,
            `<div class="ed-cell"><div class="ed-val">${fmt(d.editorial_sequences)}</div><div class="ed-label">Sequences</div></div>`,
            `<div class="ed-cell"><div class="ed-val">${fmt(d.editorial_scenes)}</div><div class="ed-label">Scenes</div></div>`,
            `<div class="ed-cell"><div class="ed-val">${fmt(d.editorial_shots)}</div><div class="ed-label">Shots</div></div>`,
        ].join('');
    }

    // ═══════════════════════════════════════════════════════════════════
    // REGEN CHECKBOX + FLAG-ALL LOGIC
    // ═══════════════════════════════════════════════════════════════════

    // Toggle a single regen checkbox and persist to DB immediately
    async function toggleRegenRow(chk, entityType, id) {
        const newVal = chk.checked ? 1 : 0;
        chk.classList.add('saving');
        try {
            const res = await apiPost('set_regen', { entity_type: entityType, ids: [id], value: newVal });
            if (!res.ok) {
                // Revert on failure
                chk.checked = !chk.checked;
                toast('Failed to update: ' + (res.error || '?'), '#ff3d5a');
            } else {
                toast(newVal ? 'Regen flagged' : 'Regen cleared', newVal ? '#00d4ff' : null);
            }
        } catch(e) {
            chk.checked = !chk.checked;
            toast('Network error', '#ff3d5a');
        }
        chk.classList.remove('saving');
    }

    // Collect all visible entity IDs + current regen states from a container
    function getVisibleRegenState(containerId) {
        const tbody = document.getElementById('tbody_' + containerId);
        if (!tbody) return [];
        return Array.from(tbody.querySelectorAll('.regen-chk')).map(chk => ({
            id:      parseInt(chk.dataset.id),
            checked: chk.checked,
            chk,
        }));
    }

    // Flag-all / unflag-all for visible rows in a panel
    async function flagAllVisible(containerId, entityType, value) {
        const rows  = getVisibleRegenState(containerId);
        const ids   = rows.map(r => r.id);
        if (!ids.length) { toast('No rows loaded yet'); return; }

        // Optimistic update
        rows.forEach(r => { r.chk.checked = !!value; r.chk.classList.add('saving'); });
        updateFlagToolbarLabel(containerId, entityType);

        try {
            const res = await apiPost('set_regen', { entity_type: entityType, ids, value });
            if (!res.ok) {
                // Revert
                rows.forEach(r => { r.chk.checked = !value; r.chk.classList.remove('saving'); });
                toast('Batch update failed: ' + (res.error || '?'), '#ff3d5a');
            } else {
                rows.forEach(r => r.chk.classList.remove('saving'));
                toast(`${res.affected} item(s) ${value ? 'flagged' : 'cleared'} for regen`, value ? '#00d4ff' : null);
                updateFlagToolbarLabel(containerId, entityType);
            }
        } catch(e) {
            rows.forEach(r => { r.chk.checked = !value; r.chk.classList.remove('saving'); });
            toast('Network error', '#ff3d5a');
        }
    }

    // Update the "N flagged" counter in a flag toolbar
    function updateFlagToolbarLabel(containerId, entityType) {
        const toolbar = document.getElementById('flagbar_' + containerId);
        if (!toolbar) return;
        const rows    = getVisibleRegenState(containerId);
        const flagged = rows.filter(r => r.checked).length;
        const label   = toolbar.querySelector('.flag-toolbar-label');
        if (label) {
            label.innerHTML = `<span>${flagged}</span> / ${rows.length} visible flagged for regen`;
        }
    }

    // Build the flag toolbar HTML and wire it above a table container
    function insertFlagToolbar(containerId, entityType) {
        const existing = document.getElementById('flagbar_' + containerId);
        if (existing) existing.remove();

        const bar = document.createElement('div');
        bar.className = 'flag-toolbar';
        bar.id = 'flagbar_' + containerId;
        bar.innerHTML = `
            <span class="flag-toolbar-label"><span>0</span> / 0 visible flagged for regen</span>
            <button class="btn-flag-all" onclick="Stats.flagAllVisible('${containerId}','${entityType}',1)">Flag All Visible</button>
            <button class="btn-flag-all unflag" onclick="Stats.flagAllVisible('${containerId}','${entityType}',0)">Clear All</button>
        `;
        const container = document.getElementById(containerId);
        container.insertBefore(bar, container.firstChild);
        updateFlagToolbarLabel(containerId, entityType);
    }

    // ── Sketches no frames ────────────────────────────────────────────────
    async function loadSketchesNoFrames(reset = false) {
        const pg = _pages.sketchesNoFrames;
        if (pg.loading) return;
        if (reset) { pg.offset = 0; document.getElementById('sketchesNoFramesBody').innerHTML = '<div class="table-loading"><div class="mini-spinner"></div> Loading…</div>'; }
        pg.loading = true;

        const res = await api('sketches_no_frames', { limit: pg.limit, offset: pg.offset });
        pg.loading = false;
        if (!res.ok) return;
        pg.total = res.total;
        document.getElementById('dpCountSketchesNoFrames').textContent = fmt(res.total);
        document.getElementById('dpCountSketchesNoFrames').className = 'dp-count' + (res.total === 0 ? ' ok' : '');

        if (reset) document.getElementById('sketchesNoFramesBody').innerHTML = '';

        appendTableRegen('sketchesNoFramesBody', res.data, 'sketches', 'regenerate_images', pg, 'sketchesNoFrames');
    }

    // ── Animatics no videos ───────────────────────────────────────────────
    async function loadAnimaticsNoVideos(reset = false) {
        const pg = _pages.animaticsNoVideos;
        if (pg.loading) return;
        if (reset) { pg.offset = 0; document.getElementById('animaticsNoVideosBody').innerHTML = '<div class="table-loading"><div class="mini-spinner"></div> Loading…</div>'; }
        pg.loading = true;

        const res = await api('animatics_no_videos', { limit: pg.limit, offset: pg.offset });
        pg.loading = false;
        if (!res.ok) return;
        pg.total = res.total;
        document.getElementById('dpCountAnimaticsNoVideos').textContent = fmt(res.total);
        document.getElementById('dpCountAnimaticsNoVideos').className = 'dp-count' + (res.total === 0 ? ' ok' : '');

        if (reset) document.getElementById('animaticsNoVideosBody').innerHTML = '';

        appendTableRegen('animaticsNoVideosBody', res.data, 'animatics', 'regenerate_videos', pg, 'animaticsNoVideos');
    }

    // ── Analysis gaps ─────────────────────────────────────────────────────
    async function loadAnalysisGaps(reset = false) {
        const pg = _pages.analysisGaps;
        if (pg.loading) return;
        if (reset) { pg.offset = 0; document.getElementById('analysisGapsBody').innerHTML = '<div class="table-loading"><div class="mini-spinner"></div> Loading…</div>'; }
        pg.loading = true;

        const res = await api('sketches_no_analysis', { type: _analysisType, limit: pg.limit, offset: pg.offset });
        pg.loading = false;
        if (!res.ok) return;
        pg.total = res.total;
        document.getElementById('dpCountAnalysis').textContent = fmt(res.total);
        document.getElementById('dpCountAnalysis').className = 'dp-count' + (res.total === 0 ? ' ok' : '');

        if (reset) document.getElementById('analysisGapsBody').innerHTML = '';

        appendTable('analysisGapsBody', res.data, [
            { key: 'id',        label: 'ID',      cls: 'td-id' },
            { key: 'name',      label: 'Name',    cls: 'td-name' },
            { key: 'created_at',label: 'Created', cls: 'td-date', fmt: fmtDate },
            { key: 'updated_at',label: 'Updated', cls: 'td-date', fmt: fmtDate },
            { key: 'id',        label: '',        cls: '', fmt: id => `<button class="btn-entity" onclick="Stats.openEntityModal('sketches',${id})" title="Open sketches #${id}"><i class="bi bi-box-arrow-up-right"></i></button>` },
        ], pg, 'analysisGaps');
    }

    function switchAnalysisTab(el) {
        document.querySelectorAll('.analysis-tab').forEach(t => t.classList.remove('active'));
        el.classList.add('active');
        _analysisType = el.dataset.type;
        _pages.analysisGaps.offset = 0;
        loadAnalysisGaps(true);
    }

    // ── Regen-aware table renderer ────────────────────────────────────────
    // Used for tables that have a regen flag column with live checkboxes
    function appendTableRegen(containerId, rows, entityType, regenCol, pg, pgKey) {
        const container = document.getElementById(containerId);

        // Remove load-more
        const existing = container.querySelector('.table-load-more');
        if (existing) existing.remove();

        if (!rows.length && pg.offset === 0) {
            container.innerHTML = '<div class="empty-state"><i class="bi bi-check-circle"></i>All clear — nothing missing here.</div>';
            return;
        }

        if (rows.length) {
            if (pg.offset === 0) {
                container.innerHTML = `
                    <div class="tbl-scroll-wrap">
                        <table class="data-table" id="tbl_${containerId}">
                            <thead><tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Created</th>
                                <th title="Check to queue for regeneration">Regen</th>
                                <th></th>
                            </tr></thead>
                            <tbody id="tbody_${containerId}"></tbody>
                        </table>
                    </div>
                `;
            }

            const tbody = document.getElementById('tbody_' + containerId);
            rows.forEach(row => {
                const tr = document.createElement('tr');
                const isRegen = parseInt(row[regenCol]) === 1;
                tr.innerHTML = `
                    <td class="td-id">#${esc(row.id)}</td>
                    <td class="td-name">${esc(row.name)}</td>
                    <td class="td-date">${fmtDate(row.created_at)}</td>
                    <td class="td-flag" style="text-align:center;">
                        <input type="checkbox" class="regen-chk"
                            data-id="${row.id}"
                            ${isRegen ? 'checked' : ''}
                            title="${isRegen ? 'Queued for regen — click to clear' : 'Click to queue for regen'}">
                    </td>
                    <td style="text-align:center; padding:6px 8px;">
                        <button class="btn-entity" onclick="Stats.openEntityModal('${entityType}',${row.id})" title="Open ${entityType} #${row.id}">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </button>
                    </td>
                `;
                // Wire the checkbox change event
                const chk = tr.querySelector('.regen-chk');
                chk.addEventListener('change', () => {
                    toggleRegenRow(chk, entityType, row.id);
                    updateFlagToolbarLabel(containerId, entityType);
                });
                tbody.appendChild(tr);
            });
            pg.offset += rows.length;

            // Insert/refresh flag toolbar above the table (after first batch or after load-more)
            insertFlagToolbar(containerId, entityType);
        }

        // Load more button
        if (pg.offset < pg.total) {
            const remaining = pg.total - pg.offset;
            const btn = document.createElement('button');
            btn.className = 'table-load-more';
            btn.textContent = `Load more (${remaining.toLocaleString()} remaining)`;
            btn.onclick = () => {
                btn.disabled = true;
                btn.textContent = 'Loading…';
                const loaders = {
                    sketchesNoFrames: () => loadSketchesNoFrames(false),
                    animaticsNoVideos: () => loadAnimaticsNoVideos(false),
                };
                if (loaders[pgKey]) loaders[pgKey]();
            };
            container.appendChild(btn);
        }
    }

    // ── Generic table renderer (no regen) ─────────────────────────────────
    function appendTable(containerId, rows, cols, pg, pgKey) {
        const container = document.getElementById(containerId);

        const existing = container.querySelector('.table-load-more');
        if (existing) existing.remove();

        if (!rows.length && pg.offset === 0) {
            container.innerHTML = '<div class="empty-state"><i class="bi bi-check-circle"></i>All clear — nothing missing here.</div>';
            return;
        }

        if (rows.length) {
            if (pg.offset === 0) {
                const thead = cols.map(c => `<th>${esc(c.label)}</th>`).join('');
                container.innerHTML = `
                    <div class="tbl-scroll-wrap">
                        <table class="data-table" id="tbl_${containerId}">
                            <thead><tr>${thead}</tr></thead>
                            <tbody id="tbody_${containerId}"></tbody>
                        </table>
                    </div>
                `;
            }
            const tbody = document.getElementById('tbody_' + containerId);
            rows.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = cols.map(c => {
                    const raw = row[c.key] ?? '';
                    const val = c.fmt ? c.fmt(raw) : esc(raw);
                    return `<td class="${c.cls || ''}">${val}</td>`;
                }).join('');
                tbody.appendChild(tr);
            });
            pg.offset += rows.length;
        }

        if (pg.offset < pg.total) {
            const remaining = pg.total - pg.offset;
            const btn = document.createElement('button');
            btn.className = 'table-load-more';
            btn.textContent = `Load more (${remaining.toLocaleString()} remaining)`;
            btn.onclick = () => {
                btn.disabled = true;
                btn.textContent = 'Loading…';
                const loaders = {
                    analysisGaps: () => loadAnalysisGaps(false),
                };
                if (loaders[pgKey]) loaders[pgKey]();
            };
            container.appendChild(btn);
        }
    }

    // ── Linkage ───────────────────────────────────────────────────────────
    async function loadLinkage() {
        const res = await api('sketches_linkage');
        if (!res.ok) return;
        const d = res.data;
        const total = d.total || 1;

        document.getElementById('linkageGrid').innerHTML = [
            linkCard('bi-collection-play', d.in_narrative_seq,  'In Narrative Seqs',   'sketches linked to sequences', d.in_narrative_seq / total * 100, '#00d4ff'),
            linkCard('bi-kanban',          d.in_boards,          'In Boards',           'direct + map_run + storyboard items', d.in_boards / total * 100, '#a855f7'),
            linkCard('bi-images',          d.in_storyboards,     'In Storyboards',      'via frame mapping',             d.in_storyboards / total * 100,   '#ff6b2b'),
            linkCard('bi-camera-video',    d.in_editorial,       'In Editorial',        'shots from sketch chain',       d.in_editorial / total * 100,     '#00e5a0'),
        ].join('');

        requestAnimationFrame(() => {
            document.querySelectorAll('.linkage-bar-fill[data-w]').forEach(el => {
                el.style.width = parseFloat(el.dataset.w).toFixed(1) + '%';
            });
        });
    }

    function linkCard(icon, value, label, sub, barW, color) {
        return `
            <div class="linkage-card">
                <div class="linkage-icon" style="color:${color}"><i class="bi ${icon}"></i></div>
                <div class="linkage-value" style="color:${color}">${fmt(value)}</div>
                <div class="linkage-label">${esc(label)}</div>
                <div class="linkage-sub">${esc(sub)}</div>
                <div class="linkage-bar">
                    <div class="linkage-bar-fill" data-w="${Math.min(100, barW).toFixed(1)}" style="background:${color}; width:0%;"></div>
                </div>
            </div>`;
    }

    // ── Map Runs ──────────────────────────────────────────────────────────
    async function loadMapRuns() {
        const res = await api('recent_map_runs', { limit: 20 });
        const body = document.getElementById('mapRunsBody');
        if (!res.ok) { body.innerHTML = '<div class="empty-state">Failed to load</div>'; return; }

        if (!res.data.length) { body.innerHTML = '<div class="empty-state"><i class="bi bi-database-slash"></i>No map runs yet</div>'; return; }

        body.innerHTML = `
            <div class="tbl-scroll-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Note</th>
                            <th>Frames/Videos</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${res.data.map(r => {
                            const count = r.entity_type === 'animatics' ? r.video_count : r.frame_count;
                            const unit  = r.entity_type === 'animatics' ? 'vid' : 'fr';
                            return `
                                <tr>
                                    <td class="td-id">#${r.id}</td>
                                    <td><span class="run-badge ${r.entity_type === 'sketches' ? 'sketches' : r.entity_type === 'animatics' ? 'animatics' : 'other'}">${esc(r.entity_type)}</span></td>
                                    <td class="td-name" style="max-width:220px;">${esc(r.note || '—')}</td>
                                    <td style="font-family:var(--head);font-size:1.1rem;letter-spacing:1px;color:var(--cyan);">${fmt(count)} <span style="font-size:0.6rem;color:var(--text-dim)">${unit}</span></td>
                                    <td class="td-date">${fmtDate(r.created_at)}</td>
                                </tr>`;
                        }).join('')}
                    </tbody>
                </table>
            </div>
        `;
    }

    // ═══════════════════════════════════════════════════════════════════
    // DEEP DIVE MODAL — ID Cluster Analysis
    // ═══════════════════════════════════════════════════════════════════

    let _dd = {
        entityType: 'sketches',
        page: 1,
        totalPages: 1,
        totalClusters: 0,
        totalIds: 0,
        loading: false,
        regenCol: 'regenerate_images',
    };

    function openDeepDive(entityType) {
        _dd.entityType   = entityType;
        _dd.page         = 1;
        _dd.totalPages   = 1;
        _dd.loading      = false;

        const overlay = document.getElementById('ddOverlay');
        const title   = document.getElementById('ddTitle');
        const sub     = document.getElementById('ddSubtitle');
        title.textContent = entityType === 'sketches' ? 'SKETCH GAPS' : 'ANIMATIC GAPS';
        sub.textContent   = entityType === 'sketches'
            ? 'FRAMES MISSING — ID RANGE CLUSTERS'
            : 'VIDEOS MISSING — ID RANGE CLUSTERS';

        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        loadDeepDivePage(1);
    }

    function closeDeepDive() {
        document.getElementById('ddOverlay').classList.remove('open');
        document.body.style.overflow = '';
    }

    async function loadDeepDivePage(page) {
        if (_dd.loading) return;
        _dd.loading = true;
        _dd.page    = page;

        const body = document.getElementById('ddBody');
        body.innerHTML = '<div class="dd-loading"><div class="mini-spinner"></div>Loading clusters…</div>';

        const res = await api('gap_clusters', {
            entity_type: _dd.entityType,
            page,
            per_page: 10,
        });

        _dd.loading = false;

        if (!res.ok) {
            body.innerHTML = `<div class="dd-loading">Error: ${esc(res.error || 'Failed to load')}</div>`;
            return;
        }

        _dd.page          = res.page;
        _dd.totalPages    = res.total_pages;
        _dd.totalClusters = res.total;
        _dd.totalIds      = res.total_ids;
        _dd.regenCol      = res.regen_col;

        // Update pagination UI
        document.getElementById('ddPgInfo').innerHTML =
            `<span>${fmt(res.total)}</span> clusters &nbsp;·&nbsp; <span id="ddTotalIds">${fmt(res.total_ids)}</span> total unmapped IDs`;
        document.getElementById('ddPgInput').value = res.page;
        document.getElementById('ddPgInput').max   = res.total_pages;
        document.getElementById('ddPgSep').textContent = `/ ${res.total_pages}`;
        document.getElementById('ddPgPrev').disabled   = res.page <= 1;
        document.getElementById('ddPgNext').disabled   = res.page >= res.total_pages;

        if (!res.data.length) {
            body.innerHTML = '<div class="dd-loading"><i class="bi bi-check-circle" style="font-size:2rem;opacity:0.4;"></i>No clusters found</div>';
            return;
        }

        body.innerHTML = '';
        res.data.forEach((cluster, ci) => renderCluster(body, cluster, ci));
    }

    function renderCluster(container, cluster, ci) {
        const sizeCls = cluster.count >= 100 ? 'large' : cluster.count >= 20 ? 'medium' : 'small';
        const isSingle = cluster.range_start === cluster.range_end;
        const rangeStr = isSingle
            ? `#${cluster.range_start}`
            : `#${cluster.range_start}<span class="dd-range-sep">→</span>#${cluster.range_end}`;

        // Build queued summary pill for the cluster header
        const queuedSet  = new Set(cluster.queued_ids || []);
        const queuedCount = queuedSet.size;
        const queuedSummaryHtml = queuedCount > 0
            ? `<span class="dd-queued-summary" title="${queuedCount} of ${cluster.count} IDs are pending/processing in the queue"><i class="bi bi-hourglass-split"></i>${queuedCount}</span>`
            : '';

        const card = document.createElement('div');
        card.className = 'dd-cluster';
        card.dataset.clusterId = ci;

        card.innerHTML = `
            <div class="dd-cluster-head">
                <div class="dd-range">${rangeStr}</div>
                <div class="dd-cluster-meta">
                    <div class="dd-cluster-count">${cluster.count} ID${cluster.count !== 1 ? 's' : ''} unmapped</div>
                    <div class="dd-cluster-sample">${esc(cluster.sample_name)}</div>
                </div>
                <div class="dd-cluster-actions">
                    <span class="dd-size-pill ${sizeCls}">${cluster.count}</span>
                    ${queuedSummaryHtml}
                    <button class="dd-flag-btn" data-cluster-idx="${ci}" data-value="1" title="Flag all in this cluster for regen">Flag All</button>
                    <button class="dd-flag-btn clear-btn" data-cluster-idx="${ci}" data-value="0" title="Clear regen flag for all in cluster">Clear</button>
                </div>
                <i class="bi bi-chevron-down dd-cluster-chevron"></i>
            </div>
            <div class="dd-cluster-rows" id="ddRows_${ci}"></div>
        `;

        // Toggle expand/collapse on head click
        const head = card.querySelector('.dd-cluster-head');
        head.addEventListener('click', e => {
            // Don't toggle if clicking action buttons
            if (e.target.closest('.dd-cluster-actions')) return;
            card.classList.toggle('expanded');
            const rowsEl = card.querySelector('.dd-cluster-rows');
            if (card.classList.contains('expanded') && !rowsEl.dataset.built) {
                buildClusterRows(rowsEl, cluster, ci, queuedSet);
                rowsEl.dataset.built = '1';
            }
        });

        // Wire flag/clear buttons
        card.querySelectorAll('.dd-flag-btn').forEach(btn => {
            btn.addEventListener('click', e => {
                e.stopPropagation();
                ddFlagCluster(card, cluster, parseInt(btn.dataset.value), ci);
            });
        });

        container.appendChild(card);
    }

    function buildClusterRows(container, cluster, ci, queuedSet) {
        cluster.ids.forEach(id => {
            const isRegen  = cluster.regen_flags[id] === 1;
            const isQueued = queuedSet.has(id);
            const rowName  = (cluster.names && cluster.names[id]) ? cluster.names[id] : '';

            // Queued pill shown inline next to the name when this specific ID is in the queue
            const queuedPillHtml = isQueued
                ? `<span class="queued-pill" title="Pending or processing in map_run_queue"><i class="bi bi-hourglass-split"></i></span>`
                : '';

            const row = document.createElement('div');
            row.className = 'dd-cluster-row-item';
            row.innerHTML = `
                <span class="dd-row-id">#${id}</span>
                <span class="dd-row-name">${esc(rowName)}</span>
                ${queuedPillHtml}
                <button class="btn-entity" onclick="Stats.openEntityModal('${_dd.entityType}',${id})" title="Open ${_dd.entityType} #${id}">
                    <i class="bi bi-box-arrow-up-right"></i>
                </button>
                <input type="checkbox" class="dd-row-chk"
                    data-id="${id}" data-ci="${ci}"
                    ${isRegen ? 'checked' : ''}
                    title="${isRegen ? 'Queued — click to clear' : 'Click to queue for regen'}">
            `;
            const chk = row.querySelector('.dd-row-chk');
            chk.addEventListener('change', () => {
                cluster.regen_flags[id] = chk.checked ? 1 : 0;
                toggleRegenRow(chk, _dd.entityType, id);
            });
            container.appendChild(row);
        });
    }

    async function ddFlagCluster(cardEl, cluster, value, ci) {
        const btns = cardEl.querySelectorAll('.dd-flag-btn');
        btns.forEach(b => b.classList.add('saving'));

        try {
            const res = await apiPost('set_regen', {
                entity_type: _dd.entityType,
                ids: cluster.ids,
                value,
            });
            if (!res.ok) {
                toast('Batch update failed: ' + (res.error || '?'), '#ff3d5a');
                return;
            }
            // Update local state
            cluster.ids.forEach(id => { cluster.regen_flags[id] = value; });
            cluster.all_flagged  = value === 1;
            cluster.none_flagged = value === 0;

            // Update any expanded row checkboxes
            const rowsEl = document.getElementById('ddRows_' + ci);
            if (rowsEl) {
                rowsEl.querySelectorAll('.dd-row-chk').forEach(chk => { chk.checked = !!value; });
            }

            toast(`${res.affected} item(s) ${value ? 'flagged' : 'cleared'}`, value ? '#00d4ff' : null);
        } finally {
            btns.forEach(b => b.classList.remove('saving'));
        }
    }

    function ddNavigate(dir) {
        const newPage = _dd.page + dir;
        if (newPage >= 1 && newPage <= _dd.totalPages) loadDeepDivePage(newPage);
    }
    
    
    
    
    
window.openEntityModal = function (entityType, entityId) {
    const overlay = document.getElementById('efOverlay');
    const iframe  = document.getElementById('efIframe');
    const title   = document.getElementById('efTitle');

    title.textContent = `${entityType} #${entityId}`;
    iframe.src = `entity_form.php?entity_type=${encodeURIComponent(entityType)}&entity_id=${encodeURIComponent(entityId)}`;
    overlay.classList.add('open');
};

window.closeEntityModal = function () {
    const overlay = document.getElementById('efOverlay');
    const iframe  = document.getElementById('efIframe');
    overlay.classList.remove('open');
    iframe.src = 'about:blank';
};


    
/*
    // ── Entity Form Modal ─────────────────────────────────────────────────
    function openEntityModal(entityType, entityId) {
        const overlay = document.getElementById('efOverlay');
        const iframe  = document.getElementById('efIframe');
        const title   = document.getElementById('efTitle');
        title.textContent = `${entityType} #${entityId}`;
        iframe.src = `entity_form.php?entity_type=${encodeURIComponent(entityType)}&entity_id=${encodeURIComponent(entityId)}`;
        overlay.classList.add('open');
    }

    function closeEntityModal() {
        const overlay = document.getElementById('efOverlay');
        const iframe  = document.getElementById('efIframe');
        overlay.classList.remove('open');
        iframe.src = 'about:blank';
    }
    */

    // Wire the deep dive page input (called from init)
    function _wireDdPageInput() {
        const inp = document.getElementById('ddPgInput');
        if (!inp) return;
        inp.addEventListener('keydown', e => {
            if (e.key === 'Enter') { e.preventDefault(); inp.blur(); }
        });
        inp.addEventListener('blur', () => {
            const v = parseInt(inp.value);
            if (!isNaN(v) && v >= 1 && v <= _dd.totalPages) {
                loadDeepDivePage(v);
            } else {
                inp.value = _dd.page;
            }
        });
    }

    // Close on Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            closeEntityModal();
            closeDeepDive();
        }
    });

    // ── Panel toggle ──────────────────────────────────────────────────────
    function togglePanel(headEl) {
        const panel = headEl.closest('.data-panel');
        panel.classList.toggle('open');
    }

    // ── Init ──────────────────────────────────────────────────────────────
    async function init() {
        startClock();
        _wireDdPageInput();
        await refresh();
    }

    return {
        init,
        refresh,
        togglePanel,
        switchAnalysisTab,
        flagAllVisible,
        openDeepDive,
        closeDeepDive,
        ddNavigate,
        openEntityModal,
        closeEntityModal,
    };
})();

document.addEventListener('DOMContentLoaded', () => Stats.init());
</script>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

<?php // echo $eruda; ?>

</body>
</html>
<?php
$content = ob_get_clean();
// Output directly — this page renders its own full HTML
echo $content;
?>
