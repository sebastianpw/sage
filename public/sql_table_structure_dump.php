<?php
require "e.php";
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$mysqli = $spw->getMysqli();
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

$sqlOutput = '';
$selectedItem = '';
$selectedType = '';

// Handle single table/view dump
if (isset($_POST['item_name']) && !isset($_POST['download_all_views'])) {
    $selectedItem = $_POST['item_name'];

    // Find the type
    foreach ($items as $i) {
        if ($i['TABLE_NAME'] === $selectedItem) {
            $selectedType = $i['TABLE_TYPE']; // BASE TABLE or VIEW
            break;
        }
    }

    if ($selectedType) {
        $sqlOutput = "-- Dump for `$selectedItem` (" . strtolower($selectedType) . ")\n-- Generated: ".date('Y-m-d H:i:s')."\n\n";

        if ($selectedType === 'BASE TABLE') {
            $res = $mysqli->query("SHOW CREATE TABLE `$selectedItem`");
            $row = $res->fetch_assoc();
            $createStmt = isset($row['Create Table']) ? $row['Create Table'] : current($row);
            $sqlOutput .= $createStmt . ";\n";
        } elseif ($selectedType === 'VIEW') {
            $res = $mysqli->query("SHOW CREATE VIEW `$selectedItem`");
            $row = $res->fetch_assoc();
            $createStmt = isset($row['Create View']) ? $row['Create View'] : current($row);
            $sqlOutput .= $createStmt . ";\n";
        }
    }
}

// Handle "Download All Views"
if (isset($_POST['download_all_views'])) {
    $allViewsSql = "-- Dump for all views in ".$dbname."\n-- Generated: ".date('Y-m-d H:i:s')."\n\n";
    foreach ($items as $i) {
        if ($i['TABLE_TYPE'] === 'VIEW') {
            $res = $mysqli->query("SHOW CREATE VIEW `{$i['TABLE_NAME']}`");
            $row = $res->fetch_assoc();
            $createStmt = isset($row['Create View']) ? $row['Create View'] : current($row);
            $allViewsSql .= $createStmt . ";\n\n";
        }
    }

    // Send file for download
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="all_views_dump_' . date('Ymd_His') . '.sql"');
    echo $allViewsSql;
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>View Table/View Structure</title>
<style>
body { font-family: Arial, sans-serif; margin: 30px; }
form { 
    display: flex; 
    flex-direction: column;   /* stack children vertically */
    gap: 10px;                /* space between rows */
    margin-bottom: 20px; 
    max-width: 400px;         /* optional: prevent full-width stretching */
}
textarea { 
    width: 100%; 
    height: 400px; 
    font-family: monospace; 
    font-size: 14px; 
    white-space: pre; 
}
button { 
    padding: 6px 12px; 
    font-size: 14px; 
    cursor: pointer; 
    align-self: flex-start;   /* keep button left-aligned instead of stretching */
}
</style>

</head>
<body>
<?php require "floatool.php"; ?>
<!-- Header -->
<div style="display: flex; align-items: center; margin-bottom: 20px; gap: 10px;">
    <a href="dashboard.php" title="Dashboard" style="text-decoration: none; font-size: 24px; display: inline-block;">
        &#x1F5C3; <!-- 🗃 -->
    </a>
    <h2 style="margin: 0;">View Table/View Structure</h2>
</div>

<!-- Table/View select form -->
<form method="post">
    <label for="item_name">Select table or view:</label>
    <select name="item_name" onchange="this.form.submit();">
        <option value="">-- Select --</option>
        <?php foreach ($items as $i): ?>
            <option value="<?= htmlspecialchars($i['TABLE_NAME']) ?>" <?= ($i['TABLE_NAME'] === $selectedItem) ? 'selected' : '' ?>>
                <?= htmlspecialchars($i['TABLE_NAME']) ?> <?= $i['TABLE_TYPE']==='VIEW'?'(view)':'' ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit" name="download_all_views" value="1">Download All Views</button>
</form>

<!-- SQL output -->
<?php if ($sqlOutput): ?>
    <textarea readonly wrap="off"><?= htmlspecialchars($sqlOutput) ?></textarea>
<?php endif; ?>

</body>
</html>
