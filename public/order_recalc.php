<?php
// order_recalc.php

require __DIR__ . '/bootstrap.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// --- Entities ---
$allEntities = [
    'scene_parts','characters','character_poses','animas',
    'locations','backgrounds','artifacts','vehicles',
    'generatives','sage_ideas','sage_todos','spawns','sketches','prompt_matrix_blueprints','composites','controlnet_maps','pastebin'
];

// --- POST Handling ---
$message = '';
$selectedEntity = '';
$direction = 'ASC';
$keepNonZero = false;

// Detect Ajax request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedEntity = $_POST['entity'] ?? '';
    $direction = $_POST['direction'] ?? 'ASC';
    $keepNonZero = isset($_POST['keepNonZero']) && $_POST['keepNonZero'];

    $response = [
        'success' => false,
        'message' => ''
    ];

    if ($selectedEntity && in_array($selectedEntity, $allEntities)) {
        $stmt = $pdo->query("SELECT id, `order` FROM `$selectedEntity` ORDER BY id $direction");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $updateStmt = $pdo->prepare("UPDATE `$selectedEntity` SET `order` = :order WHERE id = :id");
        $counter = 1;

        foreach ($rows as $row) {
            if ($keepNonZero && $row['order'] != 0) continue;

            $updateStmt->execute([
                'order' => $counter,
                'id' => $row['id']
            ]);
            $counter++;
        }

        $message = "Orders recalculated for table '$selectedEntity'.";
        $response['success'] = true;
        $response['message'] = $message;
    } else {
        $message = "Please select a valid entity.";
        $response['message'] = $message;
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recalculate Orders</title>
    <link rel="stylesheet" href="css/form.css">
    <script>
        async function reorderEntity(entity, direction = 'DESC', keepNonZero = false) {
            try {
                const formData = new FormData();
                formData.append('entity', entity);
                formData.append('direction', direction);
                if (keepNonZero) formData.append('keepNonZero', '1');

                const response = await fetch('/recalc_orders.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    // Optionally reload the page or refresh table
                    // location.reload();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (err) {
                console.error(err);
                alert('AJAX request failed');
            }
        }
    </script>
</head>
<body>

<div style="position: absolute; top: 50px; margin: 0 20px 80px 20px;">

    <div style="position: absolute;">
        <a href="/dashboard.php" 
           title="Dashboard" 
           style="text-decoration: none; font-size: 24px; display: inline-block; position: absolute; top: 10px; left: 10px; z-index: 999;">
            &#x1F5C3;
        </a>

        <h2 style="margin: 0; padding: 0 0 20px 0; position: absolute; top: 10px; left: 50px;">
            Recalculate Orders
        </h2>          
    </div>

</div>

<div style="margin: 0; padding: 0;">
    <br />
</div>

<div style="display: flex; align-items: center; margin: 20px 20px 0 20px; gap: 10px;">

    <form method="post">
        <h2>Recalculate Orders for Entity</h2>

        <?php if ($message): ?>
            <div class="result success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <label for="entity">Entity:</label>
        <select name="entity" id="entity" required>
            <option value="">-- Select Entity --</option>
            <?php foreach ($allEntities as $e): ?>
                <option value="<?= htmlspecialchars($e) ?>" <?= $selectedEntity === $e ? 'selected' : '' ?>>
                    <?= htmlspecialchars($e) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label for="direction">Direction:</label>
        <select name="direction" id="direction">
            <option value="ASC" <?= $direction === 'ASC' ? 'selected' : '' ?>>Ascending (ID ASC)</option>
            <option value="DESC" <?= $direction === 'DESC' ? 'selected' : '' ?>>Descending (ID DESC)</option>
        </select>

        <label for="keepNonZero">
            <input type="checkbox" name="keepNonZero" id="keepNonZero" value="1" <?= $keepNonZero ? 'checked' : '' ?>>
            Keep non-zero orders
        </label>

        <button type="submit">Recalculate Orders</button>
    </form>

</div>

</body>
</html>
