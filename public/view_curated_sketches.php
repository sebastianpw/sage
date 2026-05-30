<?php
// public/view_curated_sketches.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require "entity_icons.php";

use App\UI\Modules\ModuleRegistry;

// 1. Setup UI Modules
$registry = ModuleRegistry::getInstance();
// Ensure 'frames' is in this list as that is what the cards represent
$entities_with_menu = ['characters', 'sketches', 'frames']; 
$gearMenu = $registry->create('gear_menu', [
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

$pageTitle = "Curated Sketches 🕵️";

// --- Params ---
$minScore = isset($_GET['min_score']) ? (float)$_GET['min_score'] : 0.0;
$filterTheme = trim($_GET['theme'] ?? '');
$filterChar = trim($_GET['char'] ?? '');
$filterFunc = trim($_GET['func'] ?? '');
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'rating'; // Default sort
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; 
$offset = ($page - 1) * $limit;

// --- Build Query ---
$where = ["sa.overall_quality >= :min"];
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
    // Default: Rating
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
$sketches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper to fetch Frames + Edit Info
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
            pswpModule: () => import('https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.esm.js')
        });
        lightbox.init();
    };
</script>

<style>
    /* Filter Bar */
    .filter-bar { padding: 15px 20px; background: var(--card); border-bottom: 1px solid var(--border); display: flex; gap: 15px; align-items: center; position: sticky; top: 0; z-index: 100; flex-wrap: wrap; box-shadow: 0 4px 12px rgba(0,0,0,0.05); transition: height 0.2s, padding 0.2s; }
    .filter-group { display: flex; align-items: center; gap: 5px; }
    .filter-input { padding: 6px; border: 1px solid var(--border); border-radius: 4px; background: var(--bg); color: var(--text); font-size: 0.9rem; }
    
    /* Collapsible Filter Styles */
    .filter-bar.collapsed {
        height: 56px;
        padding-top: 8px;
        padding-bottom: 8px;
        align-items: center;
        overflow: hidden;
    }
    .filter-bar.collapsed form.filter-form { display: none !important; }
    .filter-bar .filter-summary { display: none; }
    .filter-bar.collapsed .filter-summary { display: flex; gap: 8px; align-items: center; }
    
    #filter-toggle {
        display: inline-flex; align-items: center; justify-content: center;
        width: 34px; height: 34px; border-radius: 6px;
        border: 1px solid var(--border); background: var(--card); cursor: pointer;
    }
    
    /* Sorting Buttons */
    .sort-btn {
        background: var(--bg); border: 1px solid var(--border); color: var(--text);
        padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 0.85rem;
    }
    .sort-btn.active {
        background: var(--accent); color: #fff; border-color: var(--accent);
    }

    /* Map Run / Chain Styles */
    .map-run-section { padding: 24px 0; border-bottom: 1px solid var(--border); }
    .map-run-header { padding: 0 20px 10px 20px; display: flex; align-items: center; gap: 12px; }
    .map-run-title { font-size: 0.9rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; margin: 0; }
    .map-run-meta { font-size: 0.8rem; color: var(--text-muted); margin-top: 4px; display: flex; align-items: center; gap: 8px; }

    .frame-chain-swiper { width: 100%; padding: 16px 0; }
    .swiper-slide { width: 300px; display: flex; align-items: center; position: relative; overflow: visible !important; }
    .swiper-slide:not(:last-child)::after { content: '→'; font-size: 24px; color: var(--text-muted); position: absolute; right: -25px; top: 50%; transform: translateY(-50%); z-index: 1; }

    .chain-card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; overflow: visible !important; box-shadow: var(--card-elevation); width: 100%; display: flex; flex-direction: column; transition: transform 0.2s; position: relative; }
    .chain-card:hover { transform: translateY(-4px); }
    .chain-card-thumbnail { position: relative; width: 100%; padding-top: 100%; background: var(--bg); border-top-left-radius: 8px; border-top-right-radius: 8px; overflow: hidden; }
    .chain-card-thumbnail img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
    .chain-card-body { padding: 12px; flex-grow: 1; font-size: 13px; }
    .chain-card-title { font-weight: 600; color: var(--text); margin: 0 0 8px 0; font-size: 15px; }
    .chain-card-prompt { color: var(--text-muted); margin: 0; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; cursor: pointer; }

    /* Badges */
    .score-badge { font-weight: 800; font-size: 1.1rem; padding: 2px 8px; border-radius: 4px; color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,0.3); }
    .score-high { background: #10b981; } .score-mid { background: #f59e0b; } .score-low { background: #ef4444; } .score-zero { background: #6b7280; }
    
    .badge-gray { background: rgba(100,100,100,0.1); color: var(--text-muted); border-color: var(--border); }
    .badge-blue { background: rgba(59,130,246,0.1); color: #3b82f6; border-color: rgba(59,130,246,0.2); }
    .badge-orange { background: rgba(245,159,11,0.1); color: #f59e0b; border-color: rgba(245,159,11,0.2); }

    .badge-curator {
        background: linear-gradient(135deg, rgba(16,185,129,0.1), rgba(52,211,153,0.1));
        color: #10b981;
        border: 1px solid rgba(16,185,129,0.3);
        cursor: pointer;
        display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 10px; font-weight: 600; margin-right: 4px; margin-bottom: 4px;
    }
    .badge-curator:hover { background: rgba(16,185,129,0.15); }
    
    /* Modals */
    .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 9999; justify-content: center; align-items: center; }
    .modal-content { background: var(--card); padding: 24px; border-radius: 8px; max-width: 650px; width: 95%; max-height: 85vh; overflow-y: auto; position: relative; color: var(--text); border: 1px solid var(--border); }
    .modal-close { position: absolute; top: 16px; right: 16px; font-size: 24px; cursor: pointer; }
    .modal-row { margin-bottom: 12px; border-bottom: 1px dashed var(--border); padding-bottom: 8px; display: flex; align-items: flex-start; }
    .modal-label { font-weight: bold; color: var(--text-muted); font-size: 0.85rem; display: block; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.05em; }
    .modal-value { font-size: 0.95rem; }
    
    .pill { display: inline-block; padding: 2px 8px; background: rgba(0,0,0,0.05); border-radius: 12px; font-size: 0.8rem; margin-right: 4px; margin-bottom: 4px; text-decoration: none; color: var(--text); border: 1px solid transparent; }
    .pill:hover { border-color: var(--accent); color: var(--accent); background: rgba(59,130,246,0.1); }
    .pill-theme { border-color: var(--accent); color: var(--accent); background: rgba(59,130,246,0.1); }
    .pill-char { border-color: #f59e0b; color: #f59e0b; background: rgba(245,159,11,0.1); }
    .pill-func { background: rgba(139,92,246,0.1); color: #7c3aed; }
    
    /* Pagination */
    .pg-bar { display: flex; justify-content: center; gap: 10px; padding: 20px; }
    .pg-btn { padding: 5px 10px; border: 1px solid var(--border); background: var(--card); border-radius: 4px; text-decoration: none; color: var(--text); }
    .pg-btn.active { background: var(--accent); color: #fff; border-color: var(--accent); }
    
    /* Gear Menu Override */
    .gear-menu-btn { 
        position: absolute; top: 8px; right: 8px; z-index: 50; cursor: pointer; 
        background: rgba(255,255,255,0.9); border-radius: 4px; padding: 4px; 
        line-height: 1; box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    [data-theme="dark"] .gear-menu-btn { background: rgba(0,0,0,0.6); color: #fff; }
</style>

<div class="filter-bar">
    <div style="font-size:1.2rem; margin-right:10px;">🕵️</div>
    
    <!-- Toggle Button -->
    <button id="filter-toggle" title="Toggle filters" type="button" style="margin-right:8px;">
        <span id="filter-toggle-icon">▾</span>
    </button>

    <!-- Collapsed summary -->
    <div class="filter-summary" style="display:none; font-size:0.95rem; margin-right:10px; color:var(--text-muted);">
        <!-- content filled by JS -->
    </div>

    <form method="GET" class="filter-form" style="display:flex; gap:15px; align-items:center; flex-wrap:wrap; flex:1;">
        <div class="filter-group">
            <span class="label" style="margin:0;">Min Score</span>
            <input type="number" name="min_score" value="<?= $minScore ?>" step="0.5" min="0" max="10" style="width:50px;" class="filter-input">
        </div>
        <div class="filter-group">
            <input type="text" name="func" placeholder="Filter Function..." value="<?= htmlspecialchars($filterFunc) ?>" class="filter-input">
        </div>
        <div class="filter-group">
            <input type="text" name="theme" placeholder="Filter Theme..." value="<?= htmlspecialchars($filterTheme) ?>" class="filter-input">
        </div>
        <div class="filter-group">
            <input type="text" name="char" placeholder="Filter Character..." value="<?= htmlspecialchars($filterChar) ?>" class="filter-input">
        </div>
        
        <div style="height:25px; border-left:1px solid var(--border); margin:0 5px;"></div>

        <!-- Sort Buttons -->
        <button type="submit" name="sort" value="rating" class="sort-btn <?= $sort !== 'newest' ? 'active' : '' ?>">
            Sort by Rating
        </button>
        <button type="submit" name="sort" value="newest" class="sort-btn <?= $sort === 'newest' ? 'active' : '' ?>">
            Sort by Most Current
        </button>
        
        <button type="submit" class="btn-sm" style="flex:0 0 auto; width:auto; padding: 6px 15px; cursor: pointer; margin-left: auto;">Apply</button>
        <?php if($filterFunc||$filterTheme||$filterChar||$minScore!=0||$sort!='rating'): ?>
            <a href="?" class="btn-sm" style="text-decoration:none;">Reset</a>
        <?php endif; ?>
    </form>
    <div>
        <a href="view_curated_sketches_analysis.php?sort=<?= $sort ?>" class="btn-sm" style="text-decoration:none;">Switch view 📜</a>
    </div>
</div>

<div class="container">
    <?php if(empty($sketches)): ?>
        <div style="text-align:center; padding:40px; color:var(--text-muted);">No curated sketches found matching criteria.</div>
    <?php endif; ?>

    <div class="pswp-gallery">
        <?php foreach($sketches as $sketch): 
            $score = (float)$sketch['overall_quality'];
            $scoreClass = $score >= 8.0 ? 'score-high' : ($score >= 5.0 ? 'score-mid' : ($score > 0 ? 'score-low' : 'score-zero'));
            
            // Reconstruct FULL Curation Object for Modal
            $srRaw = $sketch['showrunner_analysis'] ?? '{}';
            $srData = json_decode($srRaw, true);
            
            $curation = [
                'id' => $sketch['sketch_id'],
                'name' => $sketch['name'],
                'description' => $sketch['description'],
                'created_at' => $sketch['created_at'],
                'score' => $score,
                'class' => json_decode($sketch['classification'], true),
                'score_breakdown' => json_decode($sketch['scoring'], true),
                'entities' => json_decode($sketch['entities'], true),
                'themes' => json_decode($sketch['thematics'], true),
                'recs' => json_decode($sketch['recommendations'], true),
                'show' => $srData
            ];
            
            // Get Frames for this Sketch
            $frames = getFramesForSketch($pdo, $sketch['sketch_id']);
        ?>

        <div class="map-run-section">
            <div class="map-run-header">
                <span class="score-badge <?= $scoreClass ?>"><?= $score ?></span>
                <div style="margin-left: 10px;">
                    <div class="map-run-title"><?= htmlspecialchars($sketch['name']) ?></div>
                    <div class="map-run-meta">
                        #<?= $sketch['sketch_id'] ?> &bull; <?= date('M d H:i', strtotime($sketch['created_at'])) ?>
                        &bull; <?= count($frames) ?> Frames
                    </div>
                </div>
                
                <div style="margin-left: auto;">
                     <button class="btn-sm" onclick="reAnalyze(<?= $sketch['sketch_id'] ?>, this)">🔄 Re-Score</button>
                </div>
            </div>

            <?php if(!empty($frames)): ?>
            <div class="swiper frame-chain-swiper">
                <div class="swiper-wrapper">
                    <?php foreach ($frames as $frame):
                        $img = ltrim($frame['filename'], '/');
                        $frameId = $frame['id'];
                        
                        // Badges Logic
                        $badgeHtml = '<span class="badge badge-gray">Original</span>';
                        $editTool = $frame['edit_tool'] ?? null;
                        $img2img = $frame['img2img_frame_id'] ?? null;

                        if ($editTool) {
                            $badgeHtml = '<span class="badge badge-orange">Edit</span>';
                        } elseif ($img2img) {
                            $badgeHtml = '<span class="badge badge-blue">Img2Img</span>';
                        }

                        // Gear Menu Attributes
                        $gearAttr = 'data-gear-menu data-entity="frames" data-entity-id="'.$frameId.'" data-frame-id="'.$frameId.'" data-img-url="'.$img.'"';
                    ?>
                    <div class="swiper-slide">
                        <div class="chain-card" <?= $gearAttr ?>>
                            <div class="chain-card-thumbnail">
                                <a href="<?= htmlspecialchars($img) ?>" class="pswp-gallery-item" data-pswp-width="1024" data-pswp-height="1024" target="_blank">
                                    <img src="<?= htmlspecialchars($img) ?>" loading="lazy">
                                </a>
                            </div>
                            <div class="chain-card-body">
                                <h3 class="chain-card-title">Frame #<?= $frameId ?></h3>
                                <div style="margin-bottom:8px;">
                                    <?= $badgeHtml ?>
                                    <span class="badge badge-curator curation-pill-trigger"
                                          data-curation='<?= htmlspecialchars(json_encode($curation), ENT_QUOTES) ?>'
                                          title="Quality Score: <?= $score ?>">
                                        🕵️ Analysis
                                    </span>
                                </div>
                                <p class="chain-card-prompt full-desc-trigger" data-full-desc="<?= htmlspecialchars($sketch['description']) ?>">
                                    <?= htmlspecialchars(substr($sketch['description'], 0, 60)) ?>...
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="swiper-button-next"></div>
                <div class="swiper-button-prev"></div>
                <div class="swiper-scrollbar"></div>
            </div>
            <?php else: ?>
                <div style="padding: 20px; color: var(--text-muted); font-style: italic;">No frames generated for this sketch yet.</div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Pagination -->
<div class="pg-bar">
    <?php
        $qParams = "min_score=$minScore&theme=".urlencode($filterTheme)."&char=".urlencode($filterChar)."&func=".urlencode($filterFunc)."&sort=$sort";
    ?>
    <?php if($page > 1): ?>
        <a href="?page=<?= $page-1 ?>&<?= $qParams ?>" class="pg-btn">Previous</a>
    <?php endif; ?>
    <span>Page <?= $page ?> of <?= $totalPages ?></span>
    <?php if($page < $totalPages): ?>
        <a href="?page=<?= $page+1 ?>&<?= $qParams ?>" class="pg-btn">Next</a>
    <?php endif; ?>
</div>

<!-- Modals -->
<div id="curation-modal" class="modal-overlay">
    <div class="modal-content" style="max-width:700px;">
        <span class="modal-close" onclick="$('#curation-modal').hide()">&times;</span>
        <h3 class="modal-title" style="margin-top:0; border-bottom:1px solid var(--border); padding-bottom:10px;">Sketch Analysis</h3>
        <div id="curation-modal-body"></div>
    </div>
</div>

<div id="desc-modal" class="modal-overlay">
    <div class="modal-content">
        <span class="modal-close" onclick="$('#desc-modal').hide()">&times;</span>
        <h3 class="modal-title" style="margin-top:0;">Description</h3>
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
            slidesPerView: 'auto', spaceBetween: 40, freeMode: true,
            navigation: { nextEl: el.querySelector('.swiper-button-next'), prevEl: el.querySelector('.swiper-button-prev') },
            scrollbar: { el: el.querySelector('.swiper-scrollbar'), hide: true },
            slidesOffsetBefore: 20, slidesOffsetAfter: 20
        });
    });
    
    if(window.initLightbox) window.initLightbox();

    // Re-Score
    window.reAnalyze = function(id, btn) {
        const origText = btn.innerText;
        btn.innerText = '⏳ Thinking...';
        btn.style.opacity = '0.7';
        
        const formData = new FormData();
        formData.append('action', 'reanalyze');
        formData.append('id', id);

        fetch('curator_api.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(res => {
            if(res.ok) {
                Toast.show('Re-analysis complete! Score: ' + res.score, 'success');
                setTimeout(() => window.location.reload(), 1000); 
            } else {
                Toast.show('Error: ' + res.error, 'error');
                btn.innerText = origText;
            }
        })
        .catch(e => {
            Toast.show('Network Error', 'error');
            btn.innerText = origText;
        });
    };
    
    // Modal Interactions
    $(document).on('click', '.curation-pill-trigger', function(e) {
        e.stopPropagation();
        const raw = this.dataset.curation;
        if (!raw) return;
        const data = JSON.parse(raw);
        const body = document.getElementById('curation-modal-body');
        
        // --- 1. Header (Name, Score, ID) ---
        let html = `
            <div style="margin-bottom:15px; border-bottom:1px solid var(--border); padding-bottom:15px;">
                <div style="display:flex; justify-content:space-between; align-items:start;">
                    <div>
                        <h2 style="margin:0; font-size:1.4em;">${data.name}</h2>
                        <div style="font-size:0.85em; color:var(--text-muted); margin-top:4px;">
                            #${data.id} &bull; ${new Date(data.created_at).toLocaleString()}
                        </div>
                    </div>
                    <div class="score-badge score-high" style="padding:6px 12px; background:#10b981; color:white; border-radius:8px; font-weight:800; font-size:1.4em;">${data.score}</div>
                </div>
            </div>
        `;

        // --- 2. Description ---
        if (data.description) {
            html += `
                <div style="margin-bottom:15px; background:rgba(0,0,0,0.02); padding:10px; border-radius:6px; font-style:italic; line-height:1.5; color:var(--text);">
                    "${data.description}"
                </div>
            `;
        }

        // --- 3. Classification ---
        if(data.class) {
            html += `<div class="modal-row">`;
            html += `<span class="modal-label" style="width:100px; flex-shrink:0;">Class</span>`;
            html += `<div>`;
            if(data.class.narrative_function) html += `<a href="?func=${encodeURIComponent(data.class.narrative_function)}" class="pill pill-func">${data.class.narrative_function}</a> `;
            if(data.class.emotional_tone) html += `<span class="pill">${data.class.emotional_tone}</span>`;
            html += `</div></div>`;
        }

        // --- 4. Themes ---
        if (data.themes && data.themes.primary_themes) {
            html += `<div class="modal-row">
                        <span class="modal-label" style="width:100px; flex-shrink:0;">Themes</span>
                        <div style="flex:1;">`;
            let themes = Array.isArray(data.themes.primary_themes) ? data.themes.primary_themes : [data.themes.primary_themes];
            themes.forEach(t => html += `<a href="?theme=${encodeURIComponent(t)}" class="pill pill-theme">${t}</a> `);
            html += `</div></div>`;
        }

        // --- 5. Characters ---
        if (data.entities && data.entities.characters && data.entities.characters.length > 0) {
            html += `<div class="modal-row">
                        <span class="modal-label" style="width:100px; flex-shrink:0;">Characters</span>
                        <div style="flex:1;">`;
            data.entities.characters.forEach(c => html += `<a href="?char=${encodeURIComponent(c)}" class="pill pill-char">👤 ${c}</a> `);
            html += `</div></div>`;
        }

        // --- 6. Recommendations ---
        if(data.recs && data.recs.potential_use) {
             html += `<div style="margin:15px 0; background:rgba(245,159,11,0.1); padding:12px; border-radius:6px; border:1px dashed rgba(245,159,11,0.4);">
                        <span class="modal-label" style="color:#d97706; margin-bottom:5px;">💡 Potential Use</span>
                        <div style="font-size:0.95em;">${data.recs.potential_use}</div>
                      </div>`;
        }
        
        // --- 7. Deep Analysis ---
        let deepAnalysis = '';
        if(data.show && data.show.narrative_engines) {
            deepAnalysis += `<div style="margin-bottom:15px;"><span class="modal-label" style="color:#8b5cf6;">Narrative Engines</span>`;
            data.show.narrative_engines.forEach(ne => {
                 deepAnalysis += `<div style="background:rgba(139,92,246,0.1); padding:8px; border-radius:4px; margin-bottom:5px; border-left:3px solid #8b5cf6;">
                            <div style="font-weight:700; font-size:0.9em;">${ne.focus || 'Conflict'}</div>
                            <div style="font-size:0.85em; font-style:italic;">${ne.stakes || ''}</div>
                          </div>`;
            });
            deepAnalysis += `</div>`;
        }
        if(data.show && data.show.episode_concepts) {
            deepAnalysis += `<div style="margin-bottom:15px;"><span class="modal-label">Episode Concepts</span>`;
            data.show.episode_concepts.forEach(ep => {
                 deepAnalysis += `<div style="background:rgba(0,0,0,0.03); padding:8px; border-radius:4px; margin-bottom:5px; border-left:3px solid #f59e0b;">
                            <div style="font-weight:700;">📺 ${ep.title || 'Concept'}</div>
                            <div style="font-size:0.85em;">${ep.logline || ''}</div>
                          </div>`;
            });
            deepAnalysis += `</div>`;
        }
        if(deepAnalysis) {
            html += `<h4 style="margin:15px 0 10px 0; border-top:1px solid var(--border); padding-top:10px;">Showrunner Analysis</h4>` + deepAnalysis;
        }

        body.innerHTML = html;
        $('#curation-modal').css('display', 'flex');
    });

    $(document).on('click', '.full-desc-trigger', function(e) {
        e.stopPropagation(); $('#desc-modal-body').text(this.dataset.fullDesc); $('#desc-modal').css('display', 'flex');
    });
    
    window.addEventListener('click', function(e) { if (e.target.classList.contains('modal-overlay')) $(e.target).hide(); });

    // Collapsible filter bar logic
    (function() {
        const STORAGE_KEY = 'spw.curator.filter.collapsed';
        const $filterBar = $('.filter-bar');
        const $toggle = $('#filter-toggle');
        const $toggleIcon = $('#filter-toggle-icon');
        const $summary = $filterBar.find('.filter-summary');
        const $form = $filterBar.find('form.filter-form');

        if (!$filterBar.length || !$toggle.length) return;

        function countActiveFilters() {
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
            $summary.text(active === 0 ? 'No filters' : (active + ' active filters'));
        }

        function setCollapsed(collapsed, save = true) {
            if (collapsed) {
                $filterBar.addClass('collapsed');
                $toggle.attr('aria-pressed', 'true');
                $toggleIcon.text('▸');
            } else {
                $filterBar.removeClass('collapsed');
                $toggle.attr('aria-pressed', 'false');
                $toggleIcon.text('▾');
            }
            updateSummaryText();
            if (save) {
                try { localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0'); } catch (e) {}
            }
        }

        $toggle.on('click', function() {
            setCollapsed(!$filterBar.hasClass('collapsed'));
        });

        try {
            const saved = localStorage.getItem(STORAGE_KEY);
            setCollapsed(saved === '1', false);
        } catch (e) {
            setCollapsed(false, false);
        }

        $form.find('input').on('input change', function() {
            if ($filterBar.hasClass('collapsed')) updateSummaryText();
        });
        updateSummaryText();
    })();
});
</script>
<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle, $spw->getProjectPath() . '/templates/curation.php');
?>