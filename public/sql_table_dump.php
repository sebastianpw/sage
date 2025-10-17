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
    WHERE table_schema='" . $dbname . "'
    ORDER BY TABLE_TYPE, TABLE_NAME
");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $items[] = $row;
    }
}

// Handle dump
if (isset($_POST['item_name'])) {
    $item = $_POST['item_name'];

    // Find the type
    $type = null;
    foreach ($items as $i) {
        if ($i['TABLE_NAME'] === $item) {
            $type = $i['TABLE_TYPE']; // BASE TABLE or VIEW
            break;
        }
    }

    if ($type) {
        $dump = "-- Dump for `$item` (" . strtolower($type) . ")\n-- Generated: ".date('Y-m-d H:i:s')."\n\n";

        if ($type === 'BASE TABLE') {
            // Table structure
            $res = $mysqli->query("SHOW CREATE TABLE `$item`");
            $row = $res->fetch_assoc();
            $createStmt = isset($row['Create Table']) ? $row['Create Table'] : current($row);
            $dump .= $createStmt . ";\n\n";

            // Table data
            $res = $mysqli->query("SELECT * FROM `$item`");
            if ($res && $res->num_rows > 0) {
                $dump .= "-- Data\n";
                while ($r = $res->fetch_assoc()) {
                    $cols = array_map(fn($c) => "`$c`", array_keys($r));
                    $vals = array_map(fn($v) => $v===null ? "NULL" : "'" . $mysqli->real_escape_string($v) . "'", array_values($r));
                    $dump .= "INSERT INTO `$item` (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ");\n";
                }
                $dump .= "\n";
            }
        } elseif ($type === 'VIEW') {
            // Just the view structure
            $res = $mysqli->query("SHOW CREATE VIEW `$item`");
            $row = $res->fetch_assoc();
            $createStmt = isset($row['Create View']) ? $row['Create View'] : current($row);
            $dump .= $createStmt . ";\n";
        }

        // Serve download
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="'.$item.'_dump_'.date('Ymd_His').'.sql"');
        echo $dump;
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Single Table/View Dump</title>
<style>
body { font-family: Arial, sans-serif; margin: 30px; }
form { display: flex; align-items: center; gap: 10px; }
</style>
</head>
<body>

<!-- Header -->
<div style="display: flex; align-items: center; margin-bottom: 20px; gap: 10px;">
    <a href="dashboard.php" title="Dashboard" style="text-decoration: none; font-size: 24px; display: inline-block;">
        &#x1F5C3; <!-- 🗃 -->
    </a>
    <h2 style="margin: 0;">Download Table/View Dump</h2>
</div>

<!-- Table/View select form -->
<form method="post">
    <label for="item_name">Select table or view:</label>
    <select name="item_name" onchange="this.form.submit();">
        <option value="">-- Select --</option>
        <?php foreach ($items as $i): ?>
            <option value="<?= htmlspecialchars($i['TABLE_NAME']) ?>">
                <?= htmlspecialchars($i['TABLE_NAME']) ?> <?= $i['TABLE_TYPE']==='VIEW'?'(view)':'' ?>
            </option>
        <?php endforeach; ?>
    </select>
</form>

</body>
</html>
