<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php'; // $mysqli oder $pdo ist bereit

// Wir nutzen $mysqli in diesem Beispiel
if (!$mysqli) {
    die("Datenbankverbindung fehlt.");
}

// Alle Views auslesen
$query = "SELECT table_name, view_definition FROM information_schema.views WHERE table_schema = DATABASE()";
$result = $mysqli->query($query);

if (!$result) {
    die("Fehler beim Abrufen der Views: " . $mysqli->error);
}

$messages = [];

while ($row = $result->fetch_assoc()) {
    $viewName = $row['table_name'];
    $viewDef  = $row['view_definition'];

    // DROP + CREATE TABLE SQL
    $sql = "DROP VIEW IF EXISTS `$viewName`; CREATE TABLE `$viewName` AS $viewDef;";

    // Ausführen
    if (!$mysqli->multi_query($sql)) {
        $messages[] = "<div class='alert alert-danger'>Fehler bei View <strong>$viewName</strong>: " . htmlspecialchars($mysqli->error) . "</div>";
    } else {
        // Ergebnisse durchlaufen, um multi_query sauber abzuschließen
        do {
            if ($res = $mysqli->store_result()) {
                $res->free();
            }
        } while ($mysqli->more_results() && $mysqli->next_result());

        $messages[] = "<div class='alert alert-success'>View <strong>$viewName</strong> erfolgreich in Tabelle umgewandelt.</div>";
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Views zu Tabellen umwandeln</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-3">
<h1>Views → Tabellen Umwandlung</h1>
<?php
if (empty($messages)) {
    echo "<div class='alert alert-info'>Keine Views gefunden.</div>";
} else {
    foreach ($messages as $msg) {
        echo $msg;
    }
}
?>
</body>
</html>
