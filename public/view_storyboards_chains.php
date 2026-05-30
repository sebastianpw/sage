<?php
// public/view_storyboards_chains.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\UI\Modules\ModuleRegistry;

// --- 0. AJAX Handlers ---

// Handler for Regenerate Sketches Flagging in Storyboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'regenerate_storyboard') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    $sb_id = filter_input(INPUT_POST, 'storyboard_id', FILTER_VALIDATE_INT);

    if ($sb_id) {
        try {
            // Find all sketch entity IDs linked to frames in this storyboard
            $stmt = $pdo->prepare("
                SELECT DISTINCT f.entity_id 
                FROM storyboard_frames sf
                JOIN frames f ON sf.frame_id = f.id
                WHERE sf.storyboard_id = :sbid AND f.entity_type = 'sketches'
            ");
            $stmt->execute(['sbid' => $sb_id]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($ids)) {
                $inQuery = implode(',', array_fill(0, count($ids), '?'));
                $upStmt = $pdo->prepare("UPDATE sketches SET regenerate_images = 1 WHERE id IN ($inQuery)");
                $upStmt->execute($ids);
            }
            
            echo json_encode(['success' => true, 'count' => count($ids)]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    }
    exit;
}

// Handler for Storyboard Description Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_description') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    $sb_id = filter_input(INPUT_POST, 'storyboard_id', FILTER_VALIDATE_INT);
    $note = isset($_POST['note']) ? trim($_POST['note']) : '';

    if ($sb_id) {
        try {
            $stmt = $pdo->prepare("UPDATE storyboards SET description = :note WHERE id = :id");
            $success = $stmt->execute(['note' => $note, 'id' => $sb_id]);
            echo json_encode(['success' => $success]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid ID']);
    }
    exit;
}

// --- 1. Setup, Search & Pagination ---

$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
$search = trim(filter_input(INPUT_GET, 'search', FILTER_DEFAULT) ?? '');
$limit = 5;
$offset = ($page - 1) * $limit;

// Base Condition
$whereClause = "WHERE 1=1";
$params =[];

// Search Logic
if ($search !== '') {
    $whereClause .= " AND (
        s.id = :search_int 
        OR s.name LIKE :search_wild 
        OR s.description LIKE :search_wild 
        OR EXISTS (
            SELECT 1 FROM storyboard_frames sf 
            LEFT JOIN frames f ON sf.frame_id = f.id
            WHERE sf.storyboard_id = s.id 
            AND (f.prompt LIKE :search_wild OR sf.name LIKE :search_wild OR sf.description LIKE :search_wild)
        )
    )";
    $params['search_int'] = (int)$search;
    $params['search_wild'] = "%$search%";
}

// Count Total
$countSql = "SELECT COUNT(*) FROM storyboards s $whereClause";
$stmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
$stmt->execute();
$total_storyboards = $stmt->fetchColumn();
$total_pages = ceil($total_storyboards / $limit);
if ($total_pages < 1) $total_pages = 1;

// Fetch Storyboards
$sql = <<<SQL
    SELECT s.id, s.created_at, s.name, s.description 
    FROM storyboards s
    $whereClause
    ORDER BY s.id DESC 
    LIMIT :limit OFFSET :offset
SQL;

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$storyboards = $stmt->fetchAll(PDO::FETCH_ASSOC);

$iconChar = '🎬';

// --- 2. UI Module Setup ---
$registry = ModuleRegistry::getInstance();
$entities_with_menu =['characters', 'character_poses', 'animas', 'locations', 'backgrounds', 'artifacts', 'vehicles', 'scene_parts', 'controlnet_maps', 'spawns', 'generatives', 'sketches', 'prompt_matrix_blueprints', 'composites', 'storyboards'];
$gearMenu = $registry->create('gear_menu',[
    'position' => 'top-right',
    'icon' => '&#9881;',
    'icon_size' => '1.5em',
    'show_for_entities' => $entities_with_menu,
]);
foreach ($entities_with_menu as $entity_name) {
    $gearMenu->addStandardActions($entity_name);
}
$imageEditor = $registry->create('image_editor');

ob_start();
require __DIR__ . '/modal_frame_details.php';
$frameDetailsModal = ob_get_clean();

// Helper Function to get Storyboard Frames + Metadata
function getFramesForStoryboard($pdo, $storyboard_id) {
    // 1. Fetch Frames & Legacy Meta & FULL Curation Data
    $sql = <<<SQL
    SELECT 
        sf.id as sb_frame_id,
        sf.frame_id as frame_id,
        sf.name as sb_frame_name,
        sf.description as sb_description,
        sf.filename as sb_filename,
        sf.sort_order,
        f.name as frame_name,
        f.filename as original_filename,
        f.prompt,
        f.entity_type,
        f.entity_id,
        g.name as generative_name,
        ie.tool as edit_tool,
        ie.note as edit_note,
        f.img2img_frame_id,
        
        -- Legacy Meta Sketches Information
        ms.id as meta_id,
        sk.description as full_sketch_desc,
        gc.title as gen_config_title,
        st.name as template_name,
        st.core_idea as template_core,
        st.shot_type,
        st.camera_angle,
        st.perspective,
        intr.name as interaction_name,
        
        -- Curation Data (COMPLETE)
        sa.overall_quality,
        sa.classification,
        sa.scoring,
        sa.entities,
        sa.thematics,
        sa.recommendations

    FROM storyboard_frames sf
    LEFT JOIN frames f ON sf.frame_id = f.id
    LEFT JOIN generatives g ON g.id = f.entity_id AND f.entity_type = 'generatives'
    LEFT JOIN image_edits ie ON ie.derived_frame_id = f.id
    
    -- Joins for Sketches Legacy Meta
    LEFT JOIN sketches sk ON f.entity_id = sk.id AND f.entity_type = 'sketches'
    LEFT JOIN meta_sketches ms ON sk.id = ms.sketch_id
    LEFT JOIN generator_config gc ON ms.desc_gen_config_id = gc.id
    LEFT JOIN sketch_templates st ON ms.sketch_template_id = st.id
    LEFT JOIN interactions intr ON ms.interaction_id = intr.id
    
    -- Join for Curation
    LEFT JOIN sketch_analysis sa ON sk.id = sa.sketch_id

    WHERE sf.storyboard_id = :sbid
    ORDER BY sf.sort_order ASC
SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['sbid' => $storyboard_id]);
    $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($frames)) return[];

    // 2. Fetch New Flexible Ingredients (Only for sketches)
    $sketchIds =[];
    foreach ($frames as $f) {
        if ($f['entity_type'] === 'sketches' && $f['entity_id'] > 0) {
            $sketchIds[] = $f['entity_id'];
        }
    }
    $sketchIds = array_values(array_unique($sketchIds));

    $ingredientsMap =[];
    $genConfigIds =[]; 

    if (!empty($sketchIds)) {
        $inQuery = implode(',', array_fill(0, count($sketchIds), '?'));
        $ingStmt = $pdo->prepare("SELECT * FROM sketch_ingredients WHERE sketch_id IN ($inQuery) ORDER BY sort_order ASC");
        $ingStmt->execute($sketchIds);
        while ($row = $ingStmt->fetch(PDO::FETCH_ASSOC)) {
            $ingredientsMap[$row['sketch_id']][] = $row;
            if (($row['ingredient_type'] === 'generator_config_desc' || $row['ingredient_type'] === 'generator_config_name') && $row['source_id']) {
                $genConfigIds[] = $row['source_id'];
            }
        }
    }

    $genTitles =[];
    if (!empty($genConfigIds)) {
        $genConfigIds = array_values(array_unique($genConfigIds));
        $inGenQuery = implode(',', array_fill(0, count($genConfigIds), '?'));
        $genStmt = $pdo->prepare("SELECT id, title FROM generator_config WHERE id IN ($inGenQuery)");
        $genStmt->execute($genConfigIds);
        while ($gRow = $genStmt->fetch(PDO::FETCH_ASSOC)) {
            $genTitles[$gRow['id']] = $gRow['title'];
        }
    }

    // 3. Merge & Normalize Metadata
    foreach ($frames as &$f) {
        $f['normalized_ingredients'] =[];
        $sid = $f['entity_id'];

        // A. Add New Flexible Ingredients
        if (isset($ingredientsMap[$sid])) {
            foreach ($ingredientsMap[$sid] as $ing) {
                $snap = json_decode($ing['snapshot_data'] ?? '[]', true);
                $label = $snap['name'] ?? ucfirst(str_replace('_', ' ', $ing['ingredient_type']));
                
                if($ing['ingredient_type'] === 'generator_config_desc') {
                    $t = $genTitles[$ing['source_id']] ?? 'Unknown';
                    $label = "Gen (Desc): $t";
                }
                if($ing['ingredient_type'] === 'generator_config_name') {
                    $t = $genTitles[$ing['source_id']] ?? 'Unknown';
                    $label = "Gen (Name): $t";
                }

                $f['normalized_ingredients'][] =[
                    'type' => $ing['ingredient_type'],
                    'label' => $label,
                    'detail' => $ing['prompt_fragment'] ?? ''
                ];
            }
        }

        // B. Add Legacy Meta
        if (empty($f['normalized_ingredients']) && !empty($f['meta_id'])) {
            if ($f['gen_config_title']) {
                $f['normalized_ingredients'][] =['type' => 'generator_config', 'label' => 'Generator', 'detail' => $f['gen_config_title']];
            }
            if ($f['template_name']) {
                $f['normalized_ingredients'][] =['type' => 'sketch_template', 'label' => 'Template: ' . $f['template_name'], 'detail' => $f['template_core']];
            }
            if ($f['interaction_name']) {
                $f['normalized_ingredients'][] = ['type' => 'interaction', 'label' => 'Interaction', 'detail' => $f['interaction_name']];
            }
        }
        
        // C. Curation Logic (Fully Populated)
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

    return $frames;
}

function getPaginationUrl($page, $search) {
    $params =['page' => $page];
    if ($search !== '') $params['search'] = $search;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.6">
    <title>Storyboards Chain View</title>
    
    <script>
      (function() {
        try {
          var theme = localStorage.getItem('spw_theme');
          if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
          else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
        } catch (e) {}
      })();
    </script>

    <link rel="stylesheet" href="/css/base.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
    
    <style>
        .header-compact { display: flex; align-items: center; gap: 15px; margin-bottom: 10px; margin-left: 60px; height: 40px; margin-top: 20px; }
        .search-line { display: flex; align-items: center; gap: 6px; margin-bottom: 30px; margin-left: 60px; }
        .entity-icon-link { font-size: 1.5rem; text-decoration: none; line-height: 1; display: block; color: var(--text-muted); }
        .entity-icon-link:hover { transform: scale(1.15); color: var(--accent); }
        .search-input { padding: 4px 8px; font-size: 0.85rem; border: 1px solid var(--border); border-radius: 4px; background: var(--card); color: var(--text); width: 300px; }
        .search-input:focus { outline: none; border-color: var(--accent); }
        .btn-sm { padding: 4px 8px; font-size: 0.85rem; border-radius: 4px; cursor: pointer; border: 1px solid var(--border); background: var(--bg); color: var(--text); }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; margin-right: 4px; margin-bottom: 4px; border: 1px solid transparent; }
        .badge-gray { background: rgba(100,100,100,0.1); color: var(--text-muted); border-color: var(--border); }
        .badge-blue { background: rgba(59,130,246,0.1); color: #3b82f6; border-color: rgba(59,130,246,0.2); }
        .badge-orange { background: rgba(245,159,11,0.1); color: #f59e0b; border-color: rgba(245,159,11,0.2); }
        
        .badge-meta { 
            background: linear-gradient(135deg, rgba(99,102,241,0.1), rgba(168,85,247,0.1)); 
            color: #8b5cf6; 
            border: 1px solid rgba(139,92,246,0.3); 
            cursor: pointer; 
        }
        .badge-meta:hover { border-color: #8b5cf6; background: rgba(139,92,246,0.15); }
        
        .badge-curator {
            background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(52,211,153,0.1));
            color: #10b981;
            border: 1px solid rgba(16,185,129,0.3);
            cursor: pointer;
        }
        .badge-curator:hover { background: rgba(16,185,129,0.15); }

        /* Pill Styles for Modal */
        .pill { display: inline-block; padding: 2px 8px; background: rgba(0,0,0,0.05); border-radius: 12px; font-size: 0.8rem; margin-right: 4px; margin-bottom: 4px; color: var(--text); border: 1px solid transparent; }
        .pill-theme { border-color: var(--accent); color: var(--accent); background: rgba(59,130,246,0.1); }
        .pill-char { border-color: #f59e0b; color: #f59e0b; background: rgba(245,159,11,0.1); }

        .storyboard-section { padding: 24px 0; border-bottom: 1px solid var(--border); }
        .storyboard-header { padding: 0 20px 10px 20px; display: flex; align-items: center; gap: 12px; }
        .storyboard-title { font-size: 0.9rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin: 0; }
        .scroll-magic-link { color: var(--text-muted); font-size: 1.2rem; cursor: pointer; }
        .scroll-magic-link:hover { color: var(--accent); }
        .storyboard-meta { font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; display: flex; align-items: center; gap: 8px; }
        .storyboard-note-input { background: transparent; border: none; border-bottom: 1px dashed var(--border); color: var(--text); width: 300px; }
        .storyboard-note-input:focus { outline: none; border-bottom: 1px solid var(--accent); }
        .save-indicator { opacity: 0; transition: opacity 0.3s; color: #10b981; }
        .save-indicator.visible { opacity: 1; }

        .frame-chain-swiper { width: 100%; padding: 16px 0; }
        .swiper-slide { width: 300px; display: flex; align-items: center; position: relative; }
        .swiper-slide:not(:last-child)::after { content: '→'; font-size: 24px; color: var(--text-muted); position: absolute; right: -25px; top: 50%; transform: translateY(-50%); z-index: 1; }

        .chain-card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; box-shadow: var(--card-elevation); width: 100%; display: flex; flex-direction: column; transition: transform 0.2s; }
        .chain-card:hover { transform: translateY(-4px); }
        .chain-card-thumbnail { position: relative; width: 100%; padding-top: 100%; background: var(--bg); }
        .chain-card-thumbnail img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
        .chain-card-body { padding: 12px; flex-grow: 1; font-size: 13px; }
        .chain-card-title { font-weight: 600; color: var(--text); margin: 0 0 8px 0; font-size: 15px; }
        .chain-card-prompt { color: var(--text-muted); display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; cursor: pointer; }
        .chain-card-meta { border-top: 1px solid var(--border); padding-top: 8px; display: flex; justify-content: space-between; font-size: 12px; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center; }
        .modal-content { background: var(--card); padding: 24px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative; color: var(--text); border: 1px solid var(--border); }
        .modal-close { position: absolute; top: 16px; right: 16px; font-size: 24px; cursor: pointer; }
        .modal-row { margin-bottom: 12px; border-bottom: 1px dashed var(--border); padding-bottom: 8px; display: flex; align-items: flex-start; }
        .modal-icon { font-size: 1.5em; margin-right: 12px; min-width: 30px; text-align: center; }
        .modal-info { flex: 1; }
        .modal-label { font-weight: bold; color: var(--text-muted); font-size: 0.85rem; display: block; margin-bottom: 4px;}
        .modal-value { font-size: 0.95rem; display: block; }
        .modal-detail { font-size: 0.85rem; color: var(--text-muted); margin-top: 2px; font-style: italic; }

        .pagination-container { display: flex; justify-content: center; gap: 10px; padding: 40px 0; }
        .pagination-btn { padding: 8px 16px; background: var(--card); border: 1px solid var(--border); color: var(--text); text-decoration: none; border-radius: 4px; }
        .pagination-btn.disabled { opacity: 0.5; pointer-events: none; }
        .page-input { width: 40px; text-align: center; background: transparent; border: none; border-bottom: 1px dashed var(--border); color: var(--text); font-weight: bold; }

.sb-menu { position: absolute !important; }

    </style>
</head>
<body>

    <!-- Header Section -->
    <div class="header-compact">
        <a href="view_storyboards.php" class="entity-icon-link" title="Storyboards Overview"><?php echo $iconChar; ?></a>
        <h2 style="margin:0; font-size:1.2rem; color:var(--text);">Storyboards Chains</h2>
    </div>

    <div class="search-line">
        <form action="" method="GET" style="display:flex; gap:6px; align-items:center; width:100%;">
            <input type="text" name="search" class="search-input" placeholder="Search prompts, descriptions, or IDs..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn-sm">Search</button>
            <?php if($search): ?>
                <a href="?" class="btn-sm" style="text-decoration:none; display:inline-flex; align-items:center;">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="container">
        
        <?php if (!empty($storyboards)): ?>
            <div class="pswp-gallery"> 
                <?php foreach ($storyboards as $sb): 
                    $sb_id = (int)$sb['id'];
                    $frames = getFramesForStoryboard($pdo, $sb_id);
                    if (empty($frames)) continue; 
                ?>

                <div class="storyboard-section">
                
                
                    <div class="storyboard-header">
                        <h3 class="storyboard-title">Storyboard #<?= $sb_id ?>: <?= htmlspecialchars($sb['name']) ?></h3>
                        
                        <!-- Play Icon: Now opens Scroll Magic View -->
                        <a href="view_scrollmagic_multi_prm.php?storyboard_ids=<?= $sb_id ?>&refresh=true" target="_blank" class="scroll-magic-link" title="Open Scroll Magic View">
                            <i class="bi bi-collection-play"></i>
                        </a>

                        <!-- Edit Icon: Opens the default Storyboard Editor -->
                        <a href="view_storyboard.php?id=<?= $sb_id ?>" target="_blank" class="scroll-magic-link" title="Open Storyboard Editor">
                            <i class="bi bi-pencil-square"></i>
                        </a>

                        <!-- Regenerate Icon -->
                        <a href="#" class="scroll-magic-link regen-sb-btn" data-id="<?= $sb_id ?>" title="Regenerate Sketches">
                            <i class="bi bi-arrow-repeat"></i>
                        </a>

                        <div class="storyboard-meta">
                            <span><?= date('M d, Y H:i', strtotime($sb['created_at'])) ?></span>
                            &bull; <span><?= count($frames) ?> frames</span> &bull; 
                            <div style="position: relative; display: inline-flex; align-items: center;">
                                <input type="text" class="storyboard-note-input" data-id="<?= $sb_id ?>" value="<?= htmlspecialchars($sb['description'] ?? '') ?>" placeholder="Add a description..." autocomplete="off">
                                <span class="save-indicator" id="save-indicator-<?= $sb_id ?>" title="Saved">&#10003;</span>
                            </div>
                        </div>
                    </div>
               

                    <div class="swiper frame-chain-swiper" id="swiper-sb-<?= $sb_id ?>">
                        <div class="swiper-wrapper">
                            <?php foreach ($frames as $frame):
                                $img_path = !empty($frame['sb_filename']) ? $frame['sb_filename'] : $frame['original_filename'];
                                $img_path = ltrim($img_path ?? '', '/');
                                
                                $entity_type = htmlspecialchars($frame['entity_type'] ?? 'frames');
                                $entity_id = (int)($frame['entity_id'] ?? 0);
                                $frame_id = (int)($frame['frame_id'] ?? 0);
                                
                                $badge_class = 'badge-gray';
                                $creation_note = $frame['prompt'] ?: ($frame['sb_description'] ?: 'Storyboard Frame');
                                if (!empty($frame['edit_tool'])) { $creation_note = $frame['edit_note'] ?: 'Edited'; $badge_class = 'badge-orange'; } 
                                elseif (!empty($frame['img2img_frame_id'])) { $creation_note = $frame['prompt']; $badge_class = 'badge-blue'; }

                                $full_desc = $frame['full_sketch_desc'] ?? $creation_note;
                                
                                // Determine Meta Display
                                $ingredients = $frame['normalized_ingredients'] ??[];
                                $ingredientCount = count($ingredients);
                                $curation = $frame['curation'] ?? null;
                            ?>
                            <div class="swiper-slide">
                                <div class="chain-card" data-entity="<?= $entity_type ?>" data-entity-id="<?= $entity_id ?>" data-frame-id="<?= $frame_id ?>">
                                    <div class="chain-card-thumbnail">
                                        <a href="<?= htmlspecialchars($img_path) ?>" class="pswp-gallery-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                                            <img src="<?= htmlspecialchars($img_path) ?>" alt="Frame #<?= $frame_id ?>" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                                        </a>
                                    </div>
                                    <div class="chain-card-body">
                                        <h3 class="chain-card-title"><?= htmlspecialchars($frame['sb_frame_name'] ?: 'Frame #'.$frame_id) ?></h3>
                                        <div class="chain-card-method">
                                            <span class="badge <?= $badge_class ?>"><?= $frame['edit_tool'] ? 'Edit' : ($frame['img2img_frame_id'] ? 'Img2Img' : 'Original') ?></span>
                                            
                                            <?php if ($ingredientCount > 0): ?>
                                                <span class="badge badge-meta meta-pill-trigger" 
                                                      data-ingredients='<?= htmlspecialchars(json_encode($ingredients), ENT_QUOTES) ?>'>
                                                    Ingredients (<?= $ingredientCount ?>)
                                                </span>
                                            <?php endif; ?>

                                            <!-- Curation Pill -->
                                            <?php if ($curation): ?>
                                                <span class="badge badge-curator curation-pill-trigger"
                                                      data-curation='<?= htmlspecialchars(json_encode($curation), ENT_QUOTES) ?>'
                                                      title="Quality Score: <?= $curation['score'] ?>">
                                                    🕵️ Analysis (<?= $curation['score'] ?>)
                                                </span>
                                            <?php endif; ?>

                                            <p class="chain-card-prompt full-desc-trigger" title="View description" data-full-desc="<?= htmlspecialchars($full_desc) ?>">
                                                <?= htmlspecialchars($creation_note) ?>
                                            </p>
                                        </div>
                                        <div class="chain-card-meta">
                                            <span class="badge badge-gray"><?= ucfirst($entity_type) ?><?= $entity_id > 0 ? " #$entity_id" : "" ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="swiper-button-next"></div>
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-scrollbar"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <div class="pagination-container">
                <a href="<?= $page > 1 ? getPaginationUrl($page - 1, $search) : '#' ?>" class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>">Previous</a>
                <div class="pagination-info">
                    <input type="number" class="page-input" value="<?= $page ?>" min="1" max="<?= $total_pages ?>" data-search="<?= htmlspecialchars($search) ?>"> / <?= $total_pages ?>
                </div>
                <a href="<?= $page < $total_pages ? getPaginationUrl($page + 1, $search) : '#' ?>" class="pagination-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">Next</a>
            </div>

        <?php else: ?>
            <div class="empty-state" style="text-align:center; padding:40px; color:var(--text-muted);">
                <p>No storyboards found.</p>
                <?php if($search): ?><a href="?" class="btn-sm" style="text-decoration:none;">Clear Search</a><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modals -->
    <div id="meta-modal" class="modal-overlay">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h3 class="modal-title" style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:10px;">Recipe Ingredients</h3>
            <div id="meta-modal-body"></div>
        </div>
    </div>

    <!-- Curation Modal -->
    <div id="curation-modal" class="modal-overlay">
        <div class="modal-content" style="max-width:700px;">
            <span class="modal-close">&times;</span>
            <h3 class="modal-title" style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:10px;">Narrative Analysis</h3>
            <div id="curation-modal-body"></div>
        </div>
    </div>

    <div id="desc-modal" class="modal-overlay">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h3 class="modal-title" style="margin-top:0;">Full Description</h3>
            <div id="desc-modal-body" style="white-space: pre-wrap; font-family: monospace; font-size:0.9rem;"></div>
        </div>
    </div>

    <?= $eruda ?? '' ?>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="/js/gear_menu_globals.js"></script>
    <?= $gearMenu->render() ?>
    <?= $imageEditor->render() ?>
    <?= $frameDetailsModal ?>
    <script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

    <!-- PhotoSwipe -->
    <script type="module">
        import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
        const lightbox = new PhotoSwipeLightbox({
            gallery: '.pswp-gallery', 
            children: '.pswp-gallery-item', 
            pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js'),
            initialZoomLevel: 'fit',
            secondaryZoomLevel: 1
        });
        lightbox.init();
    </script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        
        // Modal Handlers
        const metaModal = document.getElementById('meta-modal');
        const descModal = document.getElementById('desc-modal');
        const curationModal = document.getElementById('curation-modal');
        
        function closeModal(modal) { modal.style.display = 'none'; }
        document.querySelectorAll('.modal-close').forEach(btn => btn.addEventListener('click', function() { closeModal(this.closest('.modal-overlay')); }));
        window.addEventListener('click', function(e) { if (e.target.classList.contains('modal-overlay')) closeModal(e.target); });

        // Meta Pill Click - DYNAMIC
        $(document).on('click', '.meta-pill-trigger', function(e) {
            e.stopPropagation();
            const raw = this.dataset.ingredients;
            if (!raw) return;
            const ingredients = JSON.parse(raw);
            const body = document.getElementById('meta-modal-body');
            let html = '';

            const getIcon = (type) => {
                if (type.includes('character')) return '🦸';
                if (type.includes('location')) return '🗺️';
                if (type.includes('template')) return '🎬';
                if (type.includes('interaction')) return '🤝';
                if (type.includes('style')) return '🎨';
                if (type.includes('generator')) return '⚡';
                if (type.includes('anivoc')) return '📘';
                return '📦';
            };

            ingredients.forEach(ing => {
                const icon = getIcon(ing.type);
                html += `
                    <div class="modal-row">
                        <div class="modal-icon">${icon}</div>
                        <div class="modal-info">
                            <span class="modal-label">${ing.label}</span>
                            ${ing.detail ? `<span class="modal-detail">${ing.detail.substring(0, 150)}${ing.detail.length>150?'...':''}</span>` : ''}
                        </div>
                    </div>
                `;
            });

            body.innerHTML = html;
            metaModal.style.display = 'flex';
        });

        // Curation Pill Click (Detailed)
        $(document).on('click', '.curation-pill-trigger', function(e) {
            e.stopPropagation();
            const raw = this.dataset.curation;
            if (!raw) return;
            const data = JSON.parse(raw);
            const body = document.getElementById('curation-modal-body');
            
            let html = `
                <div style="margin-bottom:15px;">
                    <div class="score-badge score-high" style="display:inline-block; padding:4px 10px; background:#10b981; color:white; border-radius:6px; font-weight:800; font-size:1.2em; margin-right:10px;">${data.score}</div>
                    <strong style="font-size:1.1em;">Overall Quality</strong>
                </div>
            `;
            
            if(data.class) {
                if(data.class.narrative_function) html += `<div class="modal-row"><span class="modal-label">Function</span><span class="modal-value">${data.class.narrative_function}</span></div>`;
                if(data.class.emotional_tone) html += `<div class="modal-row"><span class="modal-label">Tone</span><span class="modal-value">${data.class.emotional_tone}</span></div>`;
            }

            if (data.themes && data.themes.primary_themes) {
                html += `<div class="modal-row"><span class="modal-label">Themes</span><div style="margin-top:4px;">`;
                let themes = Array.isArray(data.themes.primary_themes) ? data.themes.primary_themes : [data.themes.primary_themes];
                themes.forEach(t => html += `<span class="pill pill-theme">${t}</span> `);
                html += `</div></div>`;
            }

            if (data.entities) {
                 if(data.entities.characters && data.entities.characters.length > 0) {
                    html += `<div class="modal-row"><span class="modal-label">Characters</span><div style="margin-top:4px;">`;
                    data.entities.characters.forEach(c => html += `<span class="pill pill-char">${c}</span> `);
                    html += `</div></div>`;
                 }
                 if(data.entities.artifacts && data.entities.artifacts.length > 0) {
                    html += `<div class="modal-row"><span class="modal-label">Artifacts</span><div style="margin-top:4px;">${data.entities.artifacts.join(', ')}</div></div>`;
                 }
            }

            if(data.recs && data.recs.potential_use) {
                 html += `<div style="margin-top:15px; background:rgba(245,159,11,0.1); padding:10px; border-radius:6px; border:1px dashed rgba(245,159,11,0.4);">
                            <span class="modal-label" style="color:#f59e0b;">Suggestion</span>
                            <div style="font-style:italic; margin-top:4px;">${data.recs.potential_use}</div>
                          </div>`;
            }

            if(data.score_breakdown) {
                 html += `<div style="margin-top:15px; border-top:1px dashed var(--border); padding-top:10px;">
                            <span class="modal-label">Score Breakdown</span>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; font-size:0.9em; margin-top:5px;">
                                <div>Narrative: <b>${data.score_breakdown.narrative_completeness || '-'}</b></div>
                                <div>Visual: <b>${data.score_breakdown.visual_impact || '-'}</b></div>
                                <div>Production: <b>${data.score_breakdown.production_readiness || '-'}</b></div>
                                <div>Distinctiveness: <b>${data.score_breakdown.visual_distinctiveness || '-'}</b></div>
                            </div>
                          </div>`;
            }
            
            body.innerHTML = html;
            curationModal.style.display = 'flex';
        });

        // Description Click
        $(document).on('click', '.full-desc-trigger', function(e) {
            e.stopPropagation();
            document.getElementById('desc-modal-body').textContent = this.dataset.fullDesc;
            descModal.style.display = 'flex';
        });

        // Regenerate Storyboard Sketches
        $(document).on('click', '.regen-sb-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            if (!confirm("Regenerate all sketches in this storyboard?")) return;
            
            const orig = $btn.html();
            $btn.html('...').css('pointer-events', 'none');

            $.post('', { action: 'regenerate_storyboard', storyboard_id: $btn.data('id') }, function(res) {
                if (res.success) { Toast.show('Marked ' + res.count + ' sketches for regeneration', 'success'); $btn.html('✔'); }
                else { Toast.show('Error: ' + res.error, 'error'); $btn.html(orig).css('pointer-events', 'auto'); }
            }, 'json').fail(function(){ Toast.show('Network Error', 'error'); $btn.html(orig).css('pointer-events', 'auto'); });
        });

        // Description Saving
        $('.storyboard-note-input').on('change', function() {
            const $in = $(this);
            const $ind = $('#save-indicator-' + $in.data('id'));
            $.post('', { action: 'update_description', storyboard_id: $in.data('id'), note: $in.val() }, function(res) {
                if (res.success) { 
                    $ind.addClass('visible'); setTimeout(() => $ind.removeClass('visible'), 2000); 
                } else Toast.show('Error saving description', 'error');
            }, 'json');
        });

        // Init Swipers
        document.querySelectorAll('.frame-chain-swiper').forEach(el => {
            new Swiper(el, {
                slidesPerView: 'auto', spaceBetween: 40, freeMode: true,
                navigation: { nextEl: el.querySelector('.swiper-button-next'), prevEl: el.querySelector('.swiper-button-prev') },
                scrollbar: { el: el.querySelector('.swiper-scrollbar'), hide: true },
                slidesOffsetBefore: 20, slidesOffsetAfter: 20
            });
        });

        // Gear Menu
        if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
            const container = document.querySelector('.container');
            if(container) window.GearMenu.attach(container);
        }
        
        // Page Input
        $('.page-input').on('change', function() {
            let val = parseInt($(this).val());
            const max = parseInt($(this).attr('max'));
            if (val < 1) val = 1; if (val > max) val = max;
            let url = '?page=' + val;
            const search = $(this).data('search');
            if(search) url += '&search=' + encodeURIComponent(search);
            window.location.href = url;
        });
    });
    </script>
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>
<?php require_once "forge_tool.php"; ?>
</body>
</html>