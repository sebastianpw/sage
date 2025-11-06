<?php
require "e.php";
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$entity = "artifacts";

require "entity_icons.php";

/*
$entityIcons = [
    'characters'      => '👤',
    'character_poses' => '🤸',
    'animas'          => '🐾',
    'locations'       => '🗺️',
    'backgrounds'     => '🏞️',
    'artifacts'       => '🏺',
    'vehicles'        => '🛸',
    'scene_parts'     => '🎬',
    'controlnet_maps' => '☠️', 
    'spawns'          => '🌱',
    'generatives'     => '⚡',
    'sketches'        => '🎨',
    'pastebin'        => '📋',
    'sage_todos'      => '🎫',
    'meta_entities'   => '📦'
];
 */

// if entity exists in map → icon only
// else → fallback icon + ucfirst(entity)
if (isset($entityIcons[$entity])) {
    $entityIcon = '<a href="gallery_' . $entity . '.php">' . $entityIcons[$entity] . '</a>';
} else {
    $entityIcon = '📦 ' . ucfirst(str_replace('_', ' ', $entity));
}

$pdo = $spw->getPDO();
$dbname = $spw->getDbName();

// --- HANDLE SORT PARAMETERS ---
$sort = $_GET['sort'] ?? 'id';
$order = $_GET['order'] ?? 'DESC';
$initialSearch = $_GET['search'] ?? '';

$allowedSorts = $pdo->query("SHOW COLUMNS FROM `$entity`")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($sort, $allowedSorts)) $sort = 'id';
if (!in_array(strtoupper($order), ['ASC','DESC'])) $order = 'DESC';

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

        $countSql = "SELECT COUNT(*) FROM `$entity`";
        $params = [];
        if ($search !== '') {
            $countSql .= " WHERE id = :id OR name LIKE :search";
            $params['id'] = (int)$search;
            $params['search'] = "%$search%";
        }
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalRows = $countStmt->fetchColumn();
        $totalPages = ceil($totalRows / $limit);


        // MODIFIED SQL: Added LEFT JOIN for controlnet map image
        $sql = "SELECT e.*, 
                       f_i2i.filename AS img2img_filename,
                       f_cnmap.filename AS cnmap_filename
                FROM `$entity` e
                LEFT JOIN frames f_i2i ON f_i2i.id = e.img2img_frame_id
                LEFT JOIN frames f_cnmap ON f_cnmap.id = e.cnmap_frame_id";
        if ($search !== '') $sql .= " WHERE e.id = :id OR e.name LIKE :search";
        $sql .= " ORDER BY `order` ASC, e.id ASC LIMIT :limit OFFSET :offset";


        $stmt = $pdo->prepare($sql);
        if ($search !== '') {
            $stmt->bindValue(':id', (int)$search, PDO::PARAM_INT);
            $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'rows' => $rows,
            'totalPages' => $totalPages,
            'currentPage' => $page
        ]);
        exit;
    }

    if ($action == 'update') {
        $id = (int)$_POST['id'];
        $field = $_POST['field'];
        $value = $_POST['value'];

        $stmt = $pdo->query("SHOW COLUMNS FROM `$entity`");
        $allowed = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array($field, $allowed)) exit('Invalid field');

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
        $uniqueName = "New $entity " . time(); // guarantees uniqueness
        $stmt = $pdo->prepare("INSERT INTO `$entity` (name, `order`) VALUES (:name, 0)");
        $stmt->execute(['name'=>$uniqueName]);
        echo $pdo->lastInsertId();
        exit;
    }

    if ($action == 'copy') {

        $id = (int)$_POST['id'];
        $columns = $pdo->query("SHOW COLUMNS FROM `$entity`")->fetchAll(PDO::FETCH_COLUMN);
        $columns = array_filter($columns, fn($c)=> $c !== 'id');
        //        $colsList = implode(", ", $columns);
        $colsList = implode(", ", array_map(fn($c) => "`$c`", $columns));
        $placeholders = implode(", ", array_map(fn($c)=> ":$c", $columns));

        $stmt = $pdo->prepare("SELECT $colsList FROM `$entity` WHERE id = :id");
        $stmt->execute(['id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row) exit('Row not found');

        //if(isset($row['name'])) $row['name'] = 'Copy of ' . $row['name'];
        if(isset($row['name'])) $row['name'] = 'Copy of ' . $row['name'] . ' ' . time();

        $insertStmt = $pdo->prepare("INSERT INTO `$entity` ($colsList) VALUES ($placeholders)");
        $insertStmt->execute($row);

        echo $pdo->lastInsertId();
        exit;
    }

    if ($action == 'regenerate') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE `$entity` SET regenerate_images = 1 WHERE id = :id");
        $stmt->execute(['id'=>$id]);
        exit('success');
    }

    if ($action == 'reorder') {
        $orderData = $_POST['order'] ?? [];
        if (!is_array($orderData)) exit('Invalid data');

        $stmt = $pdo->prepare("UPDATE `$entity` SET `order` = :order WHERE id = :id");
        foreach ($orderData as $item) {
            $id = (int)$item['id'];
            $ord = (int)$item['order'];
            $stmt->execute(['order'=>$ord, 'id'=>$id]);
        }
        exit('success');
    }
    
    // UPDATED ACTION: Remove an image reference from an entity comprehensively
    if ($action == 'remove_image') {
        $id = (int)$_POST['id'];
        $image_type = $_POST['image_type']; // 'img2img' or 'cnmap'

        $allowed_types = ['img2img', 'cnmap'];
        if (!in_array($image_type, $allowed_types)) {
            exit('Invalid image type');
        }

        // Define all possible columns for each type based on provided structure
        $column_map = [
            'img2img' => [
                'img2img', 
                'img2img_frame_id', 
                'img2img_frame_filename', 
                'img2img_prompt'
            ],
            'cnmap' => [
                'cnmap', 
                'cnmap_frame_id', 
                'cnmap_frame_filename', 
                'cnmap_prompt'
            ]
        ];

        $columns_to_clear = $column_map[$image_type];

        // Get the actual columns that exist in the current entity's table
        $table_columns = $pdo->query("SHOW COLUMNS FROM `$entity`")->fetchAll(PDO::FETCH_COLUMN);
        
        // Find the intersection: which of our target columns actually exist?
        $valid_columns_to_update = array_intersect($columns_to_clear, $table_columns);
        
        if (empty($valid_columns_to_update)) {
            exit('No relevant columns found to update.');
        }
        
        $set_clauses = [];
        foreach ($valid_columns_to_update as $col) {
            // The tinyint flags get set to 0, everything else gets set to NULL
            if ($col === 'img2img' || $col === 'cnmap') {
                $set_clauses[] = "`$col` = 0";
            } else {
                $set_clauses[] = "`$col` = NULL";
            }
        }

        $sql = "UPDATE `$entity` SET " . implode(', ', $set_clauses) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['id' => $id]);

        exit('success');
    }

    if($action === 'fetchMapRuns') {
        $entity_id = (int)$_POST['entity_id'];
        $stmt = $pdo->prepare("SELECT id, created_at, note, is_active
                           FROM v_map_runs_".$entity."
                           WHERE entity_id = :entity_id
                           ORDER BY id DESC");
        $stmt->execute(['entity_id'=>$entity_id]);
        $runs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($runs);
        exit;
    }

    if($action === 'setActiveMapRun') {
        $entity_id = (int)$_POST['entity_id'];
        $map_run_id = (int)$_POST['map_run_id'];
        $stmt = $pdo->prepare("UPDATE `$entity` SET active_map_run_id = :map_run_id WHERE id = :entity_id");
        $stmt->execute(['map_run_id'=>$map_run_id, 'entity_id'=>$entity_id]);
        exit('success');
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $entity; ?> CRUD</title>



<?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>






<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>


<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- Swiper via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<?php else: ?>
    <!-- Swiper via local copy -->
    <link rel="stylesheet" href="/vendor/swiper/swiper-bundle.min.css" />
    <script src="/vendor/swiper/swiper-bundle.min.js"></script>
<?php endif; ?>


<?php
    // uncomment eruda to activate clientside debugging
    echo $eruda;
?>

<style>
body { font-family: Arial, sans-serif; margin:20px; }
table { border-collapse: collapse; width:100%; margin-top:15px; }
th, td { border:1px solid #ccc; padding:8px; text-align:left; font-size:14px; }
th { background:#f0f0f0; cursor:pointer; }
td[contenteditable="true"] { background:#f9f9f9; }
button { padding:4px 8px; margin:2px; }
td[data-field="description"] {
    min-height: 50px;
    border-bottom: 1px solid #eee;
}
.pagination { margin-top:15px; }
.pagination button { margin-right:5px; padding:5px 10px; cursor:pointer; }
.pagination .active { font-weight:bold; }
.dragHandle { font-size:18px; color:#888; user-select:none; cursor:grab; }
.dragHandle:hover { color:#333; }
.dragHandle span { width:30px; height:30px; line-height:30px; font-size:16px; }

/* Swiper styling for full-page swipe */
.swiper { width:100%; }
.swiper-slide { padding:0; box-sizing:border-box; /* keep table scrollable inside */ }
.swiper-slide > .slide-inner { padding:15px; }

/* Keep original responsive/mobile collapse rules */
@media (max-width:600px) {
    table, thead, tbody, th, td, tr { display:block; }
    tr { margin-bottom:15px; border:1px solid #ddd; padding:10px; background:#eee; }
    td { border:none; padding:5px 0; position:relative; }
    td::before { content: attr(data-label) ": "; font-weight:bold; display:inline-block; width:120px; }
    th { display:none; }
}

/* Collapse styles for mobile card mode */
@media (max-width:600px) {
  tr.collapsed td:not(:first-child):not([data-label="Actions"]) {
    display: none;
  }

  tr td:first-child {
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  tr td:first-child .toggleRowBtn {
    margin-left: auto;
    font-size: 18px;
    cursor: pointer;
    background: none;
    border: none;
  }
}

.copyBtn, .deleteBtn, .regenBtn, .matrixBtn {
    font-size: 1.2em;
    height: 36px;
    width: 36px;
    padding: 5px;
    margin: 3px;

  background-color: #f9f9f9;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 1px 2px #c3c3c3; 

}

.copyBtn:active, .deleteBtn:active, .regenBtn:active, .matrixBtn:active {
    box-shadow: 0 1px #c3c3c3;
    padding-top: 4px;
    padding-left: 4px;
}

td[contenteditable="true"][data-field="description"][data-label="description"] {
  display: table-cell !important;
}

</style>
</head>
<body>

<div style="display:flex; align-items:center; margin-bottom:15px; gap:10px;">
    <a href="dashboard.php" title="Dashboard" style="text-decoration:none; font-size:24px;">&#x1F5C3;</a>

<h2 style="margin:0;"><?php echo $entityIcon; ?></h2>

    <button id="addBtn">Add New</button>
    <button id="toggleSortBtn">+ Drag</button>

    <!-- New buttons for reorder -->
    <button id="reorderAscBtn">Reo ASC</button>
    <button id="reorderDescBtn">Reo DESC</button>
</div>

<div style="margin-bottom:15px;">
    <input type="text" id="searchInput" placeholder="Search by id or name..." 
           value="<?php echo htmlspecialchars($initialSearch, ENT_QUOTES); ?>" 
           style="padding:5px; width:250px;">
    <button id="searchBtn">Search</button>
</div>

<!-- Swiper container: each slide is a whole page of the table -->
<div class="swiper" style="padding-bottom: 200px !important;" id="<?php echo $entity; ?>Swiper">
  <div class="swiper-wrapper">
    <!-- First slide — will contain the table once loaded -->
    <div class="swiper-slide" data-page="1">
      <div class="slide-inner pswp-gallery">
        <table id="<?php echo $entity; ?>Table">
            <thead>
                <tr>
                    <th>Drag</th>
                    <th>Actions</th>
                    <?php
                    $stmt = $pdo->query("SHOW COLUMNS FROM `$entity`");
                    $fields = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    foreach ($fields as $field) echo "<th>$field</th>";
                    ?>
                    
                </tr>
            </thead>
            <tbody></tbody>
        </table>
      </div>
    </div>
    <!-- other slides will be appended dynamically by JS -->
  </div>

  <!-- Swiper navigation & pagination (dots) -->
  <div class="swiper-button-prev"></div>
  <div class="swiper-button-next"></div>
  <div class="swiper-pagination"></div>
</div>

<div id="toast-container"></div>

<script>
const ENTITY = '<?php echo $entity; ?>';
let currentPage = 1;
let rowsPerPage = 10;
let sortableEnabled = false;
let sortableInstance = null;
let swiper = null;
let totalPages = 1;
let photoswipeLightbox = null;

// Initialize PhotoSwipe
function initPhotoSwipe() {
    if (photoswipeLightbox) {
        try { photoswipeLightbox.destroy(); } catch(e){}
    }
    
    photoswipeLightbox = new PhotoSwipeLightbox({
        gallery: '.pswp-gallery',
        children: 'a.pswp-gallery-item',
	pswpModule: PhotoSwipe,
	initialZoomLevel: 'fit',
	secondaryZoomLevel: 1,
        paddingFn: (viewportSize) => {
            return {
                //top: 30, bottom: 30, left: 70, right: 70
            };
        }
    });
    
    photoswipeLightbox.init();
}

// Utility: build rows HTML (keeps same structure as original)
function buildRowsHTML(rows) {
    let rowsHtml = '';
    rows.forEach(row => {




rowsHtml += `<tr data-id="${row.id}" class="${localStorage.getItem(ENTITY + 'row-'+row.id)==='expanded'?'':'collapsed'}">`;



        // toggle + drag handle
        rowsHtml += `<td class="dragHandle" data-label="Drag">
		${row['id']} &nbsp; <button class="toggleRowBtn">${localStorage.getItem(ENTITY + 'row-'+row.id)==='expanded'?'−':'+'}</button> &nbsp; 
                        ☰
                     </td>`;

        

        // Actions column
        rowsHtml += `<td data-label='Actions'>`;
        rowsHtml += `<button class="copyBtn">⎘</button>`;
        rowsHtml += `<button class="deleteBtn">🗑️</button>`;
	rowsHtml += `<button class="regenBtn">♾️</button>`;

	rowsHtml += `<button class="matrixBtn">✺</button>`;
        rowsHtml += `<select class="mapRunSelect" data-entity-id="${row.id}" style="margin-left:5px;">
                        <option>Loading...</option>
                     </select>`;

        // MODIFIED JS: Add previews for both img2img and cnmap
        let hasImg2Img = row['img2img_filename'];
        let hasCnMap = row['cnmap_filename'];

        if (hasImg2Img || hasCnMap) {
            rowsHtml += `<div style="display:flex; flex-wrap:wrap; gap:5px; margin-top:10px; justify-content:center;">`;
            
            if (hasImg2Img) {
                rowsHtml += `<a class="pswp-gallery-item" 
                               data-entity-id="${row.id}"
                               data-image-type="img2img"
                               data-pswp-src="${row['img2img_filename']}" 
                               data-pswp-width="768" 
                               data-pswp-height="768"
                               title="Img2Img Preview (Dbl-click to remove)"
                               href="${row['img2img_filename']}">
                                <img src="${row['img2img_filename']}" 
                                     style="width:70px;height:70px;object-fit:cover;border:2px solid #a55;border-radius:4px;" />
                            </a>`;
            }

            if (hasCnMap) {
                rowsHtml += `<a class="pswp-gallery-item" 
                               data-entity-id="${row.id}"
                               data-image-type="cnmap"
                               data-pswp-src="${row['cnmap_filename']}" 
                               data-pswp-width="768" 
                               data-pswp-height="768"
                               title="ControlNet Map Preview (Dbl-click to remove)"
                               href="${row['cnmap_filename']}">
                                <img src="${row['cnmap_filename']}" 
                                     style="width:70px;height:70px;object-fit:cover;border:2px solid #55a;border-radius:4px;" />
                            </a>`;
            }

            rowsHtml += `</div>`;
        }


        rowsHtml += `</td>`;
        
        
        <?php foreach ($fields as $field) {
            if($field!=='id') 
                echo "rowsHtml += `<td contenteditable='true' data-field='$field' data-label='$field'>\${row['$field'] ?? ''}</td>`;\n";
            else 
                echo "rowsHtml += `<td data-label='$field'>\${row['$field']}</td>`;\n";
        } ?>
        
        
        

        rowsHtml += `</tr>`;
    });
    return rowsHtml;
}

// Initialize slides: fetch page 1 to learn totalPages, create slides, init swiper, load page1
function initSlides() {
    const search = $('#searchInput').val();
    $.post('sql_crud_<?php echo $entity; ?>.php', { action: 'fetch', search: search, page: 1, limit: rowsPerPage }, function(data) {
        data = JSON.parse(data);
        totalPages = data.totalPages || 1;
        currentPage = data.currentPage || 1;

        // Fill first slide tbody
        const firstSlide = $('#<?php echo $entity; ?>Swiper .swiper-slide').first();
        const tbody = firstSlide.find('tbody');
        tbody.html(buildRowsHTML(data.rows));
        firstSlide.attr('data-loaded','1');

        // Re-init PhotoSwipe for first slide
        initPhotoSwipe();

        // populate mapRunSelect for first slide
        firstSlide.find('.mapRunSelect').each(function() {
            const select = $(this);
            const entityId = select.data('entity-id');
            $.post('sql_crud_<?php echo $entity; ?>.php', { action: 'fetchMapRuns', entity_id: entityId }, function(runData){
                runData = JSON.parse(runData);
                select.empty();
                runData.forEach(run => {
                    select.append(`<option value="${run.id}" ${run.is_active ? 'selected' : ''}>${run.id} - ${run.note ? run.note : run.created_at}</option>`);
                });
            });
        });

        // build remaining slides (if any)
        const wrapper = $('#<?php echo $entity; ?>Swiper .swiper-wrapper');
        // remove existing extra slides except first
        wrapper.find('.swiper-slide').not(':first').remove();

        for (let p = 2; p <= totalPages; p++) {
            const theadHtml = $('#<?php echo $entity; ?>Table thead').prop('outerHTML');
            const slideHtml = `<div class="swiper-slide" data-page="${p}" data-loaded="0">
                                  <div class="slide-inner pswp-gallery">
                                    <table>
                                      ${theadHtml}
                                      <tbody></tbody>
                                    </table>
                                  </div>
                               </div>`;
            wrapper.append(slideHtml);
        }

        // Initialize or re-init swiper
        if (swiper) {
            swiper.update();
        } else {
            swiper = new Swiper('#<?php echo $entity; ?>Swiper', {
                direction: 'horizontal',
                slidesPerView: 1,
                spaceBetween: 0,
                pagination: { el: '.swiper-pagination', clickable: true },
                navigation: { nextEl: '.swiper-button-next', prevEl: '.swiper-button-prev' },
                grabCursor: true,
                on: {
                    slideChange: function() {
                        const pageNum = this.activeIndex + 1;
                        currentPage = pageNum;
                        loadPageIntoSlide(pageNum);
                    }
                }
            });
        }

        // ensure first slide is active (page 1)
        if (swiper) swiper.slideTo(currentPage - 1, 0, false);

        // enable sortable for first slide if needed
        if (sortableEnabled) enableSortable(firstSlide.find('tbody'));
    });
}

// Load a specific page into its corresponding slide (lazy)
function loadPageIntoSlide(page) {
    // find slide
    const slide = $('#<?php echo $entity; ?>Swiper .swiper-slide').filter(function(){ return parseInt($(this).attr('data-page')) === page; }).first();

    if (!slide.length) return; // no slide (out of range)

    if (slide.attr('data-loaded') === '1') {
        // already loaded
        return;
    }

    const search = $('#searchInput').val();
    $.post('sql_crud_<?php echo $entity; ?>.php', { action: 'fetch', search: search, page: page, limit: rowsPerPage }, function(data) {
        data = JSON.parse(data);
        totalPages = data.totalPages || totalPages;
        currentPage = data.currentPage || page;

        const tbody = slide.find('tbody');
        tbody.html(buildRowsHTML(data.rows));
        slide.attr('data-loaded','1');

        // re-init PhotoSwipe (it will pick up all galleries)
        initPhotoSwipe();

        // populate mapRunSelect for this slide
        slide.find('.mapRunSelect').each(function() {
            const select = $(this);
            const entityId = select.data('entity-id');
            $.post('sql_crud_<?php echo $entity; ?>.php', { action: 'fetchMapRuns', entity_id: entityId }, function(runData){
                runData = JSON.parse(runData);
                select.empty();
                runData.forEach(run => {
                    select.append(`<option value="${run.id}" ${run.is_active ? 'selected' : ''}>${run.id} - ${run.note ? run.note : run.created_at}</option>`);
                });
            });
        });

        // enable sortable for this slide if needed
        if (sortableEnabled) enableSortable(tbody);
    });
}

// Compatibility: loadTable(page) — used by original code in many places
// We'll use it to load the page into Swiper and navigate to it
function loadTable(sort='', order='', search='', page=1) {
    if (!swiper) {
        // if swiper not ready, init slides which will load page 1 and create swiper
        initSlides();
        return;
    }

    // ensure requested page exists in slides; if not, re-init
    const numericPage = parseInt(page) || 1;
    if (numericPage < 1) numericPage = 1;

    // If slide does not exist (maybe totalPages changed), re-init slides
    const slideExists = $('#<?php echo $entity; ?>Swiper .swiper-slide').filter(function(){ return parseInt($(this).attr('data-page')) === numericPage; }).length > 0;
    if (!slideExists) {
        initSlides();
        return;
    }





    const slide = $('#<?php echo $entity; ?>Swiper .swiper-slide').filter(function(){ 
        return parseInt($(this).attr('data-page')) === numericPage; 
    }).first();

    if (!slide.length) {
        initSlides();
        return;
    }

    // Force reload by resetting data-loaded
    slide.attr('data-loaded','0');





    // navigate to slide and load if needed
    swiper.slideTo(numericPage - 1);
    loadPageIntoSlide(numericPage);
}

// enable sortable for a specific tbody (or all if not provided)
function enableSortable(tbodySelector) {
    // If selector is omitted, target visible slide's tbody
    const target = tbodySelector ? $(tbodySelector) : $('#<?php echo $entity; ?>Swiper .swiper-slide').eq(swiper ? swiper.activeIndex : 0).find('tbody');

    // destroy any existing sortable on that element first
    try {
        target.sortable('destroy');
    } catch (e) {}

    target.sortable({
        handle: '.dragHandle',
        update: function(event, ui) {
            let orderData = [];
            $(this).find('tr').each(function(index){
                let id = $(this).data('id');
                orderData.push({id:id, order:index+1});
            });
            $.post('sql_crud_<?php echo $entity; ?>.php',{action:'reorder', order:orderData}, res=>{
                if(res==='success') Toast.show('Order saved','info');
                else Toast.show('Failed to save order','error');
            });
        }
    }).disableSelection();

    sortableEnabled = true;
}

// disable sortable (global)
function disableSortable() {
    try {
        $('#<?php echo $entity; ?>Swiper .swiper-slide').find('tbody').sortable('destroy');
    } catch (e) {}
    sortableEnabled = false;
}

// ajaxReorder kept as before
function ajaxReorder(direction) {
    $.post('/order_recalc.php?ajax=1', {
        entity: '<?php echo $entity; ?>',
        direction: direction,
        keepNonZero: 0
    }, function(data) {
        console.log('Parsed response:', data); // already an object

        if (data.success) {
            Toast.show(data.message, 'success');
            // reload current page
            loadTable('', '', $('#searchInput').val(), currentPage);
        } else {
            Toast.show(data.message, 'error');
        }
    }, 'json');
}

$('#reorderAscBtn').click(()=> ajaxReorder('ASC'));
$('#reorderDescBtn').click(()=> ajaxReorder('DESC'));

// document-level delegated handlers — these will work for dynamically loaded rows
$(document).ready(function(){

    // initialize slides (loads first page and sets up swiper)
    initSlides();

    // search handlers: rebuild slides for new search
    $('#searchBtn').click(()=> {
        initSlides();
    });
    $('#searchInput').on('keyup', e=> { if(e.key==='Enter') initSlides(); });
    
    // NEW EVENT HANDLER: Double-click to remove an image
    $(document).on('dblclick', 'a.pswp-gallery-item[data-image-type]', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Stop event from bubbling up, important for complex UIs

        const link = $(this);
        const entityId = link.data('entity-id');
        const imageType = link.data('image-type');
        const typeLabel = imageType === 'img2img' ? 'Img2Img preview' : 'ControlNet Map';

        if (confirm(`Do you really want to remove the ${typeLabel} for this entry?`)) {
            $.post('sql_crud_<?php echo $entity; ?>.php', {
                action: 'remove_image',
                id: entityId,
                image_type: imageType
            }, function(res) {
                if (res === 'success') {
                    Toast.show(`${typeLabel} removed.`, 'success');
                    // Reload the current slide to reflect the change
                    loadTable('', '', $('#searchInput').val(), currentPage);
                } else {
                    Toast.show(`Failed to remove ${typeLabel}.`, 'error');
                    console.error('Server response:', res);
                }
            });
        }
    });


    // inline editing
    $(document).on('blur','td[contenteditable="true"]', function(){
        let td = $(this), value = td.text(), field = td.data('field'), id = td.closest('tr').data('id');
        $.post('sql_crud_<?php echo $entity; ?>.php',{action:'update',id:id,field:field,value:value}, res=>{
            if(res!='success') Toast.show('Update failed for ID '+id,'error');
        });
    });

    // delete
    $(document).on('click','.deleteBtn',function(){
        if(!confirm('Are you sure?')) return;
        let id = $(this).closest('tr').data('id');
        $.post('sql_crud_<?php echo $entity; ?>.php',{action:'delete',id:id}, ()=>{
            // reinitialize slides to reflect removed row and possible page count change
            initSlides();
        });
    });

    // add new
    $(document).on('click','#addBtn',()=> {
        $.post('sql_crud_<?php echo $entity; ?>.php',{action:'add'}, ()=>{
            // Re-init slides — new row may affect page count
            initSlides();
        });
    });

    // copy
    $(document).on('click','.copyBtn',function(){
        if(!confirm('Are you sure you want to copy this entry?')) return;
        let id = $(this).closest('tr').data('id');
        $.post('sql_crud_<?php echo $entity; ?>.php',{action:'copy',id:id}, ()=>{
            // Re-init slides — copy may increase pages
            initSlides();
        });
    });

    // regenerate
    $(document).on('click','.regenBtn',function(){
        let id = $(this).closest('tr').data('id');
        $.post('sql_crud_<?php echo $entity; ?>.php',{action:'regenerate',id:id}, res=>{
            if(res=='success') {
                Toast.show('Regenerate triggered for ID '+id,'success');
                // reload current page to reflect changed regenerate flag
                loadTable('', '', $('#searchInput').val(), currentPage);
            } else {
                Toast.show('Failed to trigger regenerate for ID '+id,'error');
            }
        });
    });

    $(document).on('click','.matrixBtn',function(){
        let id = $(this).closest('tr').data('id');      
        window.location.href = 'view_prompt_matrix.php?entity_type=<?php echo $entity; ?>&entity_id='+id;
    });

    // toggle row collapse
    $(document).on('click', '.toggleRowBtn', function() {
        const tr = $(this).closest('tr');
        const id = tr.data('id');
        const btn = $(this);
        tr.toggleClass('collapsed');
        if (tr.hasClass('collapsed')) {
            btn.text('+');
            localStorage.setItem(ENTITY + 'row-'+id, 'collapsed');
        } else {
            btn.text('−');
            localStorage.setItem(ENTITY + 'row-'+id, 'expanded');
        }
    });

    // map run select change (delegated)
    $(document).on('change', '.mapRunSelect', function() {
        const select = $(this);
        const entityId = select.data('entity-id');
        const mapRunId = select.val();

        $.post('sql_crud_<?php echo $entity; ?>.php', {
            action: 'setActiveMapRun',
            entity_id: entityId,
            map_run_id: mapRunId
        }, function(res) {
            if(res === 'success') {
                Toast.show('Active map run updated','success');
                // optionally reload page to reflect change
                loadTable('', '', $('#searchInput').val(), currentPage);
            } else {
                Toast.show('Failed to update active map run','error');
            }
        });
    });

    // toggle sort button
    $('#toggleSortBtn').click(function(){
        if(sortableEnabled) {
            disableSortable();
            $(this).text('+ Drag');
            sortableEnabled=false;
        } else {
            enableSortable(); // enable on current visible tbody
            $(this).text('- Drag');
            sortableEnabled=true;
        }
    });

}); // end document ready

/*
$(window).on('beforeunload', function(e) {
    e.preventDefault();      // standard way
    e.returnValue = '';      // required for Chrome
    // Note: the returned value is ignored, the browser shows a default message
});
 */

</script>


<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- PhotoSwipe v5 CSS via CDN -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
<?php else: ?>
    <!-- PhotoSwipe CSS via local copy -->
    <link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
<?php endif; ?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- PhotoSwipe v5 JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.umd.js"></script>
<?php else: ?>
    <!-- PhotoSwipe JS via local copy -->
    <script src="/vendor/photoswipe/photoswipe.umd.js"></script>
    <script src="/vendor/photoswipe/photoswipe-lightbox.umd.js"></script>
<?php endif; ?>


<?php require "floatool.php"; ?>

</body>
</html>
