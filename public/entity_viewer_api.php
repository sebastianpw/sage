<?php
// public/entity_viewer_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require_once __DIR__ . '/entity_icons.php';

use App\SceneKitchen\KitchenChef;

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$chef = new KitchenChef($pdo);

// Configuration
$LIMIT = 10; // Items per page

try {
    if ($action === 'fetch_content') {
        $type = $_POST['entity_type'] ?? 'characters';
        $mode = $_POST['mode'] ?? 'entity'; // 'entity' or 'map_run'
        $page = max(1, (int)($_POST['page'] ?? 1));
        $offset = ($page - 1) * $LIMIT;
        
        // ID Filters
        $fromId = !empty($_POST['from_id']) ? (int)$_POST['from_id'] : null;
        $toId = !empty($_POST['to_id']) ? (int)$_POST['to_id'] : null;

        $items = [];
        $totalItems = 0;

        // Build ID Clause
        $idWhere = [];
        $idParams = [];
        if ($fromId) {
            $idWhere[] = "id >= :from_id";
            $idParams[':from_id'] = $fromId;
        }
        if ($toId) {
            $idWhere[] = "id <= :to_id";
            $idParams[':to_id'] = $toId;
        }

        // --- MODE A: ENTITY BASED ---
        if ($mode === 'entity') {
            $whereSql = "";
            if (!empty($idWhere)) {
                $whereSql = "WHERE " . implode(" AND ", $idWhere);
            }

            // 1. Count Total
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM `$type` $whereSql");
            foreach($idParams as $k => $v) $countStmt->bindValue($k, $v, PDO::PARAM_INT);
            $countStmt->execute();
            $totalItems = $countStmt->fetchColumn();

            // 2. Fetch Entities
            $stmt = $pdo->prepare("SELECT id, name, created_at FROM `$type` $whereSql ORDER BY id DESC LIMIT :limit OFFSET :offset");
            foreach($idParams as $k => $v) $stmt->bindValue($k, $v, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $LIMIT, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $content = getRichContentForItem($pdo, $chef, $type, $row['id']);
                $items[] = [
                    'id' => $row['id'],
                    'title' => ucfirst(rtrim($type, 's')) . " #{$row['id']} ({$row['name']})",
                    'meta' => $row['created_at'],
                    'frames' => $content['frames'],
                    'videos' => $content['videos'],
                    'type' => $content['type'],
                    'item_type' => $type
                ];
            }
        } 
        // --- MODE B: MAP RUN BASED ---
        else {
            $mrWhere = ["entity_type = :type"];
            $mrParams = [':type' => $type];
            if ($fromId) { $mrWhere[] = "id >= :from_id"; $mrParams[':from_id'] = $fromId; }
            if ($toId) { $mrWhere[] = "id <= :to_id"; $mrParams[':to_id'] = $toId; }
            $whereSql = "WHERE " . implode(" AND ", $mrWhere);

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM map_runs $whereSql");
            foreach($mrParams as $k => $v) $countStmt->bindValue($k, $v);
            $countStmt->execute();
            $totalItems = $countStmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT id, created_at, note FROM map_runs $whereSql ORDER BY id DESC LIMIT :limit OFFSET :offset");
            foreach($mrParams as $k => $v) $stmt->bindValue($k, $v);
            $stmt->bindValue(':limit', $LIMIT, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as $row) {
                $content = getRichContentForItem($pdo, $chef, 'map_run', $row['id']);
                $items[] = [
                    'id' => $row['id'],
                    'title' => "Map Run #{$row['id']} (" . ($row['note'] ?: $type) . ")",
                    'meta' => $row['created_at'],
                    'frames' => $content['frames'],
                    'videos' => $content['videos'],
                    'type' => $content['type'],
                    'item_type' => 'map_run'
                ];
            }
        }

        echo json_encode([
            'ok' => true, 
            'items' => $items, 
            'total_pages' => ceil($totalItems / $LIMIT),
            'current_page' => $page
        ]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

/**
 * Shared Helper: Fetches content (Frames/Videos) + Metadata
 */
function getRichContentForItem($pdo, $chef, $type, $id) {
    $result = ['frames' => [], 'videos' => [], 'type' => 'image'];

    // 1. MAP RUN LOGIC
    if ($type === 'map_run') {
        $vStmt = $pdo->prepare("SELECT * FROM videos WHERE map_run_id = ? ORDER BY sort_order ASC, id ASC");
        $vStmt->execute([$id]);
        $videos = $vStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($videos)) {
            $result['type'] = 'video';
            $result['videos'] = $videos;
            return $result; 
        }
        $sql = getFramesSql('f.map_run_id = :id');
    } 
    // 2. ENTITY LOGIC
    else {
        $linkTable = "frames_2_{$type}";
        try {
            $check = $pdo->query("SHOW TABLES LIKE '$linkTable'");
            if ($check->rowCount() === 0) return $result;
        } catch (Exception $e) { return $result; }
        $sql = getFramesSql("l.to_id = :id", "JOIN `$linkTable` l ON f.id = l.from_id");
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($frames)) {
        // Hydrate Ingredients (Meta Pills)
        $sketchIds = [];
        foreach ($frames as $f) {
            if (($f['entity_type'] ?? '') === 'sketches' && ($f['entity_id'] ?? 0) > 0) $sketchIds[] = $f['entity_id'];
        }
        $sketchIds = array_values(array_unique($sketchIds));

        $ingredientsMap = [];
        $genTitles = [];

        if (!empty($sketchIds)) {
            $inQuery = implode(',', array_fill(0, count($sketchIds), '?'));
            
            $ingStmt = $pdo->prepare("SELECT * FROM sketch_ingredients WHERE sketch_id IN ($inQuery) ORDER BY sort_order ASC");
            $ingStmt->execute($sketchIds);
            $genConfigIds = [];
            while ($row = $ingStmt->fetch(PDO::FETCH_ASSOC)) {
                $ingredientsMap[$row['sketch_id']][] = $row;
                if (strpos($row['ingredient_type'], 'generator_config') !== false && $row['source_id']) {
                    $genConfigIds[] = $row['source_id'];
                }
            }

            if (!empty($genConfigIds)) {
                $genConfigIds = array_values(array_unique($genConfigIds));
                $inGen = implode(',', array_fill(0, count($genConfigIds), '?'));
                $genStmt = $pdo->prepare("SELECT id, title FROM generator_config WHERE id IN ($inGen)");
                $genStmt->execute($genConfigIds);
                while ($gRow = $genStmt->fetch(PDO::FETCH_ASSOC)) {
                    $genTitles[$gRow['id']] = $gRow['title'];
                }
            }
        }

        foreach ($frames as &$f) {
            $f['normalized_ingredients'] = [];
            $sid = $f['entity_id'];

            if (isset($ingredientsMap[$sid])) {
                foreach ($ingredientsMap[$sid] as $ing) {
                    $snap = json_decode($ing['snapshot_data'] ?? '[]', true);
                    $label = $snap['name'] ?? ucfirst(str_replace('_', ' ', $ing['ingredient_type']));
                    
                    if(strpos($ing['ingredient_type'], 'generator_config') !== false) {
                        $t = $genTitles[$ing['source_id']] ?? 'Unknown';
                        $label = ($ing['ingredient_type'] === 'generator_config_desc' ? "Gen (Desc)" : "Gen (Name)") . ": $t";
                    }

                    $f['normalized_ingredients'][] = [
                        'type' => $ing['ingredient_type'],
                        'label' => $label,
                        'detail' => $ing['prompt_fragment'] ?? ''
                    ];
                }
            }
            
            // Legacy Meta
            if (empty($f['normalized_ingredients']) && !empty($f['meta_id'])) {
                if ($f['gen_config_title']) $f['normalized_ingredients'][] = ['type' => 'generator_config', 'label' => 'Generator', 'detail' => $f['gen_config_title']];
                if ($f['template_name']) $f['normalized_ingredients'][] = ['type' => 'sketch_template', 'label' => 'Template: ' . $f['template_name'], 'detail' => $f['template_core']];
                if ($f['interaction_name']) $f['normalized_ingredients'][] = ['type' => 'interaction', 'label' => 'Interaction', 'detail' => $f['interaction_name']];
            }

            // ANALYSIS / CURATION DATA (Replicates view_map_runs_sketches.php logic)
            $f['curation'] = null;
            if (!empty($f['classification'])) {
                $f['curation'] = [
                    'score' => $f['overall_quality'],
                    'class' => json_decode($f['classification'], true),
                    'score_breakdown' => json_decode($f['scoring'], true),
                    'entities' => json_decode($f['entities'], true),
                    'themes' => json_decode($f['thematics'], true),
                    'recs' => json_decode($f['recommendations'], true)
                ];
            }
        }
    }

    $result['frames'] = $frames;
    return $result;
}

function getFramesSql($where, $join = '') {
    return <<<SQL
    SELECT 
        f.id as frame_id, f.name as frame_name, f.filename, f.prompt, f.entity_type, f.entity_id,
        g.name as generative_name, ie.tool as edit_tool, ie.note as edit_note, f.img2img_frame_id,
        ms.id as meta_id, s.description as full_sketch_desc,
        gc.title as gen_config_title, st.name as template_name, st.core_idea as template_core,
        st.shot_type, st.camera_angle, st.perspective, intr.name as interaction_name,
        -- JOINED CURATION DATA
        sa.overall_quality,
        sa.classification,
        sa.scoring,
        sa.entities,
        sa.thematics,
        sa.recommendations
    FROM frames f
    $join
    LEFT JOIN generatives g ON g.id = f.entity_id AND f.entity_type = 'generatives'
    LEFT JOIN image_edits ie ON ie.derived_frame_id = f.id
    LEFT JOIN sketches s ON f.entity_id = s.id AND f.entity_type = 'sketches'
    LEFT JOIN meta_sketches ms ON s.id = ms.sketch_id
    LEFT JOIN generator_config gc ON ms.desc_gen_config_id = gc.id
    LEFT JOIN sketch_templates st ON ms.sketch_template_id = st.id
    LEFT JOIN interactions intr ON ms.interaction_id = intr.id
    LEFT JOIN sketch_analysis sa ON s.id = sa.sketch_id
    WHERE $where
    ORDER BY f.id ASC
SQL;
}