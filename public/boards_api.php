<?php
// public/boards_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require_once __DIR__ . '/entity_icons.php';

use App\SceneKitchen\KitchenChef;

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$chef = new KitchenChef($pdo);

try {
    // ... [Previous actions: list_boards, fetch_tree, move_node, create_category, create_board, add_item, remove_item, regenerate_run] ...

    // --- LIST BOARDS ---
    if ($action === 'list_boards') {
        $stmt = $pdo->query("SELECT b.id, b.name, c.name as category_name FROM boards b LEFT JOIN boards_categories c ON b.category_id = c.id WHERE b.status = 'active' ORDER BY c.sort_order, b.name");
        echo json_encode(['ok' => true, 'boards' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        exit;
    }

    if ($action === 'fetch_tree') {
        $catStmt = $pdo->query("SELECT id, parent_id, name FROM boards_categories ORDER BY sort_order ASC, name ASC");
        $nodes = [];
        while($row = $catStmt->fetch(PDO::FETCH_ASSOC)) {
            $nodes[] = ['id' => 'c_' . $row['id'], 'parent' => $row['parent_id'] ? 'c_' . $row['parent_id'] : '#', 'text' => $row['name'], 'icon' => 'bi bi-folder2', 'type' => 'folder', 'data' => ['db_id' => $row['id'], 'type' => 'category']];
        }
        $boardStmt = $pdo->query("SELECT id, category_id, name FROM boards WHERE status = 'active' ORDER BY name ASC");
        while($row = $boardStmt->fetch(PDO::FETCH_ASSOC)) {
            $parent = $row['category_id'] ? 'c_' . $row['category_id'] : '#';
            $nodes[] = ['id' => 'b_' . $row['id'], 'parent' => $parent, 'text' => $row['name'], 'icon' => 'bi bi-kanban', 'type' => 'board', 'data' => ['db_id' => $row['id'], 'type' => 'board']];
        }
        echo json_encode(['ok' => true, 'tree' => $nodes]);
        exit;
    }

    if ($action === 'move_node') {
        $nodeId = $_POST['id'] ?? '';
        $parentId = $_POST['parent'] ?? '#';
        if (!$nodeId) throw new Exception("Missing Node ID");
        $dbParentId = ($parentId !== '#' && strpos($parentId, 'c_') === 0) ? (int)substr($parentId, 2) : null;
        if (strpos($nodeId, 'c_') === 0) {
            $stmt = $pdo->prepare("UPDATE boards_categories SET parent_id = ? WHERE id = ?");
            $stmt->execute([$dbParentId, (int)substr($nodeId, 2)]);
        } elseif (strpos($nodeId, 'b_') === 0) {
            $stmt = $pdo->prepare("UPDATE boards SET category_id = ? WHERE id = ?");
            $stmt->execute([$dbParentId, (int)substr($nodeId, 2)]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'create_category') {
        $name = trim($_POST['name'] ?? '');
        $parentId = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        if (!$name) throw new Exception("Folder name required");
        $stmt = $pdo->prepare("INSERT INTO boards_categories (name, parent_id) VALUES (?, ?)");
        $stmt->execute([$name, $parentId]);
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    if ($action === 'create_board') {
        $name = trim($_POST['name'] ?? '');
        $catId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
        if (!$name) throw new Exception("Name required");
        $stmt = $pdo->prepare("INSERT INTO boards (name, category_id) VALUES (?, ?)");
        $stmt->execute([$name, $catId]);
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    if ($action === 'add_item') {
        $boardId = (int)$_POST['board_id'];
        $type = $_POST['item_type'] ?? '';
        $itemId = (int)$_POST['item_id'];
        if (!$boardId || !$type || !$itemId) throw new Exception("Missing parameters");
        $check = $pdo->prepare("SELECT id FROM boards_items WHERE board_id=? AND item_type=? AND item_id=?");
        $check->execute([$boardId, $type, $itemId]);
        if ($check->fetch()) { echo json_encode(['ok' => true, 'message' => 'Already on board']); exit; }
        $max = $pdo->prepare("SELECT MAX(sort_order) FROM boards_items WHERE board_id=?");
        $max->execute([$boardId]);
        $nextOrder = ((int)$max->fetchColumn()) + 1;
        $stmt = $pdo->prepare("INSERT INTO boards_items (board_id, item_type, item_id, sort_order) VALUES (?, ?, ?, ?)");
        $stmt->execute([$boardId, $type, $itemId, $nextOrder]);
        echo json_encode(['ok' => true, 'id' => $pdo->lastInsertId()]);
        exit;
    }

    if ($action === 'remove_item') {
        $id = (int)$_POST['item_id'];
        $stmt = $pdo->prepare("DELETE FROM boards_items WHERE id = ?");
        $stmt->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'regenerate_run') {
        $map_run_id = (int)$_POST['map_run_id'];
        $stmt = $pdo->prepare("SELECT DISTINCT entity_id FROM frames WHERE map_run_id = :mid AND entity_type = 'sketches'");
        $stmt->execute(['mid' => $map_run_id]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($ids)) {
            $inQuery = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE sketches SET regenerate_images = 1 WHERE id IN ($inQuery)")->execute($ids);
        }
        echo json_encode(['ok' => true, 'count' => count($ids)]);
        exit;
    }

    // --- FETCH BOARD CONTENTS ---
    if ($action === 'fetch_board_content') {
        $boardId = (int)$_POST['board_id'];
        
        $bStmt = $pdo->prepare("SELECT name FROM boards WHERE id = ?");
        $bStmt->execute([$boardId]);
        $boardName = $bStmt->fetchColumn();

        $cStmt = $pdo->prepare("SELECT COUNT(*) FROM boards_items WHERE board_id = ?");
        $cStmt->execute([$boardId]);
        $totalItems = (int)$cStmt->fetchColumn();
        
        $page = isset($_POST['page']) ? max(1, (int)$_POST['page']) : 1;
        $limit = 5;
        $totalPages = $totalItems > 0 ? ceil($totalItems / $limit) : 1;
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $limit;

        $stmt = $pdo->prepare("
            SELECT bi.id as link_id, bi.item_type, bi.item_id, bi.note as production_note
            FROM boards_items bi
            WHERE bi.board_id = :board_id
            ORDER BY bi.created_at DESC, bi.id DESC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':board_id', $boardId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $runs = [];
        $docs = [];
        $fuzz_candidates = [];
        $narrative_sequences = [];
        $ag_nodes = [];
        $kg_nodes = [];

        // Allowed Entity Types for lookup
        $entityTypes = ['characters', 'character_poses', 'animas', 'locations', 'backgrounds', 'artifacts', 'vehicles', 'generatives', 'composites', 'sketches'];

        foreach ($items as $item) {
            $type = $item['item_type'];
            
            // MAP RUNS
            if ($type === 'map_run') {
                $mrStmt = $pdo->prepare("SELECT * FROM map_runs WHERE id = ?");
                $mrStmt->execute([$item['item_id']]);
                $run = $mrStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($run) {
                    $runData = getRichContentForItem($pdo, $chef, 'map_run', (int)$run['id']);
                    $run['frames'] = $runData['frames'];
                    $run['videos'] = $runData['videos'];
                    $run['type']   = $runData['type']; 
                    $run['link_id'] = $item['link_id']; 
                    $run['item_type'] = 'map_run';
                    $runs[] = $run;
                }
            }
            // STORYBOARDS
            elseif ($type === 'storyboard') {
                $sbStmt = $pdo->prepare("SELECT * FROM storyboards WHERE id = ?");
                $sbStmt->execute([$item['item_id']]);
                $sb = $sbStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($sb) {
                    $sbData = getRichContentForItem($pdo, $chef, 'storyboard', (int)$sb['id']);
                    $sb['frames'] = $sbData['frames'];
                    $sb['type'] = 'image'; 
                    $sb['link_id'] = $item['link_id'];
                    $sb['item_type'] = 'storyboard';
                    $runs[] = $sb;
                }
            }
            // DOCUMENTS
            elseif ($type === 'md_doc') {
                $docStmt = $pdo->prepare("SELECT id, name, updated_at, LEFT(content, 300) as preview FROM documentations WHERE id = ?");
                $docStmt->execute([$item['item_id']]);
                $doc = $docStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($doc) {
                    $doc['link_id'] = $item['link_id'];
                    $docs[] = $doc;
                }
            }
            // FUZZ CANDIDATES
            elseif ($type === 'fuzz_candidate') {
                $fcStmt = $pdo->prepare("SELECT id, label, concept_type, status, notes FROM fuzz_candidates WHERE id = ?");
                $fcStmt->execute([$item['item_id']]);
                $fc = $fcStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($fc) {
                    $fc['link_id'] = $item['link_id'];
                    // Use notes as description preview if available
                    $fc['preview'] = $fc['notes'] ? mb_substr(strip_tags($fc['notes']), 0, 200) : null;
                    $fuzz_candidates[] = $fc;
                }
            }
            // NARRATIVE SEQUENCES
            elseif ($type === 'narrative_sequence') {
                $nsStmt = $pdo->prepare("SELECT id, name FROM narrative_sequences WHERE id = ?");
                $nsStmt->execute([$item['item_id']]);
                $ns = $nsStmt->fetch(PDO::FETCH_ASSOC);
                $narrative_sequences[] = [
                    'seq_id'  => (int)$item['item_id'],
                    'link_id' => (int)$item['link_id'],
                    'name'    => $ns ? $ns['name'] : null,
                ];
            }
            // KG NODES
            elseif ($type === 'kg_node') {
                $kgStmt = $pdo->prepare("SELECT id, name FROM kg_nodes WHERE id = ?");
                $kgStmt->execute([$item['item_id']]);
                $kg = $kgStmt->fetch(PDO::FETCH_ASSOC);
                if ($kg) {
                    $kg['link_id'] = $item['link_id'];
                    $kg_nodes[] = $kg;
                }
            }
            // AG NODES
            elseif ($type === 'ag_node') {
                $agStmt = $pdo->prepare("SELECT id, name, doc_id FROM ag_nodes WHERE id = ?");
                $agStmt->execute([$item['item_id']]);
                $ag = $agStmt->fetch(PDO::FETCH_ASSOC);
                if ($ag) {
                    $ag['link_id'] = $item['link_id'];
                    $ag_nodes[] = $ag;
                }
            }
            // ENTITIES (Characters, Locations, etc.)
            elseif (in_array($type, $entityTypes)) {
                // Fetch Entity Basic Info
                $entStmt = $pdo->prepare("SELECT name, description, created_at FROM `$type` WHERE id = ?");
                $entStmt->execute([$item['item_id']]);
                $ent = $entStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($ent) {
                    // Fetch Frames via Link Table
                    $entData = getRichContentForItem($pdo, $chef, $type, (int)$item['item_id']);
                    $ent['id'] = $item['item_id']; // Ensure ID is set for UI
                    $ent['frames'] = $entData['frames'];
                    $ent['type'] = 'image';
                    $ent['link_id'] = $item['link_id'];
                    $ent['item_type'] = $type; // e.g. 'characters'
                    $runs[] = $ent;
                }
            }
        }

        echo json_encode(['ok' => true, 'runs' => $runs, 'docs' => $docs, 'fuzz_candidates' => $fuzz_candidates, 'narrative_sequences' => $narrative_sequences, 'ag_nodes' => $ag_nodes, 'kg_nodes' => $kg_nodes, 'board_name' => $boardName, 'page' => $page, 'total_pages' => $totalPages], JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

/**
 * Helper: Fetches content for Map Runs, Storyboards, or Entities
 */
function getRichContentForItem($pdo, $chef, $type, $id) {
    $result = ['frames' => [], 'videos' => [], 'type' => 'image'];

    // --- CASE A: MAP RUN ---
    if ($type === 'map_run') {
        // 1. Check for Videos
        $vStmt = $pdo->prepare("SELECT * FROM videos WHERE map_run_id = ? ORDER BY sort_order ASC, id ASC");
        $vStmt->execute([$id]);
        $videos = $vStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($videos)) {
            $result['type'] = 'video';
            $result['videos'] = $videos;
            return $result; 
        }

        // 2. Fetch Frames
        $sql = <<<SQL
        SELECT 
            f.id as frame_id, f.name as frame_name, f.filename, f.prompt, f.entity_type, f.entity_id,
            g.name as generative_name, ie.tool as edit_tool, ie.note as edit_note, f.img2img_frame_id,
            ms.id as meta_id, s.description as full_sketch_desc,
            gc.title as gen_config_title, st.name as template_name, st.core_idea as template_core,
            st.shot_type, st.camera_angle, st.perspective, intr.name as interaction_name
        FROM frames f
        LEFT JOIN generatives g ON g.id = f.entity_id AND f.entity_type = 'generatives'
        LEFT JOIN image_edits ie ON ie.derived_frame_id = f.id
        LEFT JOIN sketches s ON f.entity_id = s.id AND f.entity_type = 'sketches'
        LEFT JOIN meta_sketches ms ON s.id = ms.sketch_id
        LEFT JOIN generator_config gc ON ms.desc_gen_config_id = gc.id
        LEFT JOIN sketch_templates st ON ms.sketch_template_id = st.id
        LEFT JOIN interactions intr ON ms.interaction_id = intr.id
        WHERE f.map_run_id = :id
        ORDER BY f.id ASC
SQL;
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } 
    // --- CASE B: STORYBOARD ---
    elseif ($type === 'storyboard') {
        $sql = <<<SQL
        SELECT 
            f.id as frame_id, 
            sf.id as sb_frame_id,
            sf.name as frame_name, 
            sf.filename, 
            sf.description as prompt,
            f.entity_type, 
            f.entity_id,
            f.img2img_frame_id
        FROM storyboard_frames sf
        LEFT JOIN frames f ON sf.frame_id = f.id
        WHERE sf.storyboard_id = :id
        ORDER BY sf.sort_order ASC
SQL;
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    // --- CASE C: ENTITY (e.g. Character) ---
    else {
        // Construct Link Table Name (e.g. frames_2_characters)
        $linkTable = "frames_2_{$type}";
        
        // Check if table exists to be safe
        try {
            $check = $pdo->query("SHOW TABLES LIKE '$linkTable'");
            if ($check->rowCount() > 0) {
                // Fetch linked frames
                $sql = <<<SQL
                SELECT 
                    f.id as frame_id, f.name as frame_name, f.filename, f.prompt, f.entity_type, f.entity_id,
                    g.name as generative_name, ie.tool as edit_tool, ie.note as edit_note, f.img2img_frame_id,
                    ms.id as meta_id, s.description as full_sketch_desc,
                    gc.title as gen_config_title, st.name as template_name, st.core_idea as template_core,
                    st.shot_type, st.camera_angle, st.perspective, intr.name as interaction_name
                FROM frames f
                JOIN `$linkTable` l ON f.id = l.from_id
                LEFT JOIN generatives g ON g.id = f.entity_id AND f.entity_type = 'generatives'
                LEFT JOIN image_edits ie ON ie.derived_frame_id = f.id
                LEFT JOIN sketches s ON f.entity_id = s.id AND f.entity_type = 'sketches'
                LEFT JOIN meta_sketches ms ON s.id = ms.sketch_id
                LEFT JOIN generator_config gc ON ms.desc_gen_config_id = gc.id
                LEFT JOIN sketch_templates st ON ms.sketch_template_id = st.id
                LEFT JOIN interactions intr ON ms.interaction_id = intr.id
                WHERE l.to_id = :id
                ORDER BY f.id DESC
SQL;
                $stmt = $pdo->prepare($sql);
                $stmt->execute(['id' => $id]);
                $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $frames = []; // Table doesn't exist
            }
        } catch (Exception $e) {
            $frames = [];
        }
    }

    // --- SHARED: Hydrate Flexible Ingredients ---
    if (!empty($frames) && $type !== 'storyboard') { // Storyboards use their own meta or source meta
        $sketchIds = [];
        foreach ($frames as $f) {
            if (($f['entity_type'] ?? '') === 'sketches' && ($f['entity_id'] ?? 0) > 0) $sketchIds[] = $f['entity_id'];
        }
        $sketchIds = array_values(array_unique($sketchIds));

        $ingredientsMap = [];
        $genConfigIds = []; 

        if (!empty($sketchIds)) {
            $inQuery = implode(',', array_fill(0, count($sketchIds), '?'));
            $ingStmt = $pdo->prepare("SELECT * FROM sketch_ingredients WHERE sketch_id IN ($inQuery) ORDER BY sort_order ASC");
            $ingStmt->execute($sketchIds);
            while ($row = $ingStmt->fetch(PDO::FETCH_ASSOC)) {
                $ingredientsMap[$row['sketch_id']][] = $row;
                if (strpos($row['ingredient_type'], 'generator_config') !== false && $row['source_id']) {
                    $genConfigIds[] = $row['source_id'];
                }
            }
        }

        $genTitles = [];
        if (!empty($genConfigIds)) {
            $genConfigIds = array_values(array_unique($genConfigIds));
            $inGen = implode(',', array_fill(0, count($genConfigIds), '?'));
            $genStmt = $pdo->prepare("SELECT id, title FROM generator_config WHERE id IN ($inGen)");
            $genStmt->execute($genConfigIds);
            while ($gRow = $genStmt->fetch(PDO::FETCH_ASSOC)) {
                $genTitles[$gRow['id']] = $gRow['title'];
            }
        }

        foreach ($frames as &$f) {
            $f['normalized_ingredients'] = [];
            $sid = $f['entity_id'];

            if (isset($ingredientsMap[$sid])) {
                foreach ($ingredientsMap[$sid] as $ing) {
                    $snap = json_decode($ing['snapshot_data'] ?? '[]', true);
                    $label = $snap['name'] ?? ucfirst(str_replace('_', ' ', $ing['ingredient_type']));
                    
                    if($ing['ingredient_type'] === 'generator_config_desc') $label = "Gen (Desc): " . ($genTitles[$ing['source_id']] ?? 'Unknown');
                    if($ing['ingredient_type'] === 'generator_config_name') $label = "Gen (Name): " . ($genTitles[$ing['source_id']] ?? 'Unknown');

                    $f['normalized_ingredients'][] = [
                        'type' => $ing['ingredient_type'],
                        'label' => $label,
                        'detail' => $ing['prompt_fragment'] ?? ''
                    ];
                }
            }

            if (empty($f['normalized_ingredients']) && !empty($f['meta_id'])) {
                if ($f['gen_config_title']) $f['normalized_ingredients'][] = ['type' => 'generator_config', 'label' => 'Generator', 'detail' => $f['gen_config_title']];
                if ($f['template_name']) $f['normalized_ingredients'][] = ['type' => 'sketch_template', 'label' => 'Template: ' . $f['template_name'], 'detail' => $f['template_core']];
                if ($f['interaction_name']) $f['normalized_ingredients'][] = ['type' => 'interaction', 'label' => 'Interaction', 'detail' => $f['interaction_name']];
            }
        }
    }

    $result['frames'] = $frames;
    return $result;
}
?>