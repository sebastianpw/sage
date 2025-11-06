<?php

$entity = "scheduled_tasks";

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\TaskLockManager;

$lockManager = new TaskLockManager();

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

    if ($action === 'run_now') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) exit('Invalid ID');

        $stmt = $pdo->prepare("UPDATE `$entity` SET run_now = 1 WHERE id = :id");
        $stmt->execute(['id' => $id]);

        exit('success');
    }

    if ($action === 'fetch') {
        $search = $_POST['search'] ?? '';
        $page = (int)($_POST['page'] ?? 1);
        $limit = (int)($_POST['limit'] ?? 10);
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

        $sql = "SELECT id, name, args, script_path, last_run, require_lock, lock_scope, max_concurrent_runs FROM `$entity`";
        if ($search !== '') $sql .= " WHERE id = :id OR name LIKE :search";
        $sql .= " ORDER BY `order` ASC, id ASC LIMIT :limit OFFSET :offset";

        $stmt = $pdo->prepare($sql);
        if ($search !== '') {
            $stmt->bindValue(':id', (int)$search, PDO::PARAM_INT);
            $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll();
        
        // Add lock status for each task
        foreach ($rows as &$row) {
            $locks = $lockManager->getTaskLocks($row['id']);
            $row['active_locks'] = count($locks);
        }

        echo json_encode([
            'rows' => $rows,
            'totalPages' => $totalPages,
            'currentPage' => $page
        ]);
        exit;
    }

    if ($action === 'reorder') {
        $orderData = $_POST['order'] ?? [];
        if (!is_array($orderData)) exit('Invalid data');

        $stmt = $pdo->prepare("UPDATE `$entity` SET `order` = :order WHERE id = :id");
        foreach ($orderData as $item) {
            $id = (int)$item['id'];
            $ord = (int)$item['order'];
            $stmt->execute(['order' => $ord, 'id' => $id]);
        }
        exit('success');
    }

    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $field = $_POST['field'];
        $value = $_POST['value'];

        $stmt = $pdo->query("SHOW COLUMNS FROM `$entity`");
        $allowed = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array($field, $allowed)) exit('Invalid field');

        $stmt = $pdo->prepare("UPDATE `$entity` SET `$field` = :value WHERE id = :id");
        $stmt->execute(['value' => $value, 'id' => $id]);
        exit('success');
    }

    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM `$entity` WHERE id = :id");
        $stmt->execute(['id' => $id]);
        exit('success');
    }

    if ($action === 'add') {
        $stmt = $pdo->prepare("INSERT INTO `$entity` (name) VALUES ('New $entity')");
        $stmt->execute();
        echo $pdo->lastInsertId();
        exit;
    }

    if ($action === 'copy') {
        $id = (int)$_POST['id'];
        $columns = ['name', 'args', 'script_path', 'last_run'];
        $colsList = implode(", ", $columns);
        $placeholders = implode(", ", array_map(fn($c)=> ":$c", $columns));

        $stmt = $pdo->prepare("SELECT $colsList FROM `$entity` WHERE id = :id");
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) exit('Row not found');

        if (isset($row['name'])) $row['name'] = 'Copy of ' . $row['name'];

        $insertStmt = $pdo->prepare("INSERT INTO `$entity` ($colsList) VALUES ($placeholders)");
        $insertStmt->execute($row);

        echo $pdo->lastInsertId();
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo $entity; ?></title>
<?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>
<link rel="stylesheet" href="/css/toast.css">
<style>
body { font-family: Arial, sans-serif; margin: 20px; }
table { border-collapse: collapse; width: 100%; margin-top: 15px; }
th, td { border: 1px solid #ccc; padding: 8px; text-align: left; font-size: 14px; }
th { background: #f0f0f0; cursor: pointer; }
td[contenteditable="true"] { background: #f9f9f9; }
button { padding: 4px 8px; margin: 2px; }
.pagination { margin-top: 15px; }
.pagination button { margin-right: 5px; padding: 5px 10px; cursor: pointer; }
.pagination .active { font-weight: bold; }
@media (max-width:600px) {
    table, thead, tbody, th, td, tr { display:block; }
    tr { margin-bottom: 15px; border:1px solid #ddd; padding:10px; background:#fff; }
    td { border:none; padding:5px 0; }
    th { display:none; }
}
.dragHandle { font-size: 18px; color: #888; user-select: none; }
.dragHandle:hover { color: #333; }
.dragHandle span { width: 30px; height: 30px; line-height: 30px; font-size: 16px; }
.lock-badge { 
    display: inline-block; 
    background: #ffc107; 
    color: #000; 
    padding: 2px 6px; 
    border-radius: 3px; 
    font-size: 11px; 
    font-weight: bold;
    margin-left: 5px;
}
.lock-badge.active { background: #dc3545; color: #fff; }
.lock-badge.none { background: #28a745; color: #fff; }
td[contenteditable="true"][data-field="args"] {
  background-color: rgba(255, 0, 0, 0.05);  /* very light red nuance */
  border: 1px solid rgba(200, 0, 0, 0.3);   /* soft, darker red border */
  border-radius: 3px;                       /* optional: smooth corners */
}
td[contenteditable="true"][data-field="args"]:focus {
  outline: none;
  background-color: rgba(255, 0, 0, 0.08);
  border-color: rgba(200, 0, 0, 0.6);
}
</style>
<?php echo $eruda; ?>
</head>
<body>
<?php //require "floatool.php"; ?>
<div style="display: flex; align-items: center; margin-bottom: 15px; gap: 10px;">
    <a href="dashboard.php" title="Dashboard" style="text-decoration: none; font-size: 24px;">🔮</a>
    <h2 style="margin: 0;">Scheduler</h2>
    <button id="addBtn">Add New</button>
    <button id="logBtn">View Logs</button>
    <button id="locksBtn">🔒 Locks</button>
    <div id="heartbeatLed" style="
        width:15px; height:15px; border-radius:50%; 
        background:red; display:inline-block; margin-left:10px;"></div>
</div>

<div style="margin-bottom: 15px; display: flex; align-items: center; gap: 10px;">
    <input type="text" id="searchInput" placeholder="Search by id or name..."
           value="<?php echo htmlspecialchars($initialSearch, ENT_QUOTES); ?>" style="padding:5px; width:250px;">
    <button id="searchBtn">Search</button>
    <button id="toggleSortBtn">Enable Drag</button>
</div>

<table id="<?php echo $entity; ?>Table">
    <thead>
        <tr>
            <th>Drag</th>
            <th>Action</th>
            <th><a href="#" class="sortHeader" data-column="name" data-order="ASC">Name</a></th>
            <th><a href="#" class="sortHeader" data-column="args" data-order="ASC">Args</a></th>
            <th><a href="#" class="sortHeader" data-column="script_path" data-order="ASC">Script Path</a></th>
            <th><a href="#" class="sortHeader" data-column="last_run" data-order="ASC">Last Run</a></th>
        </tr>
    </thead>
    <tbody></tbody>
</table>

<div class="pagination" id="pagination"></div>

<!-- Log Overlay -->
<div id="logOverlay" style="display:none;">
    <div id="logOverlayContent">
        <button id="closeLogOverlay">✕ Close</button>
        <iframe id="logFrame" src="" frameborder="0"></iframe>
    </div>
</div>

<style>
#logOverlay {
    position: fixed; top:0; left:0; width:100%; height:100%;
    background: rgba(0,0,0,0.85); z-index: 9999;
    display: flex; justify-content: center; align-items: center;
}
#logOverlayContent {
    width: 80%; height: 80%; background: #111; padding: 20px;
    position: relative; display: flex; flex-direction: column;
}
#logFrame { flex: 1; width: 100%; background: #000; }
#closeLogOverlay {
    position: absolute; top: 10px; right: 10px;
    background: #b42318; color: #fff; border: none; padding: 5px 10px;
    cursor: pointer;
}
</style>

<div id="toast-container"></div>
<script src="/js/toast.js"></script>

<script>
let currentPage = 1;
let rowsPerPage = 10;
let initialSearch = $('#searchInput').val();

function loadTable(sort='id', order='DESC', search='', page=1) {
    $.post('scheduler_view.php', {action:'fetch', search:search, page:page, limit:rowsPerPage}, function(data){
        data = JSON.parse(data);
        let rows = '';
        data.rows.forEach(row => {
            const lockBadge = row.active_locks > 0 
                ? `<span class="lock-badge active">🔒 ${row.active_locks}</span>`
                : (row.require_lock ? `<span class="lock-badge none">✓</span>` : '');
            
            const lockInfo = row.require_lock 
                ? ` | Scope: ${row.lock_scope} | Max: ${row.max_concurrent_runs}`
                : '';
            
            rows += `<tr data-id="${row.id}">`;
            rows += '<td><span class="dragHandle">☰</span></td>';
            rows += `<td><button style="width: 50px; height: 50px; font-size: 180%;" class="runBtn">▶</button><button class="copyBtn">Copy</button><button class="deleteBtn">Delete</button></td>`;
            rows += `<td contenteditable="true" data-field="name">${row.name ?? ''}${lockBadge}</td>`;
            rows += `<td contenteditable="true" data-field="args">${row.args ?? ''}</td>`;
            rows += `<td contenteditable="true" data-field="script_path">${row.script_path ?? ''}</td>`;
            rows += `<td style="font-size: 10px;"><b>${row.id}</b> · last run: ${row.last_run ?? ''}${lockInfo}</td>`;
            rows += `</tr>`;
        });
        $('#<?php echo $entity; ?>Table tbody').html(rows);

        let paginationHtml = '';
        for(let i=1; i<=data.totalPages; i++){
            paginationHtml += `<button class="pageBtn ${i==data.currentPage?'active':''}" data-page="${i}">${i}</button>`;
        }
        $('#pagination').html(paginationHtml);
        currentPage = data.currentPage;
    });
}

$(document).ready(function() {
    loadTable('<?php echo $sort; ?>', '<?php echo $order; ?>', initialSearch, currentPage);

    $('#searchBtn').click(()=> loadTable('<?php echo $sort; ?>','<?php echo $order; ?>',$('#searchInput').val(),1));
    $('#searchInput').on('keyup', e=> { if(e.key==='Enter') loadTable('<?php echo $sort; ?>','<?php echo $order; ?>',$('#searchInput').val(),1); });

    $(document).on('blur','td[contenteditable="true"]',function(){
        let td = $(this), value = td.text(), field = td.data('field'), id = td.closest('tr').data('id');
        $.post('scheduler_view.php',{action:'update',id:id,field:field,value:value}, res => { 
            if(res==='success') {
                Toast.show('Update saved!', 'success');
            } else {
                Toast.show('Update failed', 'error');
            }
        });
    });

    $(document).on('click','.deleteBtn',function(){
        if(!confirm('Are you sure?')) return;
        let id = $(this).closest('tr').data('id');
        $.post('scheduler_view.php',{action:'delete',id:id}, res=>{
            if(res==='success'){
                Toast.show('Row deleted', 'info');
                loadTable('','',$('#searchInput').val(),currentPage);
            } else {
                Toast.show('Delete failed', 'error');
            }
        });
    });

    $(document).on('click','#addBtn',function(){
        $.post('scheduler_view.php',{action:'add'}, res=>{
            if(res) {
                Toast.show('New task added', 'success');
                loadTable('','',$('#searchInput').val(),currentPage);
            } else {
                Toast.show('Add failed', 'error');
            }
        });
    });

    $(document).on('click','.copyBtn',function(){
        let id = $(this).closest('tr').data('id');
        $.post('scheduler_view.php',{action:'copy',id:id}, res=>{
            if(res) {
                Toast.show('Row copied', 'success');
                loadTable('','',$('#searchInput').val(),currentPage);
            } else {
                Toast.show('Copy failed', 'error');
            }
        });
    });

    $(document).on('click','.runBtn',function(){
        let id = $(this).closest('tr').data('id');
        $.post('scheduler_view.php', {action:'run_now', id:id}, function(res){
            if(res === 'success') {
                Toast.show('Task scheduled to run now!', 'success');
            } else {
                Toast.show('Failed to trigger task', 'error');
            }
        });
    });

    $('#logBtn').click(function(){
        $('#logFrame').attr('src', 'view_scheduler_log.php');
        $('#logOverlay').fadeIn();
    });
    
    $('#closeLogOverlay').click(function(){
        $('#logOverlay').fadeOut();
    });

    $('#locksBtn').click(function(){
        window.location.href = 'task_locks_view.php';
    });

    $('#toggleSortBtn').click(function() {
        if(sortableEnabled) {
            disableSortable();
            $(this).text('Enable Drag');
        } else {
            enableSortable();
            $(this).text('Disable Drag');
        }
    });

    let sortableEnabled = false;
    let sortableInstance;

    function enableSortable() {
        sortableInstance = $('#<?php echo $entity; ?>Table tbody').sortable({
            handle: '.dragHandle',
            update: function(event, ui) {
                let orderData = [];
                $('#<?php echo $entity; ?>Table tbody tr').each(function(index){
                    let id = $(this).data('id');
                    orderData.push({id: id, order: index+1});
                });
                $.post('scheduler_view.php', {action:'reorder', order: orderData}, function(res){
                    if(res === 'success') {
                        Toast.show('Order saved', 'info');
                    } else {
                        Toast.show('Failed to save order', 'error');
                    }
                });
            }
        }).disableSelection();
        sortableEnabled = true;
    }

    function disableSortable() {
        if(sortableInstance) {
            sortableInstance.sortable('destroy');
            sortableEnabled = false;
        }
    }

    $(document).on('click','.pageBtn',function(){
        loadTable('<?php echo $sort; ?>','<?php echo $order; ?>',$('#searchInput').val(),$(this).data('page'));
    });

    function checkHeartbeat() {
        $.getJSON('heartbeat.php', function(data){
            let lastSeen = new Date(data.last_seen + ' UTC');
            let serverNow = new Date(data.server_time + ' UTC');

            let diffSec = (serverNow - lastSeen) / 1000;

            if(diffSec <= 10){
                $('#heartbeatLed').css('background','green');
            } else {
                $('#heartbeatLed').css('background','red');
            }
        });
    }

    setInterval(checkHeartbeat, 10000);
    checkHeartbeat();
});
</script>
<?php require "floatool.php"; ?>
</body>
</html>
