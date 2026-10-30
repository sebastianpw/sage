<?php
// public/view_map_runs_dimensionals.php
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
            // Find all dimensional entity IDs linked to frames in this run
            $stmt = $pdo->prepare("SELECT DISTINCT entity_id FROM frames WHERE map_run_id = :mid AND entity_type = 'dimensionals'");
            $stmt->execute(['mid' => $map_run_id]);
            $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

            if (!empty($ids)) {
                $inQuery = implode(',', array_fill(0, count($ids), '?'));
                $upStmt = $pdo->prepare("UPDATE dimensionals SET regenerate_images = 1 WHERE id IN ($inQuery)");
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
$whereClause = "WHERE mr.entity_type = 'dimensionals'";
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
$entity = 'dimensionals';
$iconChar = $entityIcons[$entity] ?? '📦';
$schedulerId = $entitySchedulerIds[$entity] ?? null;

// --- 2. UI Module Setup ---
$registry = ModuleRegistry::getInstance();
$entities_with_menu = ['characters', 'character_poses', 'animas', 'locations', 'backgrounds', 'artifacts', 'vehicles', 'scene_parts', 'controlnet_maps', 'spawns', 'generatives', 'dimensionals', 'sketches', 'prompt_matrix_blueprints', 'composites'];
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
        g.name as dimensional_name,
        ie.tool as edit_tool,
        ie.note as edit_note,
        f.img2img_frame_id
        
    FROM frames f
    LEFT JOIN dimensionals g ON g.id = f.entity_id AND f.entity_type = 'dimensionals'
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
    <title>Dimensionals Map Runs</title>
    
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
        .btn-sm:hover { border-color: var(--accent); color: var(--accent); }

        .badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; margin-right: 4px; margin-bottom: 4px; border: 1px solid transparent; }
        .badge-gray { background: rgba(100,100,100,0.1); color: var(--text-muted); border-color: var(--border); }
        .badge-blue { background: rgba(59,130,246,0.1); color: #3b82f6; border-color: rgba(59,130,246,0.2); }
        .badge-orange { background: rgba(245,159,11,0.1); color: #f59e0b; border-color: rgba(245,159,11,0.2); }

        .map-run-section { padding: 24px 0; border-bottom: 1px solid var(--border); }
        .map-run-header { padding: 0 20px 10px 20px; display: flex; align-items: center; gap: 12px; }
        .map-run-title { font-size: 0.9rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin: 0; }
        .scroll-magic-link { color: var(--text-muted); font-size: 1.2rem; cursor: pointer; text-decoration: none; }
        .scroll-magic-link:hover { color: var(--accent); }
        .map-run-meta { font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
        .map-run-note-input { background: transparent; border: 1px solid transparent; border-bottom: 1px dashed var(--border); color: var(--text); font-size: 0.85rem; padding: 2px 6px; width: 300px; border-radius: 4px; }
        .map-run-note-input:focus { background-color: var(--card); border: 1px solid var(--accent); outline: none; }
        .save-indicator { opacity: 0; transition: opacity 0.3s ease; font-size: 1.2em; color: #10b981; }
        .save-indicator.visible { opacity: 1; }

        .frame-chain-swiper { width: 100%; height: auto; padding: 16px 0; }
        .swiper-slide { width: 300px; display: flex; align-items: center; position: relative; }
        .swiper-slide:not(:last-child)::after { content: '→'; font-size: 24px; color: var(--text-muted); position: absolute; right: -25px; top: 50%; transform: translateY(-50%); z-index: 1; }

        .chain-card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; box-shadow: var(--card-elevation); width: 100%; display: flex; flex-direction: column; transition: transform 0.2s; }
        .chain-card:hover { transform: translateY(-4px); }
        .chain-card-thumbnail { position: relative; width: 100%; padding-top: 100%; background: var(--bg); }
        .chain-card-thumbnail img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
        .chain-card-body { padding: 12px; flex-grow: 1; font-size: 13px; }
        .chain-card-title { font-weight: 600; color: var(--text); margin: 0 0 8px 0; font-size: 15px; }
        .chain-card-method { margin-bottom: 12px; flex-grow: 1; }
        .chain-card-prompt { color: var(--text-muted); margin: 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word; cursor: pointer; }
        .chain-card-prompt:hover { color: var(--text); }
        .chain-card-meta { border-top: 1px solid var(--border); padding-top: 8px; display: flex; justify-content: space-between; font-size: 12px; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center; }
        .modal-content { background: var(--card); padding: 24px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative; color: var(--text); border: 1px solid var(--border); }
        .modal-close { position: absolute; top: 16px; right: 16px; font-size: 24px; cursor: pointer; }

        .pagination-container { display: flex; justify-content: center; gap: 10px; padding: 40px 0; }
        .pagination-btn { padding: 8px 16px; background: var(--card); border: 1px solid var(--border); color: var(--text); text-decoration: none; border-radius: 4px; }
        .pagination-btn.disabled { opacity: 0.5; pointer-events: none; }
        .pagination-info { display: flex; align-items: center; gap: 5px; background: var(--card); border: 1px solid var(--border); padding: 4px 12px; border-radius: 4px; color: var(--text-muted); }
        .page-input { width: 40px; text-align: center; background: transparent; border: none; border-bottom: 1px dashed var(--border); color: var(--text); font-weight: bold; }
        .page-input:focus { outline: none; border-bottom-color: var(--accent); }

        .sb-menu { position: absolute !important; }
    </style>
</head>
<body>

    <!-- Header Section -->
    <div class="header-compact">
        <a href="cruzz.php?entity=dimensionals" class="entity-icon-link" title="Dimensionals CRUD"><?php echo $iconChar; ?></a>
        <a href="gallery_dimensionals_nu.php" class="entity-icon-link" title="Dimensionals Gallery">▦</a>
        <h2 style="margin:0; font-size:1.2rem; color:var(--text);">Dimensionals Map Runs</h2>
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
                        <a href="view_scrollmagic_map_run.php?map_run_id=<?= $run_id ?>" target="_blank" class="scroll-magic-link" title="Open Scroll Magic View"><i class="bi bi-collection-play"></i></a>
                        <a href="#" class="scroll-magic-link regen-run-btn" data-id="<?= $run_id ?>" title="Regenerate Images"><i class="bi bi-arrow-repeat"></i></a>

                        <div class="map-run-meta">
                            <span><?= date('M d, Y H:i', strtotime($run['created_at'])) ?></span>
                            &bull; <span><?= count($frames) ?> frames</span> &bull; 
                            <div style="position: relative; display: inline-flex; align-items: center;">
                                <input type="text" class="map-run-note-input" data-id="<?= $run_id ?>" value="<?= htmlspecialchars($run['note'] ?? '') ?>" placeholder="Add a note..." autocomplete="off">
                                <span class="save-indicator" id="save-indicator-<?= $run_id ?>" title="Saved">&#10003;</span>
                            </div>
                        </div>
                    </div>

                    <div class="swiper frame-chain-swiper" id="swiper-run-<?= $run_id ?>">
                        <div class="swiper-wrapper">
                            <?php foreach ($frames as $frame):
                                $filename = $frame['filename'] ?? '';
                                $img_path = !empty($filename) ? ltrim($filename, '/') : '';
                                $entity_type = htmlspecialchars($frame['entity_type'] ?? 'frames');
                                $entity_id = (int)($frame['entity_id'] ?? 0);
                                $frame_id = (int)$frame['frame_id'];
                                
                                $badge_class = 'badge-gray';
                                $creation_note = $frame['prompt'] ?: 'Initial image';
                                if (!empty($frame['edit_tool'])) { $creation_note = $frame['edit_note'] ?: 'Edited'; $badge_class = 'badge-orange'; } 
                                elseif (!empty($frame['img2img_frame_id'])) { $creation_note = $frame['prompt']; $badge_class = 'badge-blue'; }

                                $full_desc = $creation_note;
                            ?>
                            <div class="swiper-slide">
                                <div class="chain-card" data-entity="<?= $entity_type ?>" data-entity-id="<?= $entity_id ?>" data-frame-id="<?= $frame_id ?>">
                                    <div class="chain-card-thumbnail">
                                        <?php if (!empty($img_path)): ?>
                                            <a href="<?= htmlspecialchars($img_path) ?>" class="pswp-gallery-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                                                <img src="<?= htmlspecialchars($img_path) ?>" alt="Frame #<?= $frame_id ?>" loading="lazy" onload="if(this.naturalWidth){this.parentElement.dataset.pswpWidth=this.naturalWidth;this.parentElement.dataset.pswpHeight=this.naturalHeight;}">
                                            </a>
                                        <?php else: ?>
                                            <div style="display:flex; align-items:center; justify-content:center; width:100%; height:100%; background:var(--bg-lighter); color:var(--text-muted); font-size:0.8rem;">No Image</div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="chain-card-body">
                                        <h3 class="chain-card-title">Frame #<?= $frame_id ?></h3>
                                        <div class="chain-card-method">
                                            <span class="badge <?= $badge_class ?>"><?= $frame['edit_tool'] ? 'Edit' : ($frame['img2img_frame_id'] ? 'Img2Img' : 'Original') ?></span>
                                            
                                            <p class="chain-card-prompt full-desc-trigger" title="View description" data-full-desc="<?= htmlspecialchars($full_desc) ?>">
                                                <?= htmlspecialchars($creation_note) ?>
                                            </p>
                                        </div>
                                        <div class="chain-card-meta">
                                            <span class="badge badge-gray"><?= ucfirst($entity_type) ?> #<?= $entity_id ?></span>
                                            <?php if (!empty($frame['dimensional_name'])): ?>
                                                <span class="badge badge-blue"><?= htmlspecialchars($frame['dimensional_name']) ?></span>
                                            <?php endif; ?>
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
                    <span>Page</span>
                    <input type="number" class="page-input" value="<?= $page ?>" min="1" max="<?= $total_pages ?>" data-search="<?= htmlspecialchars($search) ?>">
                    <span>of <?= $total_pages ?></span>
                </div>
                <a href="<?= $page < $total_pages ? getPaginationUrl($page + 1, $search) : '#' ?>" class="pagination-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">Next</a>
            </div>

        <?php else: ?>
            <div class="empty-state" style="text-align:center; padding:40px; color:var(--text-muted);">
                <p>No map runs found for Dimensionals.</p>
                <?php if($search): ?><a href="?" class="btn-sm" style="text-decoration:none;">Clear Search</a><?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Full Description Modal -->
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

    <!-- PhotoSwipe (Identical to Sketches) -->
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
        const descModal = document.getElementById('desc-modal');
        function closeModal(modal) { modal.style.display = 'none'; }
        document.querySelectorAll('.modal-close').forEach(btn => btn.addEventListener('click', function() { closeModal(this.closest('.modal-overlay')); }));
        window.addEventListener('click', function(e) { if (e.target.classList.contains('modal-overlay')) closeModal(e.target); });

        // Description Click
        $(document).on('click', '.full-desc-trigger', function(e) {
            e.stopPropagation();
            document.getElementById('desc-modal-body').textContent = this.dataset.fullDesc;
            descModal.style.display = 'flex';
        });

        // Regenerate Run
        $(document).on('click', '.regen-run-btn', function(e) {
            e.preventDefault();
            const $btn = $(this);
            if (!confirm("Regenerate all images in this run?")) return;
            
            const orig = $btn.html();
            $btn.html('...').css('pointer-events', 'none');

            $.post('', { action: 'regenerate_run', map_run_id: $btn.data('id') }, function(res) {
                if (res.success) { Toast.show('Marked ' + res.count + ' for regeneration', 'success'); $btn.html('✔'); }
                else { Toast.show('Error: ' + res.error, 'error'); $btn.html(orig).css('pointer-events', 'auto'); }
            }, 'json').fail(function(){ Toast.show('Network Error', 'error'); $btn.html(orig).css('pointer-events', 'auto'); });
        });

        // Note Saving
        $('.map-run-note-input').on('change', function() {
            const $in = $(this);
            const $ind = $('#save-indicator-' + $in.data('id'));
            $.post('', { action: 'update_note', map_run_id: $in.data('id'), note: $in.val() }, function(res) {
                if (res.success) { 
                    $ind.addClass('visible'); setTimeout(() => $ind.removeClass('visible'), 2000); 
                } else Toast.show('Error saving note', 'error');
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

        // Scheduler Trigger
        $(document).on('click', '.runBtn', function() {
            $.post('scheduler_view.php', { action: 'run_now', id: $(this).data('id') }, function(res) {
                Toast.show(res === 'success' ? 'Task Scheduled!' : 'Failed to trigger', res === 'success' ? 'success' : 'error');
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