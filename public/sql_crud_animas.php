<?php
require "e.php";
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$entity = "animas";

require "entity_icons.php";

if (isset($entityIcons[$entity])) {
    $entityIcon = '<a href="gallery_' . $entity . '_nu.php">' . $entityIcons[$entity] . '</a>';
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
        $colsList = implode(", ", array_map(fn($c) => "`$c`", $columns));
        $placeholders = implode(", ", array_map(fn($c)=> ":$c", $columns));

        $stmt = $pdo->prepare("SELECT $colsList FROM `$entity` WHERE id = :id");
        $stmt->execute(['id'=>$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if(!$row) exit('Row not found');

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

    if ($action == 'remove_image') {
        $id = (int)$_POST['id'];
        $image_type = $_POST['image_type']; // 'img2img' or 'cnmap'

        $allowed_types = ['img2img', 'cnmap'];
        if (!in_array($image_type, $allowed_types)) {
            exit('Invalid image type');
        }

        $column_map = [
            'img2img' => ['img2img', 'img2img_frame_id', 'img2img_frame_filename', 'img2img_prompt'],
            'cnmap' => ['cnmap', 'cnmap_frame_id', 'cnmap_frame_filename', 'cnmap_prompt']
        ];
        $columns_to_clear = $column_map[$image_type];
        $table_columns = $pdo->query("SHOW COLUMNS FROM `$entity`")->fetchAll(PDO::FETCH_COLUMN);
        $valid_columns_to_update = array_intersect($columns_to_clear, $table_columns);

        if (empty($valid_columns_to_update)) {
            exit('No relevant columns found to update.');
        }

        $set_clauses = [];
        foreach ($valid_columns_to_update as $col) {
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
        $stmt = $pdo->prepare("SELECT id, created_at, note, is_active FROM v_map_runs_".$entity." WHERE entity_id = :entity_id ORDER BY id DESC");
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

<!-- NEW: Added no-flash script and theme manager -->
<script>
  (function() {
    try {
      var theme = localStorage.getItem('spw_theme');
      if (theme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
      } else if (theme === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
      }
    } catch (e) {}
  })();
</script>
<script src="/js/theme-manager.js" defer></script>

<?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>

<link rel="stylesheet" href="/css/toast.css">
<script src="/js/toast.js"></script>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<?php else: ?>
    <link rel="stylesheet" href="/vendor/swiper/swiper-bundle.min.css" />
    <script src="/vendor/swiper/swiper-bundle.min.js"></script>
<?php endif; ?>

<?php echo $eruda; ?>

<!-- UPDATED: This entire style block is now theme-aware -->
<style>
:root {
    --float-bg: #ffffff;
    --float-border: #d1d5db;
    --float-text: #111827;
    --float-muted: #6b7280;
    --float-hover: #f3f4f6;
    --float-btn-bg: #f8f9fa;
    --float-shadow-inset: 1px 2px #c3c3c3;
}
html[data-theme="dark"] {
    --float-bg: #0f1724;
    --float-border: #374151; /* A bit lighter for dark mode borders */
    --float-text: #cbd5e1;
    --float-muted: #94a3b8;
    --float-hover: #1f2937;
    --float-btn-bg: #111827;
    --float-shadow-inset: 1px 2px rgba(0,0,0,0.5);
}
@media (prefers-color-scheme: dark) {
  :root:not([data-theme]) {
    --float-bg: #0f1724;
    --float-border: #374151;
    --float-text: #cbd5e1;
    --float-muted: #94a3b8;
    --float-hover: #1f2937;
    --float-btn-bg: #111827;
    --float-shadow-inset: 1px 2px rgba(0,0,0,0.5);
  }
}

body {
    font-family: Arial, sans-serif;
    margin: 20px;
    background-color: var(--float-bg);
    color: var(--float-text);
}
table { border-collapse: collapse; width:100%; margin-top:15px; }
th, td { border:1px solid var(--float-border); padding:8px; text-align:left; font-size:14px; }
th { background: var(--float-btn-bg); cursor:pointer; }
td[contenteditable="true"] { background: var(--float-hover); }
button, input, select {
    background-color: var(--float-btn-bg);
    color: var(--float-text);
    border: 1px solid var(--float-border);
    border-radius: 4px;
}
button { padding:4px 8px; margin:2px; cursor: pointer; }
input[type="text"] { padding: 5px; }
h2, h2 a { color: var(--float-text); text-decoration: none; }
td[data-field="description"] { min-height: 50px; border-bottom: 1px solid var(--float-border); }
.pagination button { margin-right:5px; padding:5px 10px; }
.pagination .active { font-weight:bold; }
.dragHandle { font-size:18px; color: var(--float-muted); user-select:none; cursor:grab; }
.dragHandle:hover { color: var(--float-text); }
.dragHandle span { width:30px; height:30px; line-height:30px; font-size:16px; }

.swiper { width:100%; }
.swiper-slide { padding:0; box-sizing:border-box; }
.swiper-slide > .slide-inner { padding:15px; }

@media (max-width:600px) {
    table, thead, tbody, th, td, tr { display:block; }
    tr { margin-bottom:15px; border:1px solid var(--float-border); padding:10px; background: var(--float-hover); }
    td { border:none; padding:5px 0; position:relative; }
    td::before { content: attr(data-label) ": "; font-weight:bold; display:inline-block; width:120px; }
    th { display:none; }
    tr.collapsed td:not(:first-child):not([data-label="Actions"]) { display: none; }
    tr td:first-child { display: flex; justify-content: space-between; align-items: center; }
    tr td:first-child .toggleRowBtn { margin-left: auto; font-size: 18px; cursor: pointer; background: none; border: none; color: var(--float-text); }
}

.copyBtn, .deleteBtn, .regenBtn, .matrixBtn, .editBtn {
    font-size: 1.2em;
    height: 26px;
    width: 26px;
    padding: 0px;
    margin: 3px;
    background-color: var(--float-btn-bg);
    border: 1px solid var(--float-border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    box-shadow: var(--float-shadow-inset);
}
.copyBtn:active, .deleteBtn:active, .regenBtn:active, .matrixBtn:activey .editBtn:active {
    box-shadow: 0 1px var(--float-shadow-inset);
    padding-top: 4px;
    padding-left: 4px;
}
td[contenteditable="true"][data-field="description"][data-label="description"] {
  display: table-cell !important;
}

</style>
</head>
<body>

<?php 
// --- ADDED: Include the modal HTML and JavaScript ---
require __DIR__ . '/modal_frame_details.php'; 
?>

<div style="display:flex; align-items:center; margin-bottom:15px; gap:10px;">
    <a href="dashboard.php" title="Dashboard" style="text-decoration:none; font-size:24px; display:none;">&#x1F5C3;</a>
    <h2 style="margin:0;margin-left:35px;"><?php echo $entityIcon; ?></h2>
    <button id="addBtn">Add New</button>
    <button id="toggleSortBtn">+ Drag</button>
    <button id="reorderAscBtn">Reo ASC</button>
    <button id="reorderDescBtn">Reo DESC</button>
</div>

<div style="margin-bottom:15px;">
    <input type="text" id="searchInput" placeholder="Search by id or name..."
           value="<?php echo htmlspecialchars($initialSearch, ENT_QUOTES); ?>"
           style="width:200px;">
    <button id="searchBtn">Search</button>
    <button id="resetBtn">Reset</button>
</div>

<div class="swiper" style="padding-bottom: 200px !important;" id="<?php echo $entity; ?>Swiper">
  <div class="swiper-wrapper">
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
  </div>
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

function initPhotoSwipe() {
    if (photoswipeLightbox) {
        try { photoswipeLightbox.destroy(); } catch(e){}
    }
    photoswipeLightbox = new PhotoSwipeLightbox({
        gallery: '.pswp-gallery',
        children: 'a.pswp-gallery-item',
        pswpModule: PhotoSwipe,
        initialZoomLevel: 'fit',
        secondaryZoomLevel: 1
    });
    photoswipeLightbox.init();
}

function buildRowsHTML(rows) {
    let rowsHtml = '';
    rows.forEach(row => {
        rowsHtml += `<tr data-id="${row.id}" class="${localStorage.getItem(ENTITY + 'row-'+row.id)==='expanded'?'':'collapsed'}">`;
        rowsHtml += `<td class="dragHandle" data-label="Drag">
                        ${row['id']} &nbsp; <button class="toggleRowBtn">${localStorage.getItem(ENTITY + 'row-'+row.id)==='expanded'?'−':'+'}</button> &nbsp;
                        ☰
                     </td>`;
                        rowsHtml += `<td data-label='Actions'>`;
        rowsHtml += `<button class="editBtn">🕸️</button>`;
        rowsHtml += `<button class="copyBtn">⎘</button>`;
        rowsHtml += `<button class="deleteBtn">🗑️</button>`;
        rowsHtml += `<button class="regenBtn">♾️</button>`;
        rowsHtml += `<button class="matrixBtn">✺</button>`;
        rowsHtml += `<select class="mapRunSelect" data-entity-id="${row.id}" style="margin-left:5px;"><option>Loading...</option></select>`;

        let hasImg2Img = row['img2img_filename'];
        let hasCnMap = row['cnmap_filename'];
        if (hasImg2Img || hasCnMap) {
            rowsHtml += `<div style="display:flex; flex-wrap:wrap; gap:5px; margin-top:10px; justify-content:center;">`;
            if (hasImg2Img) {
                rowsHtml += `<a class="pswp-gallery-item" data-entity-id="${row.id}" data-image-type="img2img" data-pswp-src="${row['img2img_filename']}" data-pswp-width="768" data-pswp-height="768" title="Img2Img Preview (Dbl-click to remove)" href="${row['img2img_filename']}"><img src="${row['img2img_filename']}" style="width:70px;height:70px;object-fit:cover;border:2px solid #a55;border-radius:4px;" /></a>`;
            }
            if (hasCnMap) {
                rowsHtml += `<a class="pswp-gallery-item" data-entity-id="${row.id}" data-image-type="cnmap" data-pswp-src="${row['cnmap_filename']}" data-pswp-width="768" data-pswp-height="768" title="ControlNet Map Preview (Dbl-click to remove)" href="${row['cnmap_filename']}"><img src="${row['cnmap_filename']}" style="width:70px;height:70px;object-fit:cover;border:2px solid #55a;border-radius:4px;" /></a>`;
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

function initSlides() {
    const search = $('#searchInput').val();
    $.post('sql_crud_<?php echo $entity; ?>.php', { action: 'fetch', search: search, page: 1, limit: rowsPerPage }, function(data) {
        data = JSON.parse(data);
        totalPages = data.totalPages || 1;
        currentPage = data.currentPage || 1;

        const firstSlide = $('#<?php echo $entity; ?>Swiper .swiper-slide').first();
        const tbody = firstSlide.find('tbody');
        tbody.html(buildRowsHTML(data.rows));
        firstSlide.attr('data-loaded','1');
        initPhotoSwipe();

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

        const wrapper = $('#<?php echo $entity; ?>Swiper .swiper-wrapper');
        wrapper.find('.swiper-slide').not(':first').remove();

        for (let p = 2; p <= totalPages; p++) {
            const theadHtml = $('#<?php echo $entity; ?>Table thead').prop('outerHTML');
            const slideHtml = `<div class="swiper-slide" data-page="${p}" data-loaded="0"><div class="slide-inner pswp-gallery"><table>${theadHtml}<tbody></tbody></table></div></div>`;
            wrapper.append(slideHtml);
        }

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
        if (swiper) swiper.slideTo(currentPage - 1, 0, false);
        if (sortableEnabled) enableSortable(firstSlide.find('tbody'));
    });
}

function loadPageIntoSlide(page) {
    const slide = $('#<?php echo $entity; ?>Swiper .swiper-slide').filter(function(){ return parseInt($(this).attr('data-page')) === page; }).first();
    if (!slide.length || slide.attr('data-loaded') === '1') return;

    const search = $('#searchInput').val();
    $.post('sql_crud_<?php echo $entity; ?>.php', { action: 'fetch', search: search, page: page, limit: rowsPerPage }, function(data) {
        data = JSON.parse(data);
        totalPages = data.totalPages || totalPages;
        currentPage = data.currentPage || page;
        const tbody = slide.find('tbody');
        tbody.html(buildRowsHTML(data.rows));
        slide.attr('data-loaded','1');
        initPhotoSwipe();

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
        if (sortableEnabled) enableSortable(tbody);
    });
}

function loadTable(sort='', order='', search='', page=1) {
    if (!swiper) { initSlides(); return; }
    const numericPage = parseInt(page) || 1;
    const slideExists = $('#<?php echo $entity; ?>Swiper .swiper-slide').filter(function(){ return parseInt($(this).attr('data-page')) === numericPage; }).length > 0;
    if (!slideExists) { initSlides(); return; }
    const slide = $('#<?php echo $entity; ?>Swiper .swiper-slide').filter(function(){ return parseInt($(this).attr('data-page')) === numericPage; }).first();
    if (!slide.length) { initSlides(); return; }
    slide.attr('data-loaded','0');
    swiper.slideTo(numericPage - 1);
    loadPageIntoSlide(numericPage);
}

function enableSortable(tbodySelector) {
    const target = tbodySelector ? $(tbodySelector) : $('#<?php echo $entity; ?>Swiper .swiper-slide').eq(swiper ? swiper.activeIndex : 0).find('tbody');
    try { target.sortable('destroy'); } catch (e) {}
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

function disableSortable() {
    try { $('#<?php echo $entity; ?>Swiper .swiper-slide').find('tbody').sortable('destroy'); } catch (e) {}
    sortableEnabled = false;
}

function ajaxReorder(direction) {
    $.post('/order_recalc.php?ajax=1', { entity: '<?php echo $entity; ?>', direction: direction, keepNonZero: 0 }, function(data) {
        if (data.success) {
            Toast.show(data.message, 'success');
            loadTable('', '', $('#searchInput').val(), currentPage);
        } else {
            Toast.show(data.message, 'error');
        }
    }, 'json');
}

$('#reorderAscBtn').click(()=> ajaxReorder('ASC'));
$('#reorderDescBtn').click(()=> ajaxReorder('DESC'));

$(document).ready(function(){
    initSlides();
    $('#searchBtn').click(()=> { initSlides(); });
    $('#searchInput').on('keyup', e=> { if(e.key==='Enter') initSlides(); });

// Reset button functionality
$('#resetBtn').click(() => {
    // Clear the search input
    $('#searchInput').val('');
    
    // Reinitialize slides
    initSlides();
});

    $(document).on('dblclick', 'a.pswp-gallery-item[data-image-type]', function(e) {
        e.preventDefault(); e.stopPropagation();
        const link = $(this);
        const entityId = link.data('entity-id');
        const imageType = link.data('image-type');
        const typeLabel = imageType === 'img2img' ? 'Img2Img preview' : 'ControlNet Map';
        if (confirm(`Do you really want to remove the ${typeLabel} for this entry?`)) {
            $.post('sql_crud_<?php echo $entity; ?>.php', { action: 'remove_image', id: entityId, image_type: imageType }, function(res) {
                if (res === 'success') {
                    Toast.show(`${typeLabel} removed.`, 'success');
                    loadTable('', '', $('#searchInput').val(), currentPage);
                } else {
                    Toast.show(`Failed to remove ${typeLabel}.`, 'error');
                    console.error('Server response:', res);
                }
            });
        }
    });

    $(document).on('blur','td[contenteditable="true"]', function(){
        let td = $(this), value = td.text(), field = td.data('field'), id = td.closest('tr').data('id');
        $.post('sql_crud_<?php echo $entity; ?>.php',{action:'update',id:id,field:field,value:value}, res=>{
            if(res!='success') Toast.show('Update failed for ID '+id,'error');
        });
    });

    $(document).on('click','.deleteBtn',function(){
        if(!confirm('Are you sure?')) return;
        let id = $(this).closest('tr').data('id');
        $.post('sql_crud_<?php echo $entity; ?>.php',{action:'delete',id:id}, ()=>{ initSlides(); });
    });

    $(document).on('click','#addBtn',()=> {
        $.post('sql_crud_<?php echo $entity; ?>.php',{action:'add'}, ()=>{ initSlides(); });
    });

    $(document).on('click','.copyBtn',function(){
        if(!confirm('Are you sure you want to copy this entry?')) return;
        let id = $(this).closest('tr').data('id');
        $.post('sql_crud_<?php echo $entity; ?>.php',{action:'copy',id:id}, ()=>{ initSlides(); });
    });

    $(document).on('click','.regenBtn',function(){
        let id = $(this).closest('tr').data('id');
        $.post('sql_crud_<?php echo $entity; ?>.php',{action:'regenerate',id:id}, res=>{
            if(res=='success') {
                Toast.show('Regenerate triggered for ID '+id,'success');
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

    // --- MODIFIED: '.editBtn' now opens the modal ---
    $(document).on('click','.editBtn',function(){
        let id = $(this).closest('tr').data('id');
        // The modal script provides a global function to open the entity form
        // ENTITY is a global JS constant defined at the top of this script block
        window.showEntityFormInModal(ENTITY, id);
    });

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

    $(document).on('change', '.mapRunSelect', function() {
        const select = $(this);
        const entityId = select.data('entity-id');
        const mapRunId = select.val();
        $.post('sql_crud_<?php echo $entity; ?>.php', { action: 'setActiveMapRun', entity_id: entityId, map_run_id: mapRunId }, function(res) {
            if(res === 'success') {
                Toast.show('Active map run updated','success');
                loadTable('', '', $('#searchInput').val(), currentPage);
            } else {
                Toast.show('Failed to update active map run','error');
            }
        });
    });

    $('#toggleSortBtn').click(function(){
        if(sortableEnabled) {
            disableSortable();
            $(this).text('+ Drag');
            sortableEnabled=false;
        } else {
            enableSortable();
            $(this).text('- Drag');
            sortableEnabled=true;
        }
    });
});
</script>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.css" />
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe.umd.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/photoswipe@5/dist/photoswipe-lightbox.umd.js"></script>
<?php else: ?>
    <link rel="stylesheet" href="/vendor/photoswipe/photoswipe.css" />
    <script src="/vendor/photoswipe/photoswipe.umd.js"></script>
    <script src="/vendor/photoswipe/photoswipe-lightbox.umd.js"></script>
<?php endif; ?>

<?php require "floatool.php"; ?>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

</body>
</html>
