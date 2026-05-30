<?php
// public/view_curated_sketches_analysis.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require "entity_icons.php";

use App\UI\Modules\ModuleRegistry;

// 1. Setup UI Modules
$registry = ModuleRegistry::getInstance();
$entities_with_menu = ['characters', 'sketches', 'frames'];
$gearMenu = $registry->create('gear_menu',[
    'position' => 'top-right',
    'icon' => '&#9881;',
    'icon_size' => '1.5em',
    'show_for_entities' => $entities_with_menu,
]);
foreach ($entities_with_menu as $entity_name) { $gearMenu->addStandardActions($entity_name); }
$imageEditor = $registry->create('image_editor');

ob_start();
require __DIR__ . '/modal_frame_details.php';
$frameDetailsModal = ob_get_clean();

$pageTitle = "Curated Analysis 📜";

// --- Params ---
$minScore = isset($_GET['min_score']) ? (float)$_GET['min_score'] : 0.0;
$filterTheme = trim($_GET['theme'] ?? '');
$filterChar = trim($_GET['char'] ?? '');
$filterFunc = trim($_GET['func'] ?? '');
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest'; // Default
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// --- Build Query ---
$where =["sa.overall_quality >= :min"];
$params = [':min' => $minScore];

if ($filterTheme) {
    $where[] = "JSON_SEARCH(sa.thematics, 'one', :theme) IS NOT NULL";
    $params[':theme'] = '%' . $filterTheme . '%';
}
if ($filterChar) {
    $where[] = "JSON_SEARCH(sa.entities, 'one', :char) IS NOT NULL";
    $params[':char'] = '%' . $filterChar . '%';
}
if ($filterFunc) {
    $where[] = "JSON_SEARCH(sa.classification, 'one', :func) IS NOT NULL";
    $params[':func'] = '%' . $filterFunc . '%';
}

$whereSql = implode(" AND ", $where);

// Order Clause
if ($sort === 'newest') {
    $orderBy = "s.id DESC";
} else {
    $orderBy = "sa.overall_quality DESC, sa.id DESC";
}

// Count
$countSql = "SELECT COUNT(*) FROM sketch_analysis sa WHERE $whereSql";
$cStmt = $pdo->prepare($countSql);
foreach($params as $k=>$v) $cStmt->bindValue($k, $v);
$cStmt->execute();
$totalItems = $cStmt->fetchColumn();
$totalPages = ceil($totalItems / $limit);
if ($totalPages < 1) $totalPages = 1;

// Fetch Sketches
$sql = "
    SELECT sa.*, s.name, s.description, s.created_at, s.id as sketch_id
    FROM sketch_analysis sa
    JOIN sketches s ON sa.sketch_id = s.id
    WHERE $whereSql
    ORDER BY $orderBy
    LIMIT :limit OFFSET :offset
";

$stmt = $pdo->prepare($sql);
foreach($params as $k=>$v) $stmt->bindValue($k, $v);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper to fetch Frames
function getFramesForSketch($pdo, $sketchId) {
    $stmt = $pdo->prepare("
        SELECT f.*, ie.tool as edit_tool 
        FROM frames f 
        LEFT JOIN image_edits ie ON f.id = ie.derived_frame_id
        WHERE f.entity_type = 'sketches' AND f.entity_id = ? 
        ORDER BY f.id DESC
    ");
    $stmt->execute([$sketchId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Pagination Helper
function getPaginationUrl($targetPage) {
    global $minScore, $filterTheme, $filterChar, $filterFunc, $sort;
    return '?' . http_build_query([
        'page' => $targetPage,
        'min_score' => $minScore,
        'theme' => $filterTheme,
        'char' => $filterChar,
        'func' => $filterFunc,
        'sort' => $sort
    ]);
}

// Base params string for JS input
$baseParams = http_build_query([
    'min_score' => $minScore,
    'theme' => $filterTheme,
    'char' => $filterChar,
    'func' => $filterFunc,
    'sort' => $sort
]);

ob_start();
?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<!-- Swiper & PhotoSwipe -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
<script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<script type="module">
    import PhotoSwipeLightbox from 'https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.esm.js';
    window.initLightbox = () => {
        const lightbox = new PhotoSwipeLightbox({
            gallery: '.pswp-gallery',
            children: '.pswp-gallery-item',
            pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js'),
            initialZoomLevel: 'fit',
            secondaryZoomLevel: 1
        });
        lightbox.init();
    };
</script>

<style>
    /* Base Grid */
    .curator-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(400px, 1fr)); gap: 20px; padding: 20px; }
    .curator-card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
    
    .card-header { padding: 12px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: rgba(0,0,0,0.02); }
    
    .score-badge { font-weight: 800; font-size: 1.2rem; padding: 4px 10px; border-radius: 6px; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.3); }
    .score-high { background: #10b981; } .score-mid { background: #f59e0b; } .score-low { background: #ef4444; } .score-zero { background: #6b7280; }
    
    .card-body { padding: 15px; font-size: 0.9rem; border-bottom: 1px solid var(--border); }
    .analysis-section { margin-bottom: 12px; }
    .label { font-weight: 700; color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase; display: block; margin-bottom: 4px; letter-spacing: 0.05em; }
    
    /* Pills */
    .pill { display: inline-block; padding: 2px 8px; background: rgba(0,0,0,0.05); border-radius: 12px; font-size: 0.8rem; margin-right: 4px; margin-bottom: 4px; text-decoration: none; color: var(--text); border: 1px solid transparent; transition: all 0.2s; }
    .pill:hover { border-color: var(--accent); color: var(--accent); background: rgba(59,130,246,0.1); }
    .pill-func { background: rgba(139,92,246,0.1); color: #7c3aed; }
    
    /* Frame Section (Bottom) */
    .card-frames-section { background: var(--bg); padding: 15px 0; }
    .frame-chain-swiper { width: 100%; }
    .swiper-slide { width: 180px; display: flex; align-items: center; position: relative; overflow: visible !important; }
    
    /* Mini Frame Card */
    .mini-chain-card { background: var(--card); border: 1px solid var(--border); border-radius: 6px; overflow: visible !important; box-shadow: 0 2px 4px rgba(0,0,0,0.1); width: 100%; display: flex; flex-direction: column; position: relative; }
    .mini-card-thumb { position: relative; width: 100%; padding-top: 100%; background: var(--bg); border-top-left-radius: 6px; border-top-right-radius: 6px; overflow: hidden; }
    .mini-card-thumb img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
    .mini-card-body { padding: 8px; font-size: 11px; }
    .mini-desc-link { color: var(--text-muted); cursor: pointer; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.3; }
    .mini-desc-link:hover { color: var(--accent); }

    /* Gear Menu Btn Override */
    .gear-menu-btn { 
        position: absolute; top: 4px; right: 4px; z-index: 50; 
        cursor: pointer; background: rgba(255,255,255,0.9); 
        border-radius: 4px; padding: 2px; line-height: 1; 
        box-shadow: 0 1px 3px rgba(0,0,0,0.2); 
    }
    [data-theme="dark"] .gear-menu-btn { background: rgba(0,0,0,0.6); color: #fff; }

    /* Filter Bar */
    .filter-bar { padding: 15px 20px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; gap: 15px; align-items: center; position: sticky; top: 0; z-index: 100; flex-wrap: wrap; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
    .filter-input { padding: 6px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); color: var(--text); font-size: 0.9rem; }
    
    /* Pagination Input Styles */
    .pagination-container { display: flex; justify-content: center; align-items: center; gap: 15px; padding: 40px 0; }
    .pagination-btn { padding: 8px 16px; background: var(--card); border: 1px solid var(--border); color: var(--text); text-decoration: none; border-radius: 4px; transition: background 0.2s; }
    .pagination-btn:hover { background: var(--bg); border-color: var(--accent); }
    .pagination-btn.disabled { opacity: 0.5; pointer-events: none; }
    .pagination-info { font-size: 1rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px; }
    .page-input { width: 50px; text-align: center; background: transparent; border: none; border-bottom: 2px dashed var(--border); color: var(--text); font-weight: bold; font-size: 1rem; padding: 2px; }
    .page-input:focus { outline: none; border-color: var(--accent); }
    
    /* Sorting Buttons */
    .sort-btn {
        background: var(--bg); border: 1px solid var(--border); color: var(--text);
        padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 0.85rem;
    }
    .sort-btn.active {
        background: var(--accent); color: #fff; border-color: var(--accent);
    }

    /* Modal */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center; }
    .modal-content { background: var(--card); padding: 24px; border-radius: 8px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; position: relative; color: var(--text); border: 1px solid var(--border); }
    .modal-close { position: absolute; top: 16px; right: 16px; font-size: 24px; cursor: pointer; }

    /* Collapsible filter styles */
    .filter-bar.collapsed {
        height: 56px;
        padding-top: 8px;
        padding-bottom: 8px;
        align-items: center;
        overflow: hidden;
    }

    .filter-bar.collapsed form.filter-form {
        display: none !important; /* hide full form when collapsed */
    }

    .filter-bar .filter-summary {
        display: none;
    }

    /* when collapsed show summary */
    .filter-bar.collapsed .filter-summary {
        display: flex;
        gap: 8px;
        align-items: center;
    }

    /* small visual tweak for toggle */
    #filter-toggle {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 6px;
        border: 1px solid var(--border);
        background: var(--card);
        cursor: pointer;
    }
</style>

<div class="filter-bar">
    <div style="font-size:1.2rem; margin-right:10px;">📜</div>

    <!-- Toggle Button -->
    <button id="filter-toggle" class="gear-menu-btn" aria-pressed="false" title="Toggle filters" type="button" style="margin-right:8px;">
        <span id="filter-toggle-icon">▾</span>
    </button>

    <!-- Collapsed summary (shown when collapsed) -->
    <div class="filter-summary" style="display:none; font-size:0.95rem; margin-right:10px; color:var(--text-muted);">
        <!-- content filled by JS -->
    </div>

    <form method="GET" class="filter-form" style="display:flex; gap:15px; align-items:center; flex-wrap:wrap; flex:1;">
        <div style="display:flex; align-items:center; gap:5px;">
            <span class="label" style="margin:0;">Min Score</span>
            <input type="number" name="min_score" value="<?= $minScore ?>" step="0.5" min="0" max="10" style="width:50px;" class="filter-input">
        </div>
        <input type="text" name="func" placeholder="Filter Function..." value="<?= htmlspecialchars($filterFunc) ?>" class="filter-input">
        <input type="text" name="theme" placeholder="Filter Theme..." value="<?= htmlspecialchars($filterTheme) ?>" class="filter-input">
        <input type="text" name="char" placeholder="Filter Character..." value="<?= htmlspecialchars($filterChar) ?>" class="filter-input">

        <div style="height:25px; border-left:1px solid var(--border); margin:0 5px;"></div>

        <!-- Sort Buttons -->
        <button type="submit" name="sort" value="rating" class="sort-btn <?= $sort === 'rating' ? 'active' : '' ?>">
            Sort by Rating
        </button>
        <button type="submit" name="sort" value="newest" class="sort-btn <?= $sort === 'newest' ? 'active' : '' ?>">
            Sort by Most Current
        </button>

        <button type="submit" class="btn-sm" style="padding: 6px 15px; cursor: pointer; margin-left:auto;">Apply</button>
        <?php if($filterFunc||$filterTheme||$filterChar||$minScore!=0||$sort!='newest'): ?>
            <a href="?" class="btn-sm" style="text-decoration:none;">Reset</a>
        <?php endif; ?>
    </form>

    <div>
        <a href="view_curated_sketches.php?sort=<?= $sort ?>" class="btn-sm" style="text-decoration:none;">Switch View 🕵️</a>
    </div>
</div>

<div class="container">
    <div class="curator-grid ps-gallery pswp-gallery">
        <?php if(empty($rows)): ?>
            <div style="grid-column:1/-1; text-align:center; padding:40px; color:var(--text-muted);">
                No analyzed sketches found matching criteria.
            </div>
        <?php endif; ?>

        <?php foreach($rows as $row): 
            $score = (float)$row['overall_quality'];
            $scoreClass = $score >= 8.0 ? 'score-high' : ($score >= 5.0 ? 'score-mid' : ($score > 0 ? 'score-low' : 'score-zero'));
            
            $classif = json_decode($row['classification'], true) ?? [];
            $themes = json_decode($row['thematics'], true) ?? [];
            $recs = json_decode($row['recommendations'], true) ??[];
            $entities = json_decode($row['entities'], true) ??[];
            
            // Fetch Frames
            $frames = getFramesForSketch($pdo, $row['sketch_id']);
        ?>
        <div class="curator-card" id="card-<?= $row['sketch_id'] ?>">
            
            <!-- 1. Text Analysis (Top) -->
            <div class="card-header">
                <span class="score-badge <?= $scoreClass ?>"><?= $score ?></span>
                <div style="text-align:right;">
                    <div style="font-weight:600; font-size:0.9rem;">#<?= $row['sketch_id'] ?></div>
                    <div style="font-size:0.75rem; color:var(--text-muted);"><?= date('M d', strtotime($row['created_at'])) ?></div>
                </div>
            </div>
            
            <div class="card-body">
                <!-- Title -->
                <div style="font-weight:600; margin-bottom:8px; font-size:1.1em;"><?= htmlspecialchars($row['name']) ?></div>

                <!-- Function -->
                <?php if(!empty($classif['narrative_function'])): ?>
                    <div class="analysis-section">
                        <span class="label">Function</span>
                        <?php 
                            $nFunc = is_array($classif['narrative_function']) ? ($classif['narrative_function']['name'] ?? $classif['narrative_function']['description'] ?? '') : (is_string($classif['narrative_function']) ? $classif['narrative_function'] : '');
                            if ($nFunc !== ''):
                        ?>
                            <a href="?func=<?= urlencode($nFunc) ?>" class="pill pill-func"><?= htmlspecialchars($nFunc) ?></a>
                        <?php endif; ?>
                        
                        <?php if(!empty($classif['emotional_tone'])): 
                            $eTone = is_array($classif['emotional_tone']) ? ($classif['emotional_tone']['name'] ?? $classif['emotional_tone']['description'] ?? '') : (is_string($classif['emotional_tone']) ? $classif['emotional_tone'] : '');
                            if ($eTone !== ''):
                        ?>
                            <span class="pill"><?= htmlspecialchars($eTone) ?></span>
                        <?php endif; endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Themes -->
                <?php if(!empty($themes['primary_themes'])): ?>
                    <div class="analysis-section">
                        <span class="label">Themes</span>
                        <?php 
                            $themeList = is_array($themes['primary_themes']) ? $themes['primary_themes'] : [$themes['primary_themes']];
                            foreach($themeList as $t): 
                                $tStr = is_array($t) ? ($t['name'] ?? $t['theme'] ?? '') : (is_string($t) ? $t : '');
                                if($tStr !== ''):
                        ?>
                            <a href="?theme=<?= urlencode($tStr) ?>" class="pill"><?= htmlspecialchars($tStr) ?></a>
                        <?php endif; endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Characters -->
                <?php if(!empty($entities['characters'])): ?>
                    <div class="analysis-section">
                        <span class="label">Characters</span>
                        <?php 
                            $charList = is_array($entities['characters']) ? $entities['characters'] : [$entities['characters']];
                            foreach($charList as $c): 
                                $cStr = is_array($c) ? ($c['name'] ?? $c['character'] ?? '') : (is_string($c) ? $c : '');
                                if ($cStr !== ''):
                        ?>
                            <a href="?char=<?= urlencode($cStr) ?>" class="pill">👤 <?= htmlspecialchars($cStr) ?></a>
                        <?php endif; endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Suggestion -->
                <?php if(!empty($recs['potential_use'])): 
                    $suggStr = is_array($recs['potential_use']) ? ($recs['potential_use']['description'] ?? '') : (is_string($recs['potential_use']) ? $recs['potential_use'] : '');
                    if ($suggStr !== ''):
                ?>
                    <div class="analysis-section" style="background:rgba(245,159,11,0.1); padding:8px; border-radius:4px; border:1px dashed rgba(245,159,11,0.3); margin-bottom:0;">
                        <span class="label" style="color:#d97706;">Suggestion</span>
                        <div style="font-style:italic; line-height:1.3; font-size:0.85rem;"><?= htmlspecialchars($suggStr) ?></div>
                    </div>
                <?php endif; endif; ?>
            </div>
            
            <!-- 2. Visual Slider (Bottom) -->
            <?php if(!empty($frames)): ?>
            <div class="card-frames-section">
                <div class="swiper frame-chain-swiper">
                    <div class="swiper-wrapper">
                        <?php foreach ($frames as $frame):
                            $img = ltrim($frame['filename'], '/');
                            $frameId = $frame['id'];
                            
                            // Gear Menu Attributes
                            $gearAttr = 'data-gear-menu data-entity="frames" data-entity-id="'.$frameId.'" data-frame-id="'.$frameId.'" data-img-url="'.$img.'"';
                        ?>
                        <div class="swiper-slide">
                            <div class="mini-chain-card" <?= $gearAttr ?>>
                                <div class="mini-card-thumb">
                                    <a href="<?= htmlspecialchars($img) ?>" class="pswp-gallery-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                                        <img src="<?= htmlspecialchars($img) ?>" loading="lazy" onload="this.parentElement.dataset.pswpWidth = this.naturalWidth; this.parentElement.dataset.pswpHeight = this.naturalHeight;">
                                    </a>
                                </div>
                                <div class="mini-card-body">
                                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                                        <span style="font-weight:700;">#<?= $frameId ?></span>
                                        <?php if($frame['edit_tool']): ?><span style="color:#f59e0b;">Edit</span><?php endif; ?>
                                    </div>
                                    <div class="mini-desc-link full-desc-trigger" data-full-desc="<?= htmlspecialchars($row['description']) ?>">
                                        <?= htmlspecialchars(substr($row['description'], 0, 40)) ?>...
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="swiper-scrollbar"></div>
                </div>
            </div>
            <?php else: ?>
                <div style="padding:15px; text-align:center; color:var(--text-muted); font-size:0.8rem; background:var(--bg);">No frames</div>
            <?php endif; ?>

        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Pagination (Input Style) -->
<div class="pagination-container">
    <a href="<?= $page > 1 ? getPaginationUrl($page - 1) : '#' ?>" class="pagination-btn <?= $page <= 1 ? 'disabled' : '' ?>">Previous</a>
    <div class="pagination-info">
        <input type="number" class="page-input" value="<?= $page ?>" min="1" max="<?= $totalPages ?>" data-base-params="<?= htmlspecialchars($baseParams) ?>"> / <?= $totalPages ?>
    </div>
    <a href="<?= $page < $totalPages ? getPaginationUrl($page + 1) : '#' ?>" class="pagination-btn <?= $page >= $totalPages ? 'disabled' : '' ?>">Next</a>
</div>

<!-- Description Modal -->
<div id="desc-modal" class="modal-overlay">
    <div class="modal-content">
        <span class="modal-close" onclick="$('#desc-modal').hide()">&times;</span>
        <h3 class="modal-title" style="margin-top:0;">Scene Description</h3>
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

<script>
$(function() {
    // 1. Attach Gear Menu
    function attachGearMenu() {
        if (window.GearMenu && typeof window.GearMenu.attach === 'function') {
            const container = document.querySelector('.container');
            if(container) window.GearMenu.attach(container);
        } else { setTimeout(attachGearMenu, 200); }
    }
    attachGearMenu();
    
    // 2. Init Swipers
    document.querySelectorAll('.frame-chain-swiper').forEach(el => {
        new Swiper(el, {
            slidesPerView: 'auto', 
            spaceBetween: 15,
            freeMode: true,
            scrollbar: { el: el.querySelector('.swiper-scrollbar'), hide: true },
            slidesOffsetBefore: 15, slidesOffsetAfter: 15
        });
    });
    
    // 3. PhotoSwipe
    if(window.initLightbox) window.initLightbox();

    // 4. Modal Interactions
    $(document).on('click', '.full-desc-trigger', function(e) {
        e.stopPropagation(); 
        $('#desc-modal-body').text(this.dataset.fullDesc); 
        $('#desc-modal').css('display', 'flex');
    });
    
    window.addEventListener('click', function(e) { if (e.target.classList.contains('modal-overlay')) $(e.target).hide(); });

    // 5. Pagination Input Logic
    $('.page-input').on('change', function() {
        let val = parseInt($(this).val());
        const max = parseInt($(this).attr('max'));
        if (val < 1) val = 1; 
        if (val > max) val = max;
        
        // Use base params from data attribute to preserve filters
        const baseParams = $(this).data('base-params');
        window.location.href = '?' + baseParams + '&page=' + val;
    });

    // Collapsible filter bar with localStorage persistence
    (function() {
        const STORAGE_KEY = 'spw.curator.filter.collapsed';
        const $filterBar = $('.filter-bar');
        const $toggle = $('#filter-toggle');
        const $toggleIcon = $('#filter-toggle-icon');
        const $summary = $filterBar.find('.filter-summary');
        const $form = $filterBar.find('form.filter-form');

        if (!$filterBar.length || !$toggle.length) return;

        function countActiveFilters() {
            // min_score considered active if not zero
            let active = 0;
            const minScore = parseFloat($form.find('input[name="min_score"]').val() || '0');
            if (!isNaN(minScore) && minScore !== 0) active++;
            ['func','theme','char'].forEach(name => {
                const v = ($form.find('input[name="'+name+'"]').val() || '').trim();
                if (v !== '') active++;
            });
            return active;
        }

        function updateSummaryText() {
            const active = countActiveFilters();
            if (active === 0) {
                $summary.text('No filters');
            } else if (active === 1) {
                $summary.text('1 active filter');
            } else {
                $summary.text(active + ' active filters');
            }
        }

        function setCollapsed(collapsed, save = true) {
            if (collapsed) {
                $filterBar.addClass('collapsed');
                $toggle.attr('aria-pressed', 'true');
                $toggleIcon.text('▸'); // collapsed icon
            } else {
                $filterBar.removeClass('collapsed');
                $toggle.attr('aria-pressed', 'false');
                $toggleIcon.text('▾'); // expanded icon
            }
            updateSummaryText();
            if (save) {
                try { localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0'); } catch (e) {}
            }
        }

        // toggle on button click
        $toggle.on('click', function() {
            const collapsed = $filterBar.hasClass('collapsed');
            setCollapsed(!collapsed);
        });

        // init from storage
        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            setCollapsed(saved === '1', false);
        } catch (e) {
            setCollapsed(false, false);
        }

        // update summary when input changes (keeps collapsed summary accurate)
        $form.find('input').on('input change', function() {
            if ($filterBar.hasClass('collapsed')) updateSummaryText();
        });

        // ensure summary is set once at start
        updateSummaryText();
    })();
});
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>