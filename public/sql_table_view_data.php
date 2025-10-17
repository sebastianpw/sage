<?php

require "e.php";
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$mysqli = $spw->get mysqli();
$dbname = $spw->getDbName();

// Fetch all tables and views
$items = [];
$res = $mysqli->query("
    SELECT TABLE_NAME, TABLE_TYPE 
    FROM information_schema.tables 
    WHERE table_schema='".$dbname."'
    ORDER BY TABLE_TYPE, TABLE_NAME
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
}

// Selected table/view
$selectedItem = $_GET['item_name'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

$totalRows = 0;
$dataRows = [];
$columns = [];

if ($selectedItem) {
    // Check type
    $type = null;
    foreach ($items as $i) {
        if ($i['TABLE_NAME'] === $selectedItem) {
            $type = $i['TABLE_TYPE']; // BASE TABLE or VIEW
            break;
        }
    }

    if ($type === 'BASE TABLE' || $type === 'VIEW') {
        // Total rows for pagination
        $resCount = $mysqli->query("SELECT COUNT(*) AS total FROM `$selectedItem`");
        $totalRows = $resCount ? intval($resCount->fetch_assoc()['total']) : 0;
        $totalPages = ceil($totalRows / $limit);

        // Fetch current page data
        $resData = $mysqli->query("SELECT * FROM `$selectedItem` LIMIT $limit OFFSET $offset");
        if ($resData) {
            $columns = $resData->fetch_fields();
            $resData->data_seek(0); // reset pointer
            while ($row = $resData->fetch_assoc()) {
                $dataRows[] = $row;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Table/View Data</title>
<style>
body { font-family: Arial, sans-serif; margin: 30px; }
table { border-collapse: collapse; width: 100%; margin-top: 20px; }
th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
th { background: #f0f0f0; }
.pagination { margin-top: 20px; }
.pagination button { margin-right: 5px; padding: 5px 10px; cursor: pointer; }
.pagination .active { font-weight: bold; }
form { display: flex; align-items: center; gap: 10px; }
</style>
</head>
<body>

<!-- Header -->
<div style="display: flex; align-items: center; margin-bottom: 20px; gap: 10px;">
    <a href="dashboard.php" title="Dashboard" style="text-decoration: none; font-size: 24px; display: inline-block;">
        &#x1F5C3; <!-- 🗃 -->
    </a>
    <h2 style="margin: 0;">View Table/View Data</h2>
</div>

<!-- Table/View select form -->
<form method="get">
    <label for="item_name">Select table or view:</label>
    <select name="item_name" onchange="this.form.submit();">
        <option value="">-- Select --</option>
        <?php foreach ($items as $i): ?>
            <option value="<?= htmlspecialchars($i['TABLE_NAME']) ?>" <?= ($i['TABLE_NAME'] === $selectedItem) ? 'selected' : '' ?>>
                <?= htmlspecialchars($i['TABLE_NAME']) ?> <?= $i['TABLE_TYPE']==='VIEW'?'(view)':'' ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

<?php if ($selectedItem && $totalRows > 0): ?>
    <table>
        <thead>
            <tr>
                <?php foreach ($columns as $col): ?>
                    <th><?= htmlspecialchars($col->name) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dataRows as $row): ?>
                <tr>
                    <?php foreach ($columns as $col): ?>
                        <td><?= htmlspecialchars($row[$col->name]) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Pagination -->
    <div class="pagination">
        <?php
        for ($p = 1; $p <= $totalPages; $p++) {
            $active = ($p == $page) ? 'active' : '';
            $url = "?item_name=" . urlencode($selectedItem) . "&page=$p";
            echo "<button class=\"$active\" onclick=\"window.location='$url'\">$p</button>";
        }
        ?>
    </div>
<?php elseif ($selectedItem): ?>
    <p>No data found in <?= htmlspecialchars($selectedItem) ?>.</p>
<?php endif; ?>

</body>
</html>
