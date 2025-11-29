<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php'; // $mysqli oder $pdo ist bereit

if (!$mysqli) {
    die("Datenbankverbindung fehlt.");
}

$messages = [];
$executed = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm']) && $_POST['confirm'] === 'yes') {
    $executed = true;

    // Alle Views auslesen
    $query = "SELECT table_name, view_definition FROM information_schema.views WHERE table_schema = DATABASE()";
    $result = $mysqli->query($query);

    if (!$result) {
        die("Fehler beim Abrufen der Views: " . $mysqli->error);
    }

    while ($row = $result->fetch_assoc()) {
        $viewName = $row['table_name'];
        $viewDef  = $row['view_definition'];

        // DROP + CREATE TABLE SQL
        $sql = "DROP VIEW IF EXISTS `$viewName`; CREATE TABLE `$viewName` AS $viewDef;";

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
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Views zu Tabellen umwandeln</title>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
  <!-- Bootstrap CSS via CDN -->
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
    crossorigin="anonymous">
<?php else: ?>
  <!-- Bootstrap CSS via local copy -->
  <link href="/vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
<?php endif; ?>

</head>
<body class="p-4 bg-light">

<div class="container">
  <h1 class="mb-4 text-danger">⚠️ Views → Tabellen Umwandlung</h1>

  <?php if (!$executed): ?>
    <div class="alert alert-danger" role="alert">
      <h4 class="alert-heading">Achtung – Irreversible Aktion!</h4>
      <p>Dieses Skript wird <strong>alle MySQL-Views in echte Tabellen umwandeln</strong>.
         Dabei werden sämtliche Views gelöscht und durch Tabellen mit demselben Namen ersetzt.
         <strong>Dieser Vorgang kann nicht rückgängig gemacht werden!</strong></p>
      <hr>
      <p class="mb-0 text-danger fw-bold">Bitte führe vorher ein vollständiges Datenbank-Backup durch.</p>
    </div>

    <form method="post" class="mt-4">
      <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="confirm" name="confirm" value="yes" onchange="toggleButton()">
        <label class="form-check-label fw-bold text-danger" for="confirm">
          Ich verstehe vollständig, dass dieser Vorgang irreversibel ist und alle Views zu Tabellen macht.
        </label>
      </div>

      <button type="submit" id="submitBtn" class="btn btn-danger" disabled>
        🚨 Views jetzt dauerhaft umwandeln
      </button>
    </form>

    <script>
    function toggleButton() {
      const checkbox = document.getElementById('confirm');
      const button = document.getElementById('submitBtn');
      button.disabled = !checkbox.checked;
    }
    </script>

  <?php else: ?>
    <h2 class="mb-3 text-success">Umwandlungsergebnisse</h2>
    <?php
      if (empty($messages)) {
          echo "<div class='alert alert-info'>Keine Views gefunden.</div>";
      } else {
          foreach ($messages as $msg) {
              echo $msg;
          }
      }
    ?>
    <a href="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="btn btn-secondary mt-3">Zurück</a>
  <?php endif; ?>
</div>
</body>
</html>
