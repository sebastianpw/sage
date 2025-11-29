<?php
// public/view_frame_chain.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\UI\Modules\ModuleRegistry;

// --- 1. Data Fetching ---
$start_frame_id = filter_input(INPUT_GET, 'start_frame_id', FILTER_VALIDATE_INT);
$frames = [];

if ($start_frame_id) {
    // NEW & IMPROVED: This recursive query now unifies both lineage tracking methods.
    // It uses COALESCE to determine the parent ID, prioritizing the legacy 'img2img_frame_id'
    // and falling back to the newer 'frames_chains' table for image editor steps.
    $sql = <<<SQL
    WITH RECURSIVE frame_chain AS (
      -- Anchor: Start with the specified frame
      SELECT
        f.id,
        -- Unify parent finding: Use img2img_frame_id if it exists, otherwise check frames_chains
        COALESCE(
          f.img2img_frame_id,
          (SELECT parent_frame_id FROM frames_chains WHERE frame_id = f.id ORDER BY id DESC LIMIT 1)
        ) as parent_id,
        0 as level
      FROM frames f
      WHERE f.id = :start_frame_id

      UNION ALL

      -- Recursive: Follow the unified parent_id backwards up the chain
      SELECT
        f.id,
        COALESCE(
          f.img2img_frame_id,
          (SELECT parent_frame_id FROM frames_chains WHERE frame_id = f.id ORDER BY id DESC LIMIT 1)
        ) as parent_id,
        fc.level + 1 as level
      FROM frame_chain fc
      JOIN frames f ON f.id = fc.parent_id
      WHERE fc.parent_id IS NOT NULL
        AND fc.level < 20 -- Safety limit
    )
    SELECT
      f.id as frame_id,
      f.name as frame_name,
      f.filename,
      f.prompt,
      f.entity_type,
      f.entity_id,
      chain.level,
      g.name as generative_name,
      ie.tool as edit_tool,
      ie.note as edit_note,
      f.img2img_frame_id
    FROM frame_chain chain
    JOIN frames f ON f.id = chain.id
    LEFT JOIN generatives g ON g.id = f.entity_id AND f.entity_type = 'generatives'
    LEFT JOIN image_edits ie ON ie.derived_frame_id = f.id
    ORDER BY chain.level ASC; -- Order ASC to show most recent frame first or DESC to show oldest (root) frame first
SQL;


    $stmt = $pdo->prepare($sql);
    $stmt->execute(['start_frame_id' => $start_frame_id]);
    $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Check if we are in modal view
$is_modal_view = isset($_GET['view']) && $_GET['view'] === 'modal';

// --- 2. UI Module Setup ---
$registry = ModuleRegistry::getInstance();

$entities_with_menu = ['characters', 'character_poses', 'animas', 'locations', 'backgrounds', 'artifacts', 'vehicles', 'scene_parts', 'controlnet_maps', 'spawns', 'generatives', 'sketches', 'prompt_matrix_blueprints', 'composites'];

$gearMenu = $registry->create('gear_menu', [
    'position' => 'top-right',
    'icon' => '&#9881;',
    'icon_size' => '1.5em',
    'show_for_entities' => $entities_with_menu,
]);



$actions_to_add = [
    [
        'label' => 'View Frame',
        'icon' => '👁️',
        'callback' => 'window.showFrameDetailsModal(frameId);'
    ],
    [
        'label' => 'View Frame Chain',
        'icon' => '🔗',
        'callback' => 'window.showFrameChainInModal(frameId);'
    ],
    [
        'label' => 'Import to Generative',
        'icon' => '⚡',
        'callback' => 'window.importGenerative(entity, entityId, frameId);'
    ],
    [
        'label' => 'Edit Entity',
        'icon' => '✏️',
        'callback' => 'window.showEntityFormInModal(entity, entityId);'
    ],
    [
        'label' => 'Edit Image',
        'icon' => '🖌️',
        'callback' => 'const $w = $(wrapper); ImageEditorModal.open({ entity: entity, entityId: entityId, frameId: frameId, src: $w.find(\'img\').attr(\'src\') });'
    ],
    [
        'label' => 'Add to Storyboard',
        'icon' => '🎬',
        'callback' => 'window.selectStoryboard(frameId, $(wrapper));'
    ],
    [
        'label' => 'Assign to Composite',
        'icon' => '🧩',
        'callback' => 'window.assignToComposite(entity, entityId, frameId);'
    ],
    [
        'label' => 'Import to ControlNet Map',
        'icon' => '☠️',
        'callback' => 'window.importControlNetMap(entity, entityId, frameId);'
    ],
    [
        'label' => 'Use Prompt Matrix',
        'icon' => '🌌',
        'callback' => 'window.usePromptMatrix(entity, entityId, frameId);'
    ],
    [
        'label' => 'Delete Frame',
        'icon' => '🗑️',
        'callback' => 'window.deleteFrame(entity, entityId, frameId);'
    ],
];


foreach ($entities_with_menu as $entity) {
    foreach ($actions_to_add as $action) {
        $gearMenu->addAction($entity, $action);
    }
}

$imageEditor = $registry->create('image_editor');

ob_start();
require __DIR__ . '/modal_frame_details.php';
$frameDetailsModal = ob_get_clean();

// --- 3. Render the Page ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.7">
    <title>Frame Generation Chain</title>
    <link rel="stylesheet" href="/css/base.css">

    <!-- SwiperJS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <!-- PhotoSwipe -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
    
    <!-- Custom Styles for this View -->
    <style>
        /* Add a new badge color for Image Edits */
        .badge-orange {
            background-color: rgba(245, 159, 11, 0.1);
            color: var(--orange);
            border-color: rgba(245, 159, 11, 0.15);
        }

        .frame-chain-container {
            padding: 24px 0;
        }
        .section-header a {
            color: var(--accent);
            text-decoration: none;
            font-size: 0.8em;
            margin-left: 12px;
        }

        .frame-chain-swiper {
            width: 100%;
            height: auto;
            padding: 16px 0; /* Space for shadow and overflow */
        }

        .swiper-slide {
            width: 300px; /* Card width */
            height: auto;
            display: flex;
            align-items: center; /* For the connector */
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
        }

        .chain-card-prompt {
            color: var(--text-muted);
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            word-break: break-word;
        }
        .chain-card-meta {
            border-top: 1px solid var(--border);
            padding-top: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
        }
        .chain-card-meta .badge {
             margin: 0;
        }
    </style>
</head>
<body>

    <div class="container">
        
        <?php if (!empty($frames)): ?>
            
            <div class="frame-chain-container">
                <!-- Swiper -->
                <div class="swiper frame-chain-swiper">
                    <div class="swiper-wrapper pswp-gallery">

                        <?php foreach ($frames as $frame):
                            $img_path = ltrim($frame['filename'], '/');
                            $entity_type = htmlspecialchars($frame['entity_type']);
                            $entity_id = (int)$frame['entity_id'];
                            $frame_id = (int)$frame['frame_id'];
                            
                            // --- NEW: More robust logic to determine creation method ---
                            $creation_method = 'Original';
                            $creation_note = $frame['prompt'] ?: 'Initial image';
                            $badge_class = 'badge-gray';

                            // An image editor step is identified by the presence of an 'edit_tool'
                            if (!empty($frame['edit_tool'])) {
                                $creation_method = 'Image Edit';
                                $creation_note = $frame['edit_note'] ?: 'Edited in image editor';
                                $badge_class = 'badge-orange';
                            } 
                            // An img2img step is identified by the legacy column
                            elseif (!empty($frame['img2img_frame_id'])) {
                                $creation_method = 'Img2Img';
                                $creation_note = $frame['prompt'];
                                $badge_class = 'badge-blue';
                            }
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
                                        <p class="chain-card-prompt" title="<?= htmlspecialchars($creation_note) ?>">
                                            <?= htmlspecialchars($creation_note) ?>
                                        </p>
                                    </div>
                                    
                                    <div class="chain-card-meta">
                                        <span class="badge badge-gray">
                                            <?= ucfirst($entity_type) ?> #<?= $entity_id ?>
                                        </span>
                                        <?php if ($frame['generative_name']): ?>
                                             <span class="badge badge-blue"><?= htmlspecialchars($frame['generative_name']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                    </div>
                    <!-- Add Pagination -->
                    <div class="swiper-pagination"></div>

                    <!-- Add Navigation -->
                    <div class="swiper-button-next"></div>
                    <div class="swiper-button-prev"></div>
                </div>
            </div>

        <?php else: ?>
            <div class="empty-state">
                <p>No frame chain found. Please provide a valid 'start_frame_id' in the URL.</p>
            </div>
        <?php endif; ?>

    </div>

    <!-- Render modular UI components -->
    <?= $eruda ?? '' ?>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="/js/gear_menu_globals.js"></script>
    <?= $gearMenu->render() ?>
    <?= $imageEditor->render() ?>
    <?= $frameDetailsModal ?>

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
        const swiper = new Swiper('.frame-chain-swiper', {
            slidesPerView: 'auto',
            spaceBetween: 40,
            freeMode: true,
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            pagination: {
                el: '.swiper-pagination',
                clickable: true,
            },
            slidesOffsetBefore: 20,
            slidesOffsetAfter: 20,
        });

        if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
            const container = document.querySelector('.frame-chain-container');
            if(container) {
                window.GearMenu.attach(container);
            }
        }
    });
    </script>

</body>
</html>

