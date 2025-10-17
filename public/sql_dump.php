<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$mysqli = $spw->getMysqli();

if (isset($_POST['dump_db'])) {
    $dbName = $spw->getDbName();

    $dump = "-- Database dump for `$dbName`\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

    // -----------------------------
    // Step 1: Dump BASE TABLEs (structure + data)
    // -----------------------------
    $tables = [];
    $res = $mysqli->query("
        SELECT TABLE_NAME 
        FROM information_schema.tables 
        WHERE table_schema='$dbName' AND TABLE_TYPE='BASE TABLE'
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $tables[] = $row['TABLE_NAME'];
        }
    }

    $dump .= "-- Step 1: Tables (structure + data)\n\n";

    foreach ($tables as $table) {
        // Table structure
        $res = $mysqli->query("SHOW CREATE TABLE `$table`");
        if ($res) {
            $row = $res->fetch_assoc();
            $createStmt = isset($row['Create Table']) ? $row['Create Table'] : current($row);
            $dump .= "-- Table structure for `$table`\n" . $createStmt . ";\n\n";
        }

        // Table data
        $res = $mysqli->query("SELECT * FROM `$table`");
        if ($res && $res->num_rows > 0) {
            $dump .= "-- Data for `$table`\n";
            while ($r = $res->fetch_assoc()) {
                $cols = array_map(fn($c) => "`$c`", array_keys($r));
                $vals = array_map(fn($v) => $v === null ? "NULL" : "'" . $mysqli->real_escape_string($v) . "'", array_values($r));
                $dump .= "INSERT INTO `$table` (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ");\n";
            }
            $dump .= "\n";
        }
    }

    // -----------------------------
    // Step 2: Dump VIEWs (structure only)
    // -----------------------------
    $views = [];
    $res = $mysqli->query("
        SELECT TABLE_NAME 
        FROM information_schema.tables 
        WHERE table_schema='$dbName' AND TABLE_TYPE='VIEW'
    ");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $views[] = $row['TABLE_NAME'];
        }
    }

    if (!empty($views)) {
        $dump .= "-- Step 2: Views (structure only)\n\n";
        foreach ($views as $view) {
            $res = $mysqli->query("SHOW CREATE VIEW `$view`");
            if ($res) {
                $row = $res->fetch_assoc();
                $createViewStmt = isset($row['Create View']) ? $row['Create View'] : current($row);
                $dump .= "-- View `$view`\n" . $createViewStmt . ";\n\n";
            }
        }
    }

    // -----------------------------
    // Step 3: Dump TRIGGERS
    // -----------------------------
    $res = $mysqli->query("
        SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, ACTION_STATEMENT, ACTION_TIMING
        FROM information_schema.triggers
        WHERE TRIGGER_SCHEMA='$dbName'
    ");
    if ($res && $res->num_rows > 0) {
        $dump .= "-- Step 3: Triggers\n\n";
        while ($row = $res->fetch_assoc()) {
            $triggerName = $row['TRIGGER_NAME'];
            $table = $row['EVENT_OBJECT_TABLE'];
            $timing = $row['ACTION_TIMING']; // BEFORE / AFTER
            $event = $row['EVENT_MANIPULATION']; // INSERT / UPDATE / DELETE
            $stmt = $row['ACTION_STATEMENT'];

            $dump .= "DELIMITER //\n";
            // Wrap in DROP IF EXISTS to ensure trigger is recreated safely
            $dump .= "DROP TRIGGER IF EXISTS `$triggerName`;//\n";
            $dump .= "CREATE TRIGGER `$triggerName` $timing $event ON `$table`\nFOR EACH ROW $stmt //\n";
            $dump .= "DELIMITER ;\n\n";
        }
    }

    // -----------------------------
    // Safety: ensure pastebin_before_insert trigger exists even if table is empty
    // -----------------------------
    $dump .= "-- Ensure pastebin_before_insert trigger exists\n\n";
    $dump .= "DELIMITER //\n";
    $dump .= "DROP TRIGGER IF EXISTS `pastebin_before_insert`;//\n";
    $dump .= "CREATE TRIGGER `pastebin_before_insert` BEFORE INSERT ON `pastebin`\n";
    $dump .= "FOR EACH ROW BEGIN\n";
    $dump .= "  IF NEW.url_token IS NULL OR NEW.url_token = '' THEN\n";
    $dump .= "    SET NEW.url_token = SHA2(UUID(), 256);\n";
    $dump .= "  END IF;\n";
    $dump .= "END;//\n";
    $dump .= "DELIMITER ;\n\n";

    // -----------------------------
    // Serve the SQL dump as download
    // -----------------------------
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $dbName . '_full_dump_' . date('Ymd_His') . '.sql"');
    echo $dump;
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Full Database Dump</title>
<style>
body { font-family: Arial, sans-serif; margin: 30px; }
button { padding: 10px 20px; font-size: 16px; cursor: pointer; }
</style>
</head>
<body>

<div style="display: flex; align-items: center; margin-bottom: 20px; gap: 10px;">
    <a href="dashboard.php" title="Dashboard" style="text-decoration: none; font-size: 24px; display: inline-block;">
        &#x1F5C3;
    </a>
    <h2 style="margin: 0;">Download Full Database Dump</h2>
</div>

<form method="post">
    <button type="submit" name="dump_db">Go & Download SQL Dump</button>
</form>

</body>
</html>
