<?php
// public/dimensionals_crud.php
// CRUD for 3D Models (Dimensionals)

require "e.php";
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';
require "entity_icons.php";

$entity = "dimensionals";

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// --- DISCOVERY & CONFIGURATION ---
$stmt = $pdo->query("SHOW COLUMNS FROM `$entity`");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

$hasOrder = in_array('order', $columns);
$hasDescription = in_array('description', $columns);
$hasMapRun = in_array('active_map_run_id', $columns);

// Icon Selection
$iconChar = $entityIcons[$entity] ?? '🧊';

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

        // Join to get thumbnail from map run or mesh? 
        // For now, keep it simple. We can add mesh filename join if needed.
        
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

    if ($action == 'reorder' && $hasOrder) {
        $orderData = $_POST['order'] ?? [];
        $stmt = $pdo->prepare("UPDATE `$entity` SET `order` = :order WHERE id = :id");
        foreach ($orderData as $item) {
            $stmt->execute(['order'=>(int)$item['order'], 'id'=>(int)$item['id']]);
        }
        exit('success');
    }

    if($action === 'fetchMapRuns' && $hasMapRun) {
        try {
            // Using generic fallback since view might not exist yet
            $stmt = $pdo->prepare("SELECT id, created_at, note, 0 as is_active FROM map_runs WHERE entity_type = :ent ORDER BY id DESC LIMIT 20");
            $stmt->execute(['ent' => $entity]);
        } catch (Exception $e) {
             echo json_encode([]); exit;
        }
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
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

<!-- Swiper -->
<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/swiper@10/swiper-bundle.min.js"></script>
<?php else: ?>
    <link rel="stylesheet" href="/vendor/swiper/swiper-bundle.min.css" />
    <script src="/vendor/swiper/swiper-bundle.min.js"></script>
<?php endif; ?>

<style>
/* Styling matches animatics_crud.php */
:root {
    --table-header-bg: rgba(var(--muted-border-rgb), 0.1);
    --table-stripe: rgba(var(--muted-border-rgb), 0.03);
}

html, body { width: 100%; max-width: 100%; overflow-x: hidden; }
body { padding: 20px; background-color: var(--bg); color: var(--text); padding-bottom: 100px; position: relative; box-sizing: border-box; }

.header-compact { display: flex; align-items: center; gap: 15px; margin-bottom: 10px; margin-left: 60px; height: 40px; }
.search-line { display: flex; align-items: center; gap: 6px; margin-bottom: 20px; margin-left: 60px; }

.entity-icon-link { font-size: 1.5rem; text-decoration: none; line-height: 1; transition: transform 0.2s; display: block; }
.entity-icon-link:hover { transform: scale(1.15); }

.header-controls { display: flex; align-items: center; gap: 6px; }
.search-input { padding: 4px 8px; font-size: 0.85rem; border: 1px solid var(--border); border-radius: 4px; background: var(--card); color: var(--text); width: 200px; }

table { width: 100%; border-collapse: collapse; background: var(--card); border-radius: 8px; overflow: hidden; box-shadow: var(--card-elevation); }
th { background: var(--table-header-bg); color: var(--text-muted); font-weight: 600; text-align: left; padding: 12px; font-size: 0.85rem; text-transform: uppercase; }
td { padding: 10px 12px; border-bottom: 1px solid var(--border); vertical-align: middle; color: var(--text); }
tr:last-child td { border-bottom: none; }
tr:nth-child(even) { background-color: var(--table-stripe); }

td[contenteditable="true"] { background: rgba(var(--muted-border-rgb), 0.05); border-radius: 3px; min-width: 50px; }
td[contenteditable="true"]:focus { outline: 2px solid var(--accent); background: var(--bg); }

.action-btn { width: 28px; height: 28px; padding: 0; display: inline-flex; align-items: center; justify-content: center; border: 1px solid var(--border); background: var(--bg); color: var(--text-muted); border-radius: 4px; cursor: pointer; transition: all 0.2s; }
.action-btn:hover { border-color: var(--accent); color: var(--accent); }
.action-btn.delete:hover { border-color: var(--red); color: var(--red); }

.swiper { width: 100%; overflow: visible; padding-bottom: 120px; }
.swiper-pagination { bottom: 0 !important; display: flex !important; flex-wrap: wrap !important; justify-content: center !important; padding: 10px; }
.swiper-pagination-bullet { margin: 4px !important; width: 10px; height: 10px; }

.dragHandle { cursor: grab; color: var(--text-muted); font-size: 1.2rem; margin-right: 8px; }

@media (max-width: 768px) {
    .header-compact { flex-wrap: wrap; height: auto; margin-left: 50px; }
    .search-line { margin-left: 0; padding: 0 10px; }
    .search-input { width: 100%; }
    table, thead, tbody, th, td, tr { display: block; }
    thead { display: none; }
    tr { margin-bottom: 15px; border: 1px solid var(--border); border-radius: 8px; background: var(--card); padding: 10px; }
    td { display: flex; justify-content: space-between; align-items: center; border: none; padding: 8px 0; border-bottom: 1px solid rgba(var(--muted-border-rgb), 0.1); }
    td::before { content: attr(data-label); font-weight: 600; color: var(--text-muted); font-size: 0.8rem; margin-right: 15px; }
    tr td:first-child { display: flex; width: 100%; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 8px; }
    td[data-label="Description"] { display: block !important; }
}
</style>
</head>
<body>

<?php require __DIR__ . '/modal_frame_details.php'; ?>

<div class="header-compact">
    <!-- Icon linking to Babylon Viewer -->
    <a href="babylon_view.php" class="entity-icon-link" title="3D Viewer"><?php echo $iconChar; ?></a>

    <div class="header-controls">
        <button id="addBtn" class="btn btn-sm btn-outline-primary">Add</button>
        <!-- Mass Upload Link -->
        <a href="view_mass_glb_upload.php" class="btn btn-sm btn-outline-secondary" title="Mass Upload">📤</a>

        <?php if($hasOrder): ?>
        <button id="toggleSortBtn" class="btn btn-sm btn-outline-secondary">Drag</button>
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
      <div class="slide-inner">
        <table id="dataTable">
            <thead>
                <tr>
                    <th width="30%">Name</th>
                    <th width="140">Actions</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="swiper-pagination"></div>
</div>

<script>
const ENTITY = '<?php echo $entity; ?>';
const HAS_ORDER = <?php echo $hasOrder ? 'true' : 'false'; ?>;

let currentPage = 1;
let rowsPerPage = 10;
let sortableEnabled = false;
let swiper = null;

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
        html += `<button class="action-btn view3dBtn" title="View 3D">🧊</button>`;
        html += `<button class="action-btn editBtn" title="Details">🕸️</button>`;
        html += `<button class="action-btn copyBtn" title="Copy">⎘</button>`;
        html += `<button class="action-btn delete" title="Delete">🗑</button>`;
        html += `</div></td>`;

        // 3. Description
        html += `<td data-label="Description" contenteditable="true" data-field="description">${row.description || ''}</td>`;

        html += `</tr>`;
    });
    return html;
}

function initSlides() {
    const search = $('#searchInput').val();
    $.post('dimensionals_crud.php', { action: 'fetch', search: search, page: 1, limit: rowsPerPage }, function(data) {
        data = JSON.parse(data);
        const firstSlide = $('#mainSwiper .swiper-slide').first();
        firstSlide.find('tbody').html(buildRowsHTML(data.rows));
        
        $('#mainSwiper .swiper-wrapper .swiper-slide').not(':first').remove();
        const header = $('#dataTable thead').prop('outerHTML');
        for (let p = 2; p <= data.totalPages; p++) {
             $('#mainSwiper .swiper-wrapper').append(`<div class="swiper-slide" data-page="${p}" data-loaded="0"><div class="slide-inner"><table>${header}<tbody></tbody></table></div></div>`);
        }
        
        if (swiper) swiper.destroy();
        swiper = new Swiper('#mainSwiper', {
            autoHeight: true,
            pagination: { el: '.swiper-pagination', clickable: true },
            on: { slideChange: function() { loadPageData(this.activeIndex + 1); } }
        });
        
        if (sortableEnabled) enableSortable(firstSlide.find('tbody'));
    });
}

function loadPageData(page) {
    const slide = $(`.swiper-slide[data-page="${page}"]`);
    if (slide.attr('data-loaded') === '1') return;
    const search = $('#searchInput').val();
    $.post('dimensionals_crud.php', { action: 'fetch', search: search, page: page, limit: rowsPerPage }, function(data) {
        data = JSON.parse(data);
        slide.find('tbody').html(buildRowsHTML(data.rows));
        slide.attr('data-loaded', '1');
        swiper.updateAutoHeight();
    });
}

$(document).ready(function() {
    initSlides();
    
    $('#searchInput').on('keyup', function(e) { if(e.key==='Enter') initSlides(); });
    $('#sendSearchBtn').click(function() { initSlides(); });
    $('#resetSearchBtn').click(function() { $('#searchInput').val(''); initSlides(); });
    $('#addBtn').click(function() { $.post('dimensionals_crud.php', {action:'add'}, function(){ initSlides(); Toast.show('Added','success'); }); });

    // Inline Edit
    $(document).on('blur', '[contenteditable="true"]', function() {
        const el = $(this);
        const id = el.closest('tr').data('id');
        const field = el.data('field');
        const val = el.text();
        $.post('dimensionals_crud.php', { action: 'update', id: id, field: field, value: val }, function(res) {
            if(res!=='success') Toast.show('Error saving','error');
        });
    });

    $(document).on('click', '.copyBtn', function() { if(confirm('Copy?')) $.post('dimensionals_crud.php', {action:'copy', id:$(this).closest('tr').data('id')}, function(){ initSlides(); Toast.show('Copied','success'); }); });
    $(document).on('click', '.delete', function() { if(confirm('Delete?')) $.post('dimensionals_crud.php', {action:'delete', id:$(this).closest('tr').data('id')}, function(){ initSlides(); Toast.show('Deleted','success'); }); });
    $(document).on('click', '.editBtn', function() { if(window.showEntityFormInModal) window.showEntityFormInModal(ENTITY, $(this).closest('tr').data('id')); });
    
    // View 3D Button
    $(document).on('click', '.view3dBtn', function() {
        const id = $(this).closest('tr').data('id');
        window.location.href = `babylon_view.php?entity_type=${ENTITY}&entity_id=${id}`;
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
        $.post('dimensionals_crud.php', {action:'reorder', order:o}, function(){ Toast.show('Saved','success'); });
    }});
}
</script>
<?php require_once "forge_tool.php"; ?>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>