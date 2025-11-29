<?php
// regenerate_frames_set.php

require __DIR__ . '/bootstrap.php';

$spw = \App\Core\SpwBase::getInstance();
$mysqli = $spw->getMysqli();

// load $items array
require "sage_entities_items_array.php";

// Extract only the `name` column
$entitiesList = array_column($items, 'name');

$updateResults = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entity = $_POST['entity'] ?? '';
    $startId = isset($_POST['start_id']) ? (int)$_POST['start_id'] : 0;
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 1;

    if (in_array($entity, $entitiesList)) {
        $stmt = $mysqli->prepare("UPDATE {$entity} 
            SET regenerate_images = 1 
            WHERE id >= ? 
            ORDER BY id ASC 
            LIMIT ?");
        $stmt->bind_param('ii', $startId, $limit);

        if ($stmt->execute()) {
            $affected = $stmt->affected_rows;
            $updateResults = "<p class='success'>Set regenerate_images=1 for $affected rows in '{$entity}'.</p>";
        } else {
            $updateResults = "<p class='error'>Failed to update '{$entity}': " . $mysqli->error . "</p>";
        }
        $stmt->close();
    } else {
        $updateResults = "<p class='error'>Invalid entity selected.</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Regenerate Images</title>


<script>
  (function() {
    try {
      var theme = localStorage.getItem('spw_theme');
      if (theme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
      } else if (theme === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
      }
      // If no theme is set, we do nothing and let the CSS media query handle it.
    } catch (e) {
      // Fails gracefully
    }
  })();
</script>

<script src="/js/theme-manager.js"></script>

<link rel="stylesheet" href="/css/base.css">



<style>
.success { color: var(--success); font-weight: 600; }
.error { color: var(--error); font-weight: 600; }
.card { padding: 20px; border-radius: var(--radius); background: var(--card); box-shadow: var(--shadow-sm); }
</style>

<?php
echo $spw->getJquery();
?>

</head>
<body>

<div style="display: flex; align-items: center; margin: 20px 20px 0 20px; gap: 10px;">
    <a href="/dashboard.php" title="Dashboard" style="text-decoration: none; font-size: 24px; display:none;">&#x1F5C3;</a>
    <h2 style="margin: 0; margin-left:35px;">Regenerate Images</h2>
</div>

<div style="margin: 20px;">

    <div class="card">
        <form method="post">

            <label for="entity">Entity:</label>
            <select name="entity" id="entity" class="form-control">
                <?php foreach ($entitiesList as $e): ?>
                    <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
                <?php endforeach; ?>
            </select>

            <br>

            <label for="start_id">Starting ID:</label>
            <input type="number" name="start_id" id="start_id" class="form-control" value="0" min="0">

            <br>

            <label for="limit">Limit:</label>
            <input type="number" name="limit" id="limit" class="form-control" value="1" min="1">

            <br>

            <button type="submit" class="btn btn-primary button">Regenerate Images</button>

        </form>
    </div>

    <?php if ($updateResults): ?>
        <div class="notification notification-success" style="margin-top: 20px;"><?= $updateResults ?></div>
    <?php endif; ?>
</div>

<?php // require "floatool.php"; echo $eruda; ?>


</body>
</html>
