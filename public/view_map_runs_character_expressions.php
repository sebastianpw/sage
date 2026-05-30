<?php
// public/view_map_runs_character_expressions.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require "entity_icons.php"; // Loaded for the icon

use App\UI\Modules\ModuleRegistry;

// --- 0. AJAX Handlers ---

// Handler for Regenerate Run Flagging
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'regenerate_run') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    $map_run_id = filter_input(INPUT_POST, 'map_run_id', FILTER_VALIDATE_INT);

    if ($map_run_id) {
        try {
            // Find all character_expressions entity IDs linked to frames in this run
            $stmt = $pdo->prepare("SELECT DISTINCT entity_id FROM frames WHERE map_run_id = :mid AND entity_type = 'character_expressions'");
            $stmt->execute(['mid' => $map_run_id]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($ids)) {
                // Update character_expressions table
                $inQuery = implode(',', array_fill(0, count($ids), '?'));
                $upStmt = $pdo->prepare("UPDATE character_expressions SET regenerate_images = 1 WHERE id IN ($inQuery)");
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

// Handler for Note Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_note') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');
    
    $map_run_id = filter_input(INPUT_POST, 'map_run_id', FILTER_VALIDATE_INT);
    $note = isset($_POST['note']) ? trim($_POST['note']) : '';

    if ($map_run_id) {
        try {
            $stmt = $pdo->prepare("UPDATE map_runs SET note = :note WHERE id = :id");
            $success = $stmt->execute(['note' => $note, 'id' => $map_run_id]);
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
$whereClause = "WHERE mr.entity_type = 'character_expressions'";
$params = [];

// Search Logic
if ($search !== '') {
    $whereClause .= " AND (
        mr.id = :search_int 
        OR mr.note LIKE :search_wild 
        OR EXISTS (
            SELECT 1 FROM frames f 
            WHERE f.map_run_id = mr.id 
            AND (f.prompt LIKE :search_wild OR f.name LIKE :search_wild)
        )
    )";
    $params['search_int'] = (int)$search;
    $params['search_wild'] = "%$search%";
}

// Count Total
$countSql = "SELECT COUNT(*) FROM map_runs mr $whereClause";
$stmt = $pdo->prepare($countSql);
foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
$stmt->execute();
$total_runs = $stmt->fetchColumn();
$total_pages = ceil($total_runs / $limit);
if ($total_pages < 1) $total_pages = 1;

// Fetch Map Runs
$sql = <<<SQL
    SELECT mr.id, mr.created_at, mr.note 
    FROM map_runs mr
    $whereClause
    ORDER BY mr.id DESC 
    LIMIT :limit OFFSET :offset
SQL;

$stmt = $pdo->prepare($sql);
foreach ($params as $key => $val) { $stmt->bindValue($key, $val); }
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$map_runs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Icon Selection
$entity = 'character_expressions';
$iconChar = $entityIcons[$entity] ?? '📦';
$schedulerId = $entitySchedulerIds[$entity] ?? null;

// --- 2. UI Module Setup ---
$registry = ModuleRegistry::getInstance();
$entities_with_menu = ['characters', 'character_expressions', 'animas', 'locations', 'backgrounds', 'artifacts', 'vehicles', 'scene_parts', 'controlnet_maps', 'spawns', 'generatives', 'sketches', 'prompt_matrix_blueprints', 'composites'];
$gearMenu = $registry->create('gear_menu', [
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

// Helper Function
function getFramesForRun($pdo, $map_run_id) {
    $sql = <<<SQL
    SELECT 
        f.id as frame_id,
        f.name as frame_name,
        f.filename,
        f.prompt,
        f.entity_type,
        f.entity_id,
        cp.name as character_pose_name,
        ie.tool as edit_tool,
        ie.note as edit_note,
        f.img2img_frame_id
        
    FROM frames f
    LEFT JOIN character_expressions cp ON cp.id = f.entity_id AND f.entity_type = 'character_expressions'
    LEFT JOIN image_edits ie ON ie.derived_frame_id = f.id

    WHERE f.map_run_id = :mrid
    ORDER BY f.id ASC
SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['mrid' => $map_run_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getPaginationUrl($page, $search) {
    $params = ['page' => $page];
    if ($search !== '') $params['search'] = $search;
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.7">
    <title>Character Expressions Map Runs</title>
    
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
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">

    <!-- SwiperJS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <!-- PhotoSwipe -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
    
    <style>
        /* Header / Search Styles */
        .header-compact {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
            margin-left: 60px; /* Space for absolute dashboard button */
            height: 40px;
            margin-top: 20px;
        }

        .search-line {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 30px;
            margin-left: 60px;
        }

        .entity-icon-link {
            font-size: 1.5rem;
            text-decoration: none;
            line-height: 1;
            transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: block;
            color: var(--text-muted);
        }
        .entity-icon-link:hover { 
            transform: scale(1.15); 
            color: var(--accent);
        }

        .search-input {
            padding: 4px 8px;
            font-size: 0.85rem;
            border: 1px solid var(--border);
            border-radius: 4px;
            background: var(--card);
            color: var(--text);
            width: 300px;
            transition: width 0.2s;
        }
        .search-input:focus { outline: none; border-color: var(--accent); }

        .btn-sm {
            padding: 4px 8px;
            font-size: 0.85rem;
            border-radius: 4px;
            cursor: pointer;
            border: 1px solid var(--border);
            background: var(--bg);
            color: var(--text);
        }
        .btn-sm:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* Map Run View Styles */
        .badge-orange {
            background-color: rgba(245, 159, 11, 0.1);
            color: var(--orange);
            border-color: rgba(245, 159, 11, 0.15);
        }
        /* New Badge Styles */
        .badge-purple {
            background-color: rgba(168, 85, 247, 0.1);
            color: #a855f7;
            border-color: rgba(168, 85, 247, 0.2);
            border: 1px solid;
            border-radius: 4px;
            padding: 2px 4px;
            font-size: 10px;
        }
        .badge-cyan {
            background-color: rgba(6, 182, 212, 0.1);
            color: #06b6d4;
            border-color: rgba(6, 182, 212, 0.2);
            border: 1px solid;
            border-radius: 4px;
            padding: 2px 4px;
            font-size: 10px;
        }
        .badge-meta {
            background-color: var(--bg-lighter);
            color: var(--text-muted);
            border: 1px solid var(--border);
            border-radius: 4px;
            padding: 2px 6px;
            font-size: 10px;
            cursor: pointer;
            text-transform: uppercase;
            font-weight: 700;
        }
        .badge-meta:hover {
            background-color: var(--card);
            color: var(--text);
            border-color: var(--accent);
        }

        .map-run-section {
            padding: 24px 0;
            border-bottom: 1px solid var(--border);
        }

        .map-run-header {
            padding: 0 20px 10px 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .map-run-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin: 0;
        }

        .scroll-magic-link {
            color: var(--text-muted);
            font-size: 1.2rem;
            text-decoration: none;
            transition: color 0.2s, transform 0.2s;
            display: inline-flex;
            align-items: center;
            cursor: pointer;
        }
        .scroll-magic-link:hover {
            color: var(--accent);
            transform: scale(1.1);
        }

        .map-run-meta {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .map-run-note-input {
            background: transparent;
            border: 1px solid transparent;
            border-bottom: 1px dashed var(--border);
            color: var(--text);
            font-size: 0.85rem;
            font-family: inherit;
            padding: 2px 6px;
            width: 300px;
            transition: all 0.2s ease;
            border-radius: 4px;
        }
        
        .map-run-note-input:hover {
            background-color: var(--bg-lighter, #1e293b);
            border-color: var(--border);
        }

        .map-run-note-input:focus {
            background-color: var(--card);
            border: 1px solid var(--accent);
            outline: none;
            color: var(--text);
            box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2);
        }
        
        .save-indicator {
            opacity: 0;
            transition: opacity 0.3s ease;
            font-size: 1.2em;
            color: #10b981;
        }
        .save-indicator.visible { opacity: 1; }

        /* Swiper & Card Styles */
        .frame-chain-swiper {
            width: 100%;
            height: auto;
            padding: 16px 0;
        }

        .swiper-slide {
            width: 300px;
            height: auto;
            display: flex;
            align-items: center;
            position: relative;
        }

        .swiper-slide:not(:last-child)::after {
            content: '→';
            font-size: 24px;
            color: var(--text-muted);
            position: absolute;
            right: -25px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1;
        }

        .chain-card {
            background-color: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
            box-shadow: var(--card-elevation);
            width: 100%;
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .chain-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(2,6,23,0.5);
        }

        .chain-card-thumbnail {
            position: relative;
            width: 100%;
            padding-top: 100%;
            background-color: var(--bg);
        }
        .chain-card-thumbnail img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .chain-card-body {
            padding: 12px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            font-size: 13px;
        }

        .chain-card-title {
            font-weight: 600;
            color: var(--text);
            margin: 0 0 8px 0;
            font-size: 15px;
        }

        .chain-card-method {
            margin-bottom: 12px;
            flex-grow: 1;
        }
        .chain-card-method .badge {
            margin-bottom: 6px;
            display: inline-block;
            margin-right: 4px;
        }

        .chain-card-prompt {
            color: var(--text-muted);
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-break: break-word;
            cursor: pointer; /* Click to see full description */
            transition: color 0.2s;
        }
        .chain-card-prompt:hover {
            color: var(--text);
        }
        .chain-card-meta {
            border-top: 1px solid var(--border);
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
        }
        
        /* Pagination */
        .pagination-container {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 40px 0;
        }
        .pagination-btn {
            padding: 8px 16px;
            background: var(--card);
            border: 1px solid var(--border);
            color: var(--text);
            text-decoration: none;
            border-radius: 4px;
        }
        .pagination-btn:hover {
            background: var(--accent);
            color: white;
            border-color: var(--accent);
        }
        .pagination-btn.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Inline Page Edit */
        .pagination-info {
            display: flex;
            align-items: center;
            gap: 5px;
            background: var(--card);
            border: 1px solid var(--border);
            padding: 4px 12px;
            border-radius: 4px;
            color: var(--text-muted);
        }
        .page-input {
            width: 40px;
            text-align: center;
            background: transparent;
            border: none;
            border-bottom: 1px dashed var(--border);
            color: var(--text);
            font-weight: bold;
            padding: 2px 0;
            -moz-appearance: textfield;
        }
        .page-input::-webkit-outer-spin-button,
        .page-input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .page-input:focus {
            outline: none;
            border-bottom-color: var(--accent);
            color: var(--accent);
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(2px);
        }
        .modal-content {
            background: var(--card);
            padding: 24px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 20px 50px rgba(0,0,0,0.5);
            border: 1px solid var(--border);
            color: var(--text);
        }
        .modal-close {
            position: absolute;
            top: 16px;
            right: 16px;
            font-size: 24px;
            cursor: pointer;
            line-height: 1;
            color: var(--text-muted);
        }
        .modal-close:hover { color: var(--accent); }
        .modal-title {
            margin-top: 0;
            margin-bottom: 16px;
            border-bottom: 1px solid var(--border);
            padding-bottom: 10px;
        }
        .modal-row {
            margin-bottom: 12px;
            border-bottom: 1px dashed var(--border);
            padding-bottom: 8px;
        }
        .modal-label {
            font-weight: bold;
            color: var(--text-muted);
            font-size: 0.85rem;
            display: block;
            margin-bottom: 4px;
        }
        .modal-value {
            font-size: 0.95rem;
        }

        @media (max-width: 768px) {
            .header-compact, .search-line { margin-left: 50px; }
            .search-input { width: 100%; }
        }
        /* FIX: Force menu to absolute so it tracks with scroll */
        .sb-menu { position: absolute !important; }
    </style>
</head>
<body>

    <!-- Header Section -->
    <div class="header-compact">
        <a href="sql_crud_character_expressions.php" class="entity-icon-link" title="Character Expressions CRUD"><?php echo $iconChar; ?></a>
        <a href="gallery_character_expressions_nu.php" class="entity-icon-link" title="Character Expressions Gallery">▦</a>
        <h2 style="margin:0; font-size:1.2rem; color:var(--text);">Character Expressions Map Runs</h2>
        <?php if ($schedulerId): ?>
            <a class="runBtn scheduler" data-id="<?php echo $schedulerId; ?>" title="Trigger <?php echo ucfirst($entity); ?> Scheduler" style="cursor:pointer; font-size:1.2rem; text-decoration:none; margin-left:5px;">🌀</a>
        <?php endif; ?>
    </div>

    <div class="search-line">
        <form action="" method="GET" style="display:flex; gap:6px; align-items:center; width:100%;">
            <input type="text" name="search" class="search-input" placeholder="Search prompts, notes, or IDs..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn-sm">Search</button>
            <?php if($search): ?>
                <a href="?" class="btn-sm" style="text-decoration:none; display:inline-flex; align-items:center;">Reset</a>
            <?php endif; ?>
        </form>
    </div>


    <div class="container">
        
        <?php if (!empty($map_runs)): ?>
            
            <div class="pswp-gallery"> 

                <?php foreach ($map_runs as $run): 
                    $run_id = (int)$run['id'];
                    $frames = getFramesForRun($pdo, $run_id);
                    
                    if (empty($frames)) continue; 
                ?>

                <div class="map-run-section">
                    <div class="map-run-header">
                        <h3 class="map-run-title">Map Run #<?= $run_id ?></h3>
                        
                        <!-- SCROLL MAGIC LINK -->
                        <a href="view_scrollmagic_map_run.php?map_run_id=<?= $run_id ?>" target="_blank" class="scroll-magic-link" title="Open Scroll Magic View">
                            <i class="bi bi-collection-play"></i>
                        </a>

                        <!-- NEW REGENERATE BUTTON -->
                        <a href="#" class="scroll-magic-link regen-run-btn" data-id="<?= $run_id ?>" title="Regenerate Images for this Run (Set flag for all frames)">
                            <i class="bi bi-arrow-repeat"></i>
                        </a>

                        <div class="map-run-meta">
                            <span><?= date('M d, Y H:i', strtotime($run['created_at'])) ?></span>
                            &bull; 
                            <span><?= count($frames) ?> frames</span>
                            &bull; 
                            <!-- Inline Editable Note Input -->
                            <div style="position: relative; display: inline-flex; align-items: center;">
                                <input type="text" 
                                       class="map-run-note-input" 
                                       data-id="<?= $run_id ?>" 
                                       value="<?= htmlspecialchars($run['note'] ?? '') ?>" 
                                       placeholder="Add a note..."
                                       autocomplete="off">
                                <span class="save-indicator" id="save-indicator-<?= $run_id ?>" title="Saved">&#10003;</span>
                            </div>
                        </div>
                    </div>

                    <!-- Unique Swiper Container per Run -->
                    <div class="swiper frame-chain-swiper" id="swiper-run-<?= $run_id ?>">
                        <div class="swiper-wrapper">
                            
                            <?php foreach ($frames as $frame):
                                $img_path = ltrim($frame['filename'], '/');
                                $entity_type = htmlspecialchars($frame['entity_type'] ?? 'frames');
                                $entity_id = (int)($frame['entity_id'] ?? 0);
                                $frame_id = (int)$frame['frame_id'];
                                
                                $creation_method = 'Original';
                                $creation_note = $frame['prompt'] ?: 'Initial image';
                                $badge_class = 'badge-gray';

                                if (!empty($frame['edit_tool'])) {
                                    $creation_method = 'Image Edit';
                                    $creation_note = $frame['edit_note'] ?: 'Edited in image editor';
                                    $badge_class = 'badge-orange';
                                } 
                                elseif (!empty($frame['img2img_frame_id'])) {
                                    $creation_method = 'Img2Img';
                                    $creation_note = $frame['prompt'];
                                    $badge_class = 'badge-blue';
                                }

                                // Metadata Logic
                                $has_meta = false;
                                $full_desc = $creation_note;
                            ?>
                            <div class="swiper-slide">
                                <div class="chain-card" 
                                     data-entity="<?= $entity_type ?>" 
                                     data-entity-id="<?= $entity_id ?>" 
                                     data-frame-id="<?= $frame_id ?>">
                                    
                                    <div class="chain-card-thumbnail">
                                        <a href="<?= htmlspecialchars($img_path) ?>"
                                           class="pswp-gallery-item"
                                           data-pswp-width="1024"
                                           data-pswp-height="1024"
                                           target="_blank">
                                            <img src="<?= htmlspecialchars($img_path) ?>" alt="<?= htmlspecialchars($frame['frame_name']) ?>" loading="lazy">
                                        </a>
                                    </div>

                                    <div class="chain-card-body">
                                        <h3 class="chain-card-title">
                                            Frame #<?= $frame_id ?>
                                        </h3>
                                        
                                        <div class="chain-card-method">
                                            <span class="badge <?= $badge_class ?>"><?= $creation_method ?></span>
                                            
                                            <p class="chain-card-prompt full-desc-trigger" 
                                               title="Click to view full description"
                                               data-full-desc="<?= htmlspecialchars($full_desc) ?>">
                                                <?= htmlspecialchars($creation_note) ?>
                                            </p>
                                        </div>
                                        
                                        <div class="chain-card-meta">
                                            <span class="badge badge-gray">
                                                <?= ucfirst($entity_type) ?> #<?= $entity_id ?>
                                            </span>
                                            <?php if (!empty($frame['character_pose_name'])): ?>
                                                 <span class="badge badge-blue"><?= htmlspecialchars($frame['character_pose_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

                        </div>
                        
                        <!-- Navigation Arrows -->
                        <div class="swiper-button-next"></div>
                        <div class="swiper-button-prev"></div>
                        <div class="swiper-scrollbar"></div>
                    </div>
                </div>
                <?php endforeach; ?>

            </div>

            <!-- Pagination -->
            <div class="pagination-container">
                <?php if ($page > 1): ?>
                    <a href="<?= getPaginationUrl($page - 1, $search) ?>" class="pagination-btn">Previous</a>
                <?php else: ?>
                    <span class="pagination-btn disabled">Previous</span>
                <?php endif; ?>

                <div class="pagination-info">
                    <span>Page</span>
                    <input type="number" 
                           class="page-input" 
                           value="<?= $page ?>" 
                           min="1" 
                           max="<?= $total_pages ?>"
                           data-search="<?= htmlspecialchars($search) ?>"
                           autocomplete="off">
                    <span>of <?= $total_pages ?></span>
                </div>

                <?php if ($page < $total_pages): ?>
                    <a href="<?= getPaginationUrl($page + 1, $search) ?>" class="pagination-btn">Next</a>
                <?php else: ?>
                    <span class="pagination-btn_disabled">Next</span>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <div class="empty-state">
                <p>No map runs found for Character Expressions.</p>
                <?php if($search): ?>
                    <p>Try adjusting your search terms.</p>
                    <a href="?" class="btn-sm" style="text-decoration:none;">Clear Search</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </div>

    <!-- Modals -->
    
    <!-- Meta Info Modal -->
    <div id="meta-modal" class="modal-overlay">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h3 class="modal-title">Character Pose Meta Information</h3>
            <div id="meta-modal-body">
                <!-- Content injected via JS -->
            </div>
        </div>
    </div>

    <!-- Full Description Modal -->
    <div id="desc-modal" class="modal-overlay">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h3 class="modal-title">Full Description</h3>
            <div id="desc-modal-body" style="white-space: pre-wrap; font-family: monospace; font-size:0.9rem;"></div>
        </div>
    </div>

    <!-- Render modular UI components -->
    <?= $eruda ?? '' ?>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="/js/gear_menu_globals.js"></script>
    <?= $gearMenu->render() ?>
    <?= $imageEditor->render() ?>
    <?= $frameDetailsModal ?>
    
    <!-- Dashboard Button -->
    <script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

    <!-- PhotoSwipe -->
    <script type="module">
        import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
        const lightbox = new PhotoSwipeLightbox({
            gallery: '.pswp-gallery',
            children: '.pswp-gallery-item',
            pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js')
        });
        lightbox.init();
    </script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        
        $(document).on('click', '.runBtn', function() {
            let id = $(this).data('id');
            $.post('scheduler_view.php', {
                action: 'run_now',
                id: id
            }, function(res) {
                if (res === 'success') {
                    Toast.show('Task scheduled to run now!', 'success');
                } else {
                    Toast.show('Failed to trigger task', 'error');
                }
            });
        });

        // --- NEW: Regenerate Run Handler ---
        $(document).on('click', '.regen-run-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            const runId = $btn.data('id');

            if (!confirm("Are you sure you want to mark all Character Expressions in this map run (Run #" + runId + ") for regeneration?")) {
                return;
            }

            const originalIcon = $btn.html();
            $btn.html('<i class="bi bi-hourglass-split"></i>').css('pointer-events', 'none');

            $.post('', { // Post to current file
                action: 'regenerate_run',
                map_run_id: runId
            }, function(response) {
                if (response.success) {
                    Toast.show('Success: ' + response.count + ' character pose(s) marked for regeneration.', 'success');
                    $btn.html('<i class="bi bi-check-lg" style="color:var(--accent);"></i>');
                } else {
                    Toast.show('Error: ' + (response.error || 'Unknown error'), 'error');
                    $btn.html(originalIcon).css('pointer-events', 'auto');
                }
            }).fail(function() {
                Toast.show('Network error.', 'error');
                $btn.html(originalIcon).css('pointer-events', 'auto');
            });
        });
        
        // --- NEW: Modal Handlers ---
        const metaModal = document.getElementById('meta-modal');
        const descModal = document.getElementById('desc-modal');
        const metaBody = document.getElementById('meta-modal-body');
        const descBody = document.getElementById('desc-modal-body');

        function closeModal(modal) {
            modal.style.display = 'none';
        }

        // Close buttons
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', function() {
                closeModal(this.closest('.modal-overlay'));
            });
        });

        // Click outside to close
        window.addEventListener('click', function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                closeModal(e.target);
            }
        });

        // Meta Pill Click (left intentionally generic; add dataset keys when available)
        $(document).on('click', '.meta-pill-trigger', function(e) {
            e.stopPropagation(); // Prevent card clicks if any
            const d = this.dataset;
            
            let html = '';
            
            if(d.genTitle) html += `<div class="modal-row"><span class="modal-label">Generator Config</span><span class="modal-value">${d.genTitle}</span></div>`;
            if(d.templateName) html += `<div class="modal-row"><span class="modal-label">Template</span><span class="modal-value">${d.templateName}</span></div>`;
            if(d.coreIdea) html += `<div class="modal-row"><span class="modal-label">Core Idea</span><span class="modal-value">${d.coreIdea}</span></div>`;
            if(d.shotType) html += `<div class="modal-row"><span class="modal-label">Shot Type</span><span class="modal-value">${d.shotType}</span></div>`;
            if(d.angle) html += `<div class="modal-row"><span class="modal-label">Camera Angle</span><span class="modal-value">${d.angle}</span></div>`;
            if(d.perspective) html += `<div class="modal-row"><span class="modal-label">Perspective</span><span class="modal-value">${d.perspective}</span></div>`;
            if(d.interaction) html += `<div class="modal-row"><span class="modal-label">Interaction</span><span class="modal-value">${d.interaction}</span></div>`;

            if(html === '') html = '<p>No meta information available.</p>';
            
            metaBody.innerHTML = html;
            metaModal.style.display = 'flex';
        });

        // Description Click
        $(document).on('click', '.full-desc-trigger', function(e) {
            e.stopPropagation();
            const text = this.dataset.fullDesc;
            descBody.textContent = text;
            descModal.style.display = 'flex';
        });

        // -----------------------------------

        document.querySelectorAll('.frame-chain-swiper').forEach(function(swiperElement) {
            new Swiper(swiperElement, {
                slidesPerView: 'auto',
                spaceBetween: 40,
                freeMode: true,
                navigation: {
                    nextEl: swiperElement.querySelector('.swiper-button-next'),
                    prevEl: swiperElement.querySelector('.swiper-button-prev'),
                },
                scrollbar: {
                    el: swiperElement.querySelector('.swiper-scrollbar'),
                    hide: true,
                },
                slidesOffsetBefore: 20,
                slidesOffsetAfter: 20,
            });
        });

        if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
            const container = document.querySelector('.container');
            if(container) {
                window.GearMenu.attach(container);
            }
        }

        $('.map-run-note-input').on('change', function() {
            const $input = $(this);
            const mapRunId = $input.data('id');
            const noteContent = $input.val();
            const $indicator = $('#save-indicator-' + mapRunId);

            $input.css('color', 'var(--accent)');

            $.post('', { // Post to current URL
                action: 'update_note',
                map_run_id: mapRunId,
                note: noteContent
            }, function(response) {
                if (response.success) {
                    $input.css('color', ''); 
                    $indicator.addClass('visible');
                    setTimeout(function() {
                        $indicator.removeClass('visible');
                    }, 2000);
                } else {
                    alert('Error saving note: ' + (response.error || 'Unknown error'));
                    $input.css('color', 'red');
                }
            }).fail(function() {
                alert('Network error while saving note.');
                $input.css('color', 'red');
            });
        });
        
        $('.map-run-note-input').on('keypress', function(e) {
            if(e.which === 13) $(this).blur(); 
        });

        $('.page-input').on('change', function() {
            let val = parseInt($(this).val());
            const max = parseInt($(this).attr('max'));
            const search = $(this).data('search');

            if (isNaN(val) || val < 1) val = 1;
            if (val > max) val = max;

            let url = '?page=' + val;
            if (search && search.toString().trim() !== '') {
                url += '&search=' + encodeURIComponent(search);
            }
            window.location.href = url;
        });

        $('.page-input').on('keypress', function(e) {
            if(e.which === 13) $(this).trigger('change');
        });
    });
    </script>
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>
<?php require_once "forge_tool.php"; ?>
</body>
</html>