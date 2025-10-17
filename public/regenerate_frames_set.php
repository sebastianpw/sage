<?php
// regenerate_images.php
//
// Web GET example:
// http://localhost:8080/regenerate_images.php
//
// CLI example:
// php regenerate_images.php

require __DIR__ . '/bootstrap.php';

$spw = \App\Core\SpwBase::getInstance();
$mysqli = $spw->getMysqli();


// load $items array
require "sage_entities_items_array.php";

// Extract only the `name` column
$entitiesList = array_column($items, 'name');



/*
// all entities
	$entitiesList = ['generatives', 'characters', 'animas', 'character_poses', 'artifacts', 'vehicles', 'locations', 'backgrounds'];
 */

$updateResults = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entity = $_POST['entity'] ?? '';
    $startId = isset($_POST['start_id']) ? (int)$_POST['start_id'] : 0;
    $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 1;

    if (in_array($entity, $entitiesList)) {
        $stmt = $mysqli->prepare("UPDATE {$entity} SET regenerate_images = 1 WHERE id >= ? ORDER BY id ASC LIMIT ?");
        $stmt->bind_param('ii', $startId, $limit);
        if ($stmt->execute()) {
            $affected = $stmt->affected_rows;
            $updateResults = "<p style='color: #1a7f37; font-weight: 600;'>Set regenerate_images=1 for $affected rows in '{$entity}'</p>";
        } else {
            $updateResults = "<p style='color: #b42318; font-weight: 600;'>Failed to update '{$entity}': " . $mysqli->error . "</p>";
        }
        $stmt->close();
    } else {
        $updateResults = "<p style='color: #b42318; font-weight: 600;'>Invalid entity selected.</p>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Regenerate Images</title>
<link rel="stylesheet" href="css/form.css">
</head>
<body>
<?php require "floatool.php"; ?>
<div style="display: flex; align-items: center; margin: 20px 20px 0 20px; gap: 10px;">
    <a href="/dashboard.php" title="Dashboard" style="text-decoration: none; font-size: 24px;">&#x1F5C3;</a>
    <h2 style="margin: 0;">Regenerate Images</h2>
</div>

<div style="margin: 20px;">
    <form method="post">
        <label for="entity">Entity:</label>
        <select name="entity" id="entity">
            <?php foreach ($entitiesList as $e): ?>
                <option value="<?= htmlspecialchars($e) ?>"><?= htmlspecialchars($e) ?></option>
            <?php endforeach; ?>
        </select>
        <br /><br />

        <label for="start_id">Starting ID:</label>
        <input type="number" name="start_id" id="start_id" value="0" min="0">
        <br /><br />

        <label for="limit">Limit:</label>
        <input type="number" name="limit" id="limit" value="1" min="1">
        <br /><br />

        <button type="submit">Regenerate Images</button>
    </form>

    <?php if ($updateResults): ?>
        <div style="margin-top: 20px;"><?= $updateResults ?></div>
    <?php endif; ?>
</div>

</body>
</html>
