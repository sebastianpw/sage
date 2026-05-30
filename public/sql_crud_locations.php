<?php
// Template Placeholder: characters
// Generated via rollout_image_cruds.sh

require "e.php";
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require "entity_icons.php";

$entity = "locations";

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// --- DISCOVERY & CONFIGURATION ---
$stmt = $pdo->query("SHOW COLUMNS FROM `$entity`");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

$hasOrder = in_array('order', $columns);
$hasDescription = in_array('description', $columns);
$regenCol = in_array('regenerate_images', $columns) ? 'regenerate_images' : (in_array('regenerate', $columns) ? 'regenerate' : null);
$hasRegenerate = !empty($regenCol);
$hasMapRun = in_array('active_map_run_id', $columns);

// Check for Image Frame References
$hasImg2Img = in_array('img2img_frame_id', $columns);
$hasCnMap   = in_array('cnmap_frame_id', $columns);
$hasImages  = ($hasImg2Img || $hasCnMap);

// Icon Selection
$iconChar = $entityIcons[$entity] ?? '📦';

// Scheduler ID Lookup
$schedulerId = $entitySchedulerIds[$entity] ?? null;

// --- HANDLE AJAX REQUESTS ---
if (isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action == 'fetch') {
        $search = $_POST['search'] ?? '';
        $page   = (int)($_POST['page'] ?? 1);
        $limit  = (int)($_POST['limit'] ?? 10);
        if ($limit <= 0) $limit = 10;
        if ($page <= 0) $page = 1;
        $offset = ($page - 1) * $limit;

        // Base SELECT
        $selects = ["e.*"];
        $joins = [];

        if ($hasImg2Img) {
            $selects[] = "f_i2i.filename AS img2img_filename";
            $joins[] = "LEFT JOIN frames f_i2i ON f_i2i.id = e.img2img_frame_id";
        }
        if ($hasCnMap) {
            $selects[] = "f_cnmap.filename AS cnmap_filename";
            $joins[] = "LEFT JOIN frames f_cnmap ON f_cnmap.id = e.cnmap_frame_id";
        }

        $sqlSelect = "SELECT " . implode(', ', $selects) . " FROM `$entity` e " . implode(' ', $joins);
        $countSql  = "SELECT COUNT(*) FROM `$entity` e"; 

        // Filtering
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = "(e.id = :id OR e.name LIKE :search)";
            $params['id'] = (int)$search;
            $params['search'] = "%$search%";
        }

        if (!empty($where)) {
            $clause = " WHERE " . implode(' AND ', $where);
            $sqlSelect .= $clause;
            $countSql  .= $clause;
        }

        // Sorting
        if ($hasOrder) {
            $sqlSelect .= " ORDER BY e.`order` ASC, e.id DESC";
        } else {
            $sqlSelect .= " ORDER BY e.id DESC";
        }

        // Pagination Limits
        $sqlSelect .= " LIMIT :limit OFFSET :offset";

        // Execute Count
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRows = $countStmt->fetchColumn();
        $totalPages = ceil($totalRows / $limit);

        // Execute Data Fetch
        $stmt = $pdo->prepare($sqlSelect);
        foreach ($params as $k => $v) $stmt->bindValue(":$k", $v);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        echo json_encode([
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'totalPages' => $totalPages,
            'currentPage' => $page
        ]);
        exit;
    }

    if ($action == 'update') {
        $id = (int)$_POST['id'];
        $field = $_POST['field'];
        $value = $_POST['value'];
        if (!in_array($field, $columns)) exit('Invalid field');
        $stmt = $pdo->prepare("UPDATE `$entity` SET `$field` = :value WHERE id = :id");
        $stmt->execute(['value'=>$value, 'id'=>$id]);
        exit('success');
    }

    if ($action == 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM `$entity` WHERE id = :id");
        $stmt->execute(['id'=>$id]);
        exit('success');
    }

    if ($action == 'add') {
        $uniqueName = "New " . ucfirst($entity) . " " . time();
        $cols = ['name'];
        $vals = [':name'];
        $params = ['name' => $uniqueName];

        if ($hasOrder) {
            $cols[] = '`order`';
            $vals[] = '0';
        }

        $stmt = $pdo->prepare("INSERT INTO `$entity` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ")");
        $stmt->execute($params);
        echo $pdo->lastInsertId();
        exit;
    }

    if ($action == 'copy') {
        $id = (int)$_POST['id'];
        $colsList = implode(", ", array_map(fn($c) => "`$c`", array_filter($columns, fn($c)=> $c !== 'id')));
        $stmt = $pdo->prepare("SELECT $colsList FROM `$entity` WHERE id = :id");
        $stmt->execute(['id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($row) {
            if(isset($row['name'])) $row['name'] .= ' (Copy)';
            $placeholders = implode(", ", array_fill(0, count($row), '?'));
            $stmt = $pdo->prepare("INSERT INTO `$entity` ($colsList) VALUES ($placeholders)");
            $stmt->execute(array_values($row));
            echo $pdo->lastInsertId();
        }
        exit;
    }

    if ($action == 'regenerate' && $hasRegenerate) {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE `$entity` SET `$regenCol` = 1 WHERE id = :id");
        $stmt->execute(['id'=>$id]);
        exit('success');
    }

    if ($action == 'reorder' && $hasOrder) {
        $orderData = $_POST['order'] ?? [];
        $stmt = $pdo->prepare("UPDATE `$entity` SET `order` = :order WHERE id = :id");
        foreach ($orderData as $item) {
            $stmt->execute(['order'=>(int)$item['order'], 'id'=>(int)$item['id']]);
        }
        exit('success');
    }
    
    if ($action == 'remove_image') {
        $id = (int)$_POST['id'];
        $type = $_POST['image_type']; // 'img2img' or 'cnmap'
        $colName = ($type === 'img2img') ? 'img2img_frame_id' : 'cnmap_frame_id';
        if (in_array($colName, $columns)) {
            $stmt = $pdo->prepare("UPDATE `$entity` SET `$colName` = NULL WHERE id = :id");
            $stmt->execute(['id' => $id]);
            exit('success');
        }
        exit('error');
    }

    if($action === 'fetchMapRuns' && $hasMapRun) {
        try {
            $stmt = $pdo->prepare("SELECT id, created_at, note, is_active FROM v_map_runs_$entity WHERE entity_id = :eid ORDER BY id DESC LIMIT 20");
            $stmt->execute(['eid' => $_POST['entity_id']]);
        } catch (Exception $e) {
             $stmt = $pdo->prepare("SELECT id, created_at, 'No View' as note, 0 as is_active FROM map_runs WHERE entity = :ent ORDER BY id DESC LIMIT 20");
             $stmt->execute(['ent' => $entity]);
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if($action === 'setActiveMapRun' && $hasMapRun) {
        $stmt = $pdo->prepare("UPDATE `$entity` SET active_map_run_id = :rid WHERE id = :eid");
        $stmt->execute(['rid'=>(int)$_POST['map_run_id'], 'eid'=>(int)$_POST['entity_id']]);
        exit('success');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo ucfirst($entity); ?> Manager</title>

<script>
  (function() {
    try {
      var theme = localStorage.getItem('spw_theme');
      if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
      else if (theme === 'light') document.documentElement.setAttribute('data-theme', 'light');
    } catch (e) {}
  })();
</script>

<?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<!-- Swiper & PhotoSwipe -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.umd.js"></script>
<?php else: ?>
    <link rel="stylesheet" href="/vendor/swiper/swiper-bundle.min.css" />
    <script src="/vendor/swiper/swiper-bundle.min.js"></script>
    <link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
    <script src="/vendor/photoswipe/photoswipe.umd.js"></script>
    <script src="/vendor/photoswipe/photoswipe-lightbox.umd.js"></script>
<?php endif; ?>

<style>
/* Modern styling using base.css vars */
:root {
    --table-header-bg: rgba(var(--muted-border-rgb), 0.1);
    --table-stripe: rgba(var(--muted-border-rgb), 0.03);
}

/* 
   FIX: Constrain document width to prevent layout explosion when modals open.
   This ensures you can still zoom normally, but the page won't involuntarily
   expand to huge dimensions.
*/
html, body {
    width: 100%;
    max-width: 100%;
    overflow-x: hidden;
}

body {
    padding: 20px;
    background-color: var(--bg);
    color: var(--text);
    padding-bottom: 100px;
    position: relative;
    /* FIX: Ensure padding is included in width calculation */
    box-sizing: border-box; 
}

/* COMPACT HEADER */
.header-compact {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 10px;
    margin-left: 60px; /* Space for absolute dashboard button */
    height: 40px;
}

.search-line {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 20px;
    margin-left: 60px;
}

.entity-icon-link {
    font-size: 1.5rem;
    text-decoration: none;
    line-height: 1;
    transition: transform 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    display: block;
}
.entity-icon-link:hover { transform: scale(1.15); }

.header-controls {
    display: flex;
    align-items: center;
    gap: 6px;
}

.search-input {
    padding: 4px 8px;
    font-size: 0.85rem;
    border: 1px solid var(--border);
    border-radius: 4px;
    background: var(--card);
    color: var(--text);
    width: 200px;
    transition: width 0.2s;
}
.search-input:focus { outline: none; border-color: var(--accent); }

/* Table */
table { width: 100%; border-collapse: collapse; background: var(--card); border-radius: 8px; overflow: hidden; box-shadow: var(--card-elevation); }
th { background: var(--table-header-bg); color: var(--text-muted); font-weight: 600; text-align: left; padding: 12px; font-size: 0.85rem; text-transform: uppercase; }
td { padding: 10px 12px; border-bottom: 1px solid var(--border); vertical-align: middle; color: var(--text); }
tr:last-child td { border-bottom: none; }
tr:nth-child(even) { background-color: var(--table-stripe); }

td[contenteditable="true"] { background: rgba(var(--muted-border-rgb), 0.05); border-radius: 3px; min-width: 50px; }
td[contenteditable="true"]:focus { outline: 2px solid var(--accent); background: var(--bg); }

/* Placeholder for empty negative prompt */
td[data-field="prompt_negative"]:empty::before {
    content: "Negative prompt...";
    color: var(--text-muted);
    font-style: italic;
    font-size: 0.8em;
    opacity: 0.7;
}

/* Buttons */
.action-btn { 
    width: 28px; height: 28px; padding: 0; 
    display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid var(--border); background: var(--bg); color: var(--text-muted);
    border-radius: 4px; cursor: pointer; transition: all 0.2s;
}
.action-btn:hover { border-color: var(--accent); color: var(--accent); transform: translateY(-1px); }
.action-btn.delete:hover { border-color: var(--red); color: var(--red); }

/* Checkbox Style in Action Bar */
.action-checkbox-wrapper {
    width: 28px; height: 28px;
    display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid var(--border); background: var(--bg);
    border-radius: 4px;
}
.regen-checkbox { transform: scale(1.2); cursor: pointer; margin: 0; }

/* Images */
.thumb-container { display: flex; gap: 8px; flex-wrap: wrap; }
.thumb-link img { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; border: 1px solid var(--border); transition: transform 0.2s; }
.thumb-link img:hover { transform: scale(1.1); z-index: 10; border-color: var(--accent); }
.thumb-i2i img { border-color: #a55; }
.thumb-cn img { border-color: #55a; }





/* Swiper Fixes */
.swiper { 
    width: 100%; 
    overflow: visible; 
    padding-bottom: 0 !important; 
}
.swiper-slide { height: auto; opacity: 0; transition: opacity 0.3s; }
.swiper-slide-active { opacity: 1; }

.swiper-pagination {
    position: static !important;
    margin-top: 20px;
    display: flex !important;
    flex-wrap: wrap !important;
    justify-content: center !important;
    gap: 4px;
    padding: 10px;
    background: var(--card);
    border-radius: 8px;
    width: 100%;
    box-sizing: border-box;
}
.swiper-pagination-bullet {
    margin: 0 5px !important;
    width: 10px; 
    height: 10px;
    background: var(--text-muted);
    opacity: 0.3;
    display: inline-block;
}
.swiper-pagination-bullet-active {
    background: var(--accent);
    opacity: 1;
}


/* 
   PhotoSwipe Viewport Fix
   FIX: Use 100% instead of 100vw/vh to prevent calculation errors on tablets 
   where the scrollbar can cause width checks to fail and expand the page.
*/
.pswp {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    z-index: 2147483647 !important; /* Max Integer */
    margin: 0 !important;
    padding: 0 !important;
    transform: none !important;
    display: none;
    opacity: 0;
}
.pswp.pswp--open { 
    display: block; 
    opacity: 1; 
}

/* Draggable */
.dragHandle { cursor: grab; color: var(--text-muted); font-size: 1.2rem; margin-right: 8px; }

/* Responsive / Mobile Card View */
@media (max-width: 768px) {
    .header-compact { flex-wrap: wrap; height: auto; margin-left: 50px; }
    .search-line { margin-left: 0; padding: 0 10px; }
    .search-input { width: 100%; }

    table, thead, tbody, th, td, tr { display: block; }
    thead { display: none; }
    
    tr { margin-bottom: 15px; border: 1px solid var(--border); border-radius: 8px; background: var(--card); padding: 10px; box-shadow: var(--card-elevation); }
    
    td { display: flex; justify-content: space-between; align-items: center; border: none; padding: 8px 0; border-bottom: 1px solid rgba(var(--muted-border-rgb), 0.1); }
    td:last-child { border-bottom: none; }
    
    td::before { content: attr(data-label); font-weight: 600; color: var(--text-muted); font-size: 0.8rem; margin-right: 15px; flex-shrink: 0; }
    
    /* First cell (Header of card) */
    tr td:first-child { display: flex; width: 100%; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 8px; margin-bottom: 8px; }
    
    /* Mobile Toggle Logic - HIDE UNLESS FORCE VISIBLE */
    tr.collapsed td:not(:first-child):not(.force-visible):not(.action-cell) { display: none; }
    
    /* Ensure force-visible fields are block displayed for better reading */
    td.force-visible { display: block !important; }
    td.force-visible::before { display: block; margin-bottom: 5px; }
    
    .toggleRowBtn { background: none; border: 1px solid var(--border); border-radius: 4px; color: var(--accent); font-weight: bold; width: 30px; height: 30px; cursor: pointer; }
    
    /* Force blocks for complex content in always-open view */
    td[data-label="Description"], td[data-label="Prompt negative"], td[data-label="Images"] { display: block !important; }
    td[data-label="Description"]::before, td[data-label="Prompt negative"]::before, td[data-label="Images"]::before { display: block; margin-bottom: 5px; }
    
    /* Action cell specific */
    td[data-label="Actions"] { justify-content: flex-end; padding-top: 5px; }
    td[data-label="Actions"]::before { display: none; }
}

.mapRunSelect { max-width: 150px; padding: 2px; font-size: 0.8rem; border: 1px solid var(--border); background: var(--bg); color: var(--text); border-radius: 4px; }
</style>
</head>
<body>

<?php require __DIR__ . '/modal_frame_details.php'; ?>

<div class="header-compact">
    <!-- Icon linking to Gallery -->
    <?php if ($entity == "sketches"): ?>
    <a href="view_map_runs_<?php echo $entity; ?>.php" class="entity-icon-link" title="Gallery"><?php echo $iconChar; ?></a>
    <?php else: ?>
    <a href="gallery_<?php echo $entity; ?>_nu.php" class="entity-icon-link" title="Gallery"><?php echo $iconChar; ?></a>
    <?php endif; ?>
    
    <div class="header-controls">
        <button id="addBtn" class="btn btn-sm btn-outline-primary">Add</button>
        
        <!-- Scheduler Button (Dynamically Rendered) -->
        <?php if ($schedulerId): ?>
            <a class="runBtn scheduler" data-id="<?php echo $schedulerId; ?>" title="Trigger <?php echo ucfirst($entity); ?> Scheduler" style="cursor:pointer; font-size:1.2rem; text-decoration:none; margin-left:5px;">🌀</a>
        <?php endif; ?>

        <?php if($hasOrder): ?>
        <button id="toggleSortBtn" class="btn btn-sm btn-outline-secondary">Drag</button>
        <div class="btn-group" role="group" style="display:inline-flex;">
            <button id="reorderAscBtn" class="btn btn-sm btn-outline-secondary" title="Sort A-Z (ID)">▲</button>
            <button style="margin-left:6px;" id="reorderDescBtn" class="btn btn-sm btn-outline-secondary" title="Sort Z-A (ID)">▼</button>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="search-line">
    <input type="text" id="searchInput" class="search-input" placeholder="Search...">
    <button id="sendSearchBtn" class="btn btn-sm btn-outline-secondary" title="Search">Send</button>
    <button id="resetSearchBtn" class="btn btn-sm btn-outline-secondary" title="Reset">Reset</button>
</div>

<div class="swiper" id="mainSwiper">
  <div class="swiper-wrapper">
    <div class="swiper-slide" data-page="1">
      <div class="slide-inner pswp-gallery">
        <table id="dataTable">
            <thead>
                <tr>
                    <th width="30%">Name</th>
                    <th width="140">Actions</th>
                    <?php
                    // Only show Name (Col 1), Description, Prompt Negative. Regenerate is moved to actions.
                    $allowed = ['description', 'prompt_negative'];
                    $displayCols = array_intersect($columns, $allowed);
                    
                    foreach ($displayCols as $col) echo "<th>" . ucfirst(str_replace('_', ' ', $col)) . "</th>";
                    
                    

                    // Merged Images Header
                    if ($hasImages) echo "<th>Images</th>";
                    ?>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<div class="swiper-pagination"></div>


<script>
const ENTITY = '<?php echo $entity; ?>';
const HAS_ORDER = <?php echo $hasOrder ? 'true' : 'false'; ?>;
const REGEN_COL = '<?php echo $regenCol ?: ""; ?>';
const HAS_REGEN = <?php echo $hasRegenerate ? 'true' : 'false'; ?>;
const HAS_MAP = <?php echo $hasMapRun ? 'true' : 'false'; ?>;
const IS_COMPOSITE = ENTITY === 'composites';

let currentPage = 1;
let rowsPerPage = 10;
let sortableEnabled = false;
let swiper = null;
let lightbox = null;

function initPhotoSwipe() {
    if (lightbox) { try { lightbox.destroy(); } catch(e){} }
    lightbox = new PhotoSwipeLightbox({
        gallery: '.pswp-gallery', 
        children: 'a.pswp-gallery-item', 
        pswpModule: PhotoSwipe,
        appendToEl: document.body
    });
    lightbox.init();
}

function buildRowsHTML(rows) {
    let html = '';
    rows.forEach(row => {
        html += `<tr data-id="${row.id}">`;

        // 1. Name Cell
        html += `<td data-label="Name">
                    <div style="display:flex; align-items:center; width:100%;">
                        ${HAS_ORDER ? '<span class="dragHandle">☰</span>' : ''}
                        <span style="font-family:monospace; font-size:0.75em; color:var(--text-muted); margin-right:8px; min-width:30px;">#${row.id}</span>
                        <div contenteditable="true" data-field="name" style="flex:1; font-weight:600; padding:4px 0;">${row.name || ''}</div>
                    </div>
                 </td>`;

        // 2. Actions
        html += `<td data-label="Actions" class="action-cell"><div style="display:flex; gap:5px; flex-wrap:wrap; justify-content:flex-end;">`;
        
        // Regenerate Checkbox inside Actions
        if (HAS_REGEN) {
            let checked = (row[REGEN_COL] == 1) ? 'checked' : '';
            html += `<label class="action-checkbox-wrapper" title="Regenerate?">
                        <input type="checkbox" class="regen-checkbox" data-field="${REGEN_COL}" ${checked}>
                     </label>`;
        }

        html += `<button class="action-btn editBtn" title="Details">🕸️</button>`;
        if (IS_COMPOSITE) html += `<button class="action-btn puzzleBtn" title="Multiplane">🧩</button>`;
        html += `<button class="action-btn copyBtn" title="Copy">⎘</button>`;
        html += `<button class="action-btn delete" title="Delete">🗑</button>`;
        html += `<button class="action-btn matrixBtn" title="Matrix">✺</button>`;
        if (HAS_MAP) html += `<select class="mapRunSelect" data-entity-id="${row.id}"><option value="">Run...</option></select>`;
        html += `</div></td>`;

        // 3. Dynamic Columns (Description, Prompt Negative)
        <?php foreach ($displayCols as $col): ?>
            var val = row['<?php echo $col; ?>'] || '';
            var label = '<?php echo ucfirst(str_replace('_', ' ', $col)); ?>';
            html += `<td data-label="${label}" class="force-visible" contenteditable="true" data-field="<?php echo $col; ?>">${val}</td>`;
        <?php endforeach; ?>

        // 4. Merged Images
        <?php if ($hasImages): ?>
        html += `<td data-label="Images" class="force-visible"><div class="thumb-container">`;
        if (row.img2img_filename) {
            html += `<a class="pswp-gallery-item thumb-link thumb-i2i" href="${row.img2img_filename}" data-pswp-width="800" data-pswp-height="800" target="_blank" data-image-type="img2img" data-entity-id="${row.id}" title="Img2Img">
                        <img src="${row.img2img_filename}" loading="lazy" />
                     </a>`;
        }
        if (row.cnmap_filename) {
            html += `<a class="pswp-gallery-item thumb-link thumb-cn" href="${row.cnmap_filename}" data-pswp-width="800" data-pswp-height="800" target="_blank" data-image-type="cnmap" data-entity-id="${row.id}" title="ControlNet">
                        <img src="${row.cnmap_filename}" loading="lazy" />
                     </a>`;
        }
        html += `</div></td>`;
        <?php endif; ?>

        html += `</tr>`;
    });
    return html;
}

function initSlides() {
    const search = $('#searchInput').val();
    $.post('sql_crud_<?php echo $entity; ?>.php', { action: 'fetch', search: search, page: 1, limit: rowsPerPage }, function(data) {
        data = JSON.parse(data);
        const firstSlide = $('#mainSwiper .swiper-slide').first();
        firstSlide.find('tbody').html(buildRowsHTML(data.rows));
        if(HAS_MAP) loadMapRunsForSlide(firstSlide);
        
        $('#mainSwiper .swiper-wrapper .swiper-slide').not(':first').remove();
        const header = $('#dataTable thead').prop('outerHTML');
        for (let p = 2; p <= data.totalPages; p++) {
             $('#mainSwiper .swiper-wrapper').append(`<div class="swiper-slide" data-page="${p}" data-loaded="0"><div class="slide-inner pswp-gallery"><table>${header}<tbody></tbody></table></div></div>`);
        }
        
        if (swiper) swiper.destroy();
        swiper = new Swiper('#mainSwiper', {
            autoHeight: true,
            pagination: { 
                el: '.swiper-pagination', 
                clickable: true
            },
            on: { slideChange: function() { loadPageData(this.activeIndex + 1); } }
        });
        
        initPhotoSwipe();
        if (sortableEnabled) enableSortable(firstSlide.find('tbody'));
    });
}

function loadPageData(page) {
    const slide = $(`.swiper-slide[data-page="${page}"]`);
    if (slide.attr('data-loaded') === '1') return;
    const search = $('#searchInput').val();
    $.post('sql_crud_<?php echo $entity; ?>.php', { action: 'fetch', search: search, page: page, limit: rowsPerPage }, function(data) {
        data = JSON.parse(data);
        slide.find('tbody').html(buildRowsHTML(data.rows));
        slide.attr('data-loaded', '1');
        if(HAS_MAP) loadMapRunsForSlide(slide);
        swiper.updateAutoHeight();
        initPhotoSwipe();
    });
}

function loadMapRunsForSlide(slide) {
    slide.find('.mapRunSelect').each(function() {
        const sel = $(this);
        const id = sel.data('entity-id');
        $.post('sql_crud_<?php echo $entity; ?>.php', {action: 'fetchMapRuns', entity_id: id}, function(res){
            const runs = JSON.parse(res);
            sel.empty().append('<option value="">Run...</option>');
            runs.forEach(r => sel.append(`<option value="${r.id}" ${r.is_active?'selected':''}>${r.id}: ${r.note||r.created_at}</option>`));
        });
    });
}

function ajaxReorder(direction) {
    $.post('/order_recalc.php?ajax=1', { entity: ENTITY, direction: direction, keepNonZero: 0 }, function(data) {
        if (data.success) {
            Toast.show(data.message, 'success');
            initSlides();
        } else {
            Toast.show(data.message, 'error');
        }
    }, 'json');
}

$(document).ready(function() {
    initSlides();
    
    $('#searchInput').on('keyup', function(e) { if(e.key==='Enter') initSlides(); });
    $('#sendSearchBtn').click(function() { initSlides(); });
    $('#resetSearchBtn').click(function() { $('#searchInput').val(''); initSlides(); });
    $('#addBtn').click(function() { $.post('sql_crud_<?php echo $entity; ?>.php', {action:'add'}, function(){ initSlides(); Toast.show('Added','success'); }); });
    $('#reorderAscBtn').click(() => ajaxReorder('ASC'));
    $('#reorderDescBtn').click(() => ajaxReorder('DESC'));

    // Matrix Button Click
    $(document).on('click', '.matrixBtn', function() {
        const id = $(this).closest('tr').data('id');
        window.location.href = 'view_prompt_matrix.php?entity_type=' + ENTITY + '&entity_id=' + id;
    });

    // Checkbox Change (Regenerate)
    $(document).on('change', '.regen-checkbox', function() {
        const el = $(this);
        const id = el.closest('tr').data('id');
        const field = el.data('field');
        const val = el.is(':checked') ? 1 : 0;
        
        $.post('sql_crud_<?php echo $entity; ?>.php', { action: 'update', id: id, field: field, value: val }, function(res) {
            if(res === 'success') Toast.show('Updated', 'success');
            else { Toast.show('Error', 'error'); el.prop('checked', !val); }
        });
    });

    // Map Run Change Event
    $(document).on('change', '.mapRunSelect', function() {
        const sel = $(this);
        $.post('sql_crud_<?php echo $entity; ?>.php', { 
            action: 'setActiveMapRun', 
            entity_id: sel.data('entity-id'), 
            map_run_id: sel.val() 
        }, function(res) {
            if(res === 'success') Toast.show('Map Run Updated', 'success');
            else Toast.show('Update failed', 'error');
        });
    });

    // Inline Edit
    $(document).on('blur', '[contenteditable="true"]', function() {
        const el = $(this);
        const id = el.closest('tr').data('id');
        const field = el.data('field');
        const val = el.text();
        
        $.post('sql_crud_<?php echo $entity; ?>.php', { action: 'update', id: id, field: field, value: val }, function(res) {
            if(res!=='success') Toast.show('Error saving','error');
        });
    });

    $(document).on('click', '.copyBtn', function() { if(confirm('Copy?')) $.post('sql_crud_<?php echo $entity; ?>.php', {action:'copy', id:$(this).closest('tr').data('id')}, function(){ initSlides(); Toast.show('Copied','success'); }); });
    $(document).on('click', '.delete', function() { if(confirm('Delete?')) $.post('sql_crud_<?php echo $entity; ?>.php', {action:'delete', id:$(this).closest('tr').data('id')}, function(){ initSlides(); Toast.show('Deleted','success'); }); });
    $(document).on('click', '.editBtn', function() { if(window.showEntityFormInModal) window.showEntityFormInModal(ENTITY, $(this).closest('tr').data('id')); });
    if(IS_COMPOSITE) { $(document).on('click', '.puzzleBtn', function() { if(window.showMultiplaneInModal) window.showMultiplaneInModal($(this).closest('tr').data('id')); }); }
    
    $(document).on('dblclick', 'a.pswp-gallery-item', function(e) {
        e.preventDefault(); e.stopPropagation();
        const type = $(this).data('image-type');
        if(confirm('Remove this '+type+' image?')) {
            $.post('sql_crud_<?php echo $entity; ?>.php', {action:'remove_image', id:$(this).data('entity-id'), image_type:type}, function(res){ if(res==='success') { Toast.show('Removed','success'); initSlides(); } });
        }
    });

    $('#toggleSortBtn').click(function() {
        sortableEnabled = !sortableEnabled;
        if(sortableEnabled) { $(this).addClass('btn-accent'); enableSortable($('.swiper-slide-active tbody')); }
        else { $(this).removeClass('btn-accent'); $('.ui-sortable').sortable('destroy'); }
    });
});

function enableSortable(tbody) {
    if(tbody.hasClass('ui-sortable')) tbody.sortable('destroy');
    tbody.sortable({ handle: '.dragHandle', update: function() {
        let o=[]; $(this).find('tr').each(function(i){ o.push({id:$(this).data('id'), order:i}); });
        $.post('sql_crud_<?php echo $entity; ?>.php', {action:'reorder', order:o}, function(){ Toast.show('Saved','success'); });
    }});
}
</script>
<?php require_once "forge_tool.php"; ?>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
<script>
    $(document).ready(function() {
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
    });
</script>
</body>
</html>