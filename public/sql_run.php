<?php
require "e.php";
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$mysqli = $spw->get mysqli();
$dbname = $spw->getDbName();


$sql = '';
$result_table = '';
$message = '';

if (isset($_POST['sql_query']) && trim($_POST['sql_query'])) {
    $sql = $_POST['sql_query'];
    $res = $mysqli->query($sql);

    if ($res === false) {
        $message = "Error: " . $mysqli->error;
    } else {
        // If SELECT query, fetch results
        if ($res instanceof mysqli_result) {
            $rows = [];
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            if ($rows) {
                // Generate HTML table
                $result_table = "<table border='1' cellpadding='5'><thead><tr>";
                foreach (array_keys($rows[0]) as $col) {
                    $result_table .= "<th>" . htmlspecialchars($col) . "</th>";
                }
                $result_table .= "</tr></thead><tbody>";
                foreach ($rows as $row) {
                    $result_table .= "<tr>";
                    foreach ($row as $cell) {
                        $result_table .= "<td>" . htmlspecialchars($cell) . "</td>";
                    }
                    $result_table .= "</tr>";
                }
                $result_table .= "</tbody></table>";
            } else {
                $message = "Query executed successfully, no rows returned.";
            }
        } else {
            $message = "Query executed successfully. Affected rows: " . $mysqli->affected_rows;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Run SQL Query</title>
<style>
body { font-family: Arial, sans-serif; margin: 30px; }
textarea { width: 100%; height: 150px; font-family: monospace; font-size: 14px; margin-bottom: 10px; }
button { padding: 5px 15px; font-size: 14px; }
table { border-collapse: collapse; margin-top: 20px; width: 100%; }
th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
th { background: #f0f0f0; }
.message { margin-top: 20px; font-weight: bold; }
</style>
</head>
<body>

<!-- Header -->
<div style="display: flex; align-items: center; margin-bottom: 20px; gap: 10px;">
    <a href="dashboard.php" title="Dashboard" style="text-decoration: none; font-size: 24px; display: inline-block;">
        &#x1F5C3; <!-- 🗃 -->
    </a>
    <h2 style="margin: 0;">Run SQL Query</h2>
</div>

<!-- SQL Form -->
<form method="post">
    <textarea name="sql_query" placeholder="Paste your SQL statement here..."><?= htmlspecialchars($sql) ?></textarea>
    <br>
    <button type="submit">Run SQL</button>
</form>

<!-- Result / Message -->
<?php if ($result_table): ?>
    <?= $result_table ?>
<?php elseif ($message): ?>
    <div class="message"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

</body>
</html>
