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
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Recalculate Orders</title>

    <script>
      (function() {
        try {
          var theme = localStorage.getItem('spw_theme');
          if (theme === 'dark') document.documentElement.setAttribute('data-theme','dark');
          else if (theme === 'light') document.documentElement.setAttribute('data-theme','light');
        } catch(e){}
      })();
    </script>
    <script src="/js/theme-manager.js"></script>

    <!-- use base.css -->
    <link rel="stylesheet" href="/css/base.css">

    <style>
      /* minimal helpers to match your base layout */
      .card { padding: 18px; border-radius: var(--radius); background: var(--card); box-shadow: var(--shadow-sm); margin: 20px; }
      .form-group { margin-bottom: 12px; }
      .toolbar { display:flex; align-items:center; gap:12px; margin: 20px; }
    </style>

    <?php echo $spw->getJquery(); ?>
</head>
<body>

<div class="toolbar" aria-hidden="false">
    <a href="/dashboard.php" title="Dashboard" style="text-decoration:none;font-size:24px;">&#x1F5C3;</a>
    <h2 style="margin:0;">Recalculate Orders</h2>
</div>

<div class="card" role="region" aria-labelledby="recalcHeading">
    <h3 id="recalcHeading" style="margin-top:0;">Recalculate Orders for Entity</h3>

    <?php if ($message): ?>
        <div class="notification <?= ($response['success'] ?? false) ? 'notification-success' : 'notification-error' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <form method="post" id="recalcForm" class="recalc-form" style="margin-top:12px;">
        <div class="form-group">
            <label for="entity">Entity</label>
            <select name="entity" id="entity" class="form-control" required>
                <option value="">-- Select Entity --</option>
                <?php foreach ($allEntities as $e): ?>
                    <option value="<?= htmlspecialchars($e) ?>" <?= $selectedEntity === $e ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="direction">Direction</label>
            <select name="direction" id="direction" class="form-control">
                <option value="ASC" <?= $direction === 'ASC' ? 'selected' : '' ?>>Ascending (ID ASC)</option>
                <option value="DESC" <?= $direction === 'DESC' ? 'selected' : '' ?>>Descending (ID DESC)</option>
            </select>
        </div>

        <div class="form-group">
            <label class="checkbox-label" for="keepNonZero">
                <input type="checkbox" name="keepNonZero" id="keepNonZero" class="form-control-checkbox" value="1" <?= $keepNonZero ? 'checked' : '' ?>>
                <span style="margin-left:8px;">Keep non-zero orders</span>
            </label>
        </div>

        <div class="form-group" style="display:flex;gap:8px;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">Recalculate Orders</button>
            <button type="button" class="btn btn-secondary" onclick="document.getElementById('recalcForm').reset();">Reset</button>
            <button type="button" class="btn" onclick="bulkAjaxRecalc('ASC')">AJAX Recalc ASC</button>
            <button type="button" class="btn" onclick="bulkAjaxRecalc('DESC')">AJAX Recalc DESC</button>
        </div>
    </form>

    <div style="margin-top:10px; color:var(--muted); font-size:0.9rem;">
        Tip: Use the AJAX buttons to quickly run for the currently selected entity without reloading.
    </div>
</div>


<script src="/js/toast.js"></script> <!-- keep this if not already included -->

<script>
async function reorderEntity(entity, direction = 'DESC', keepNonZero = false) {
    try {
        const formData = new FormData();
        formData.append('entity', entity);
        formData.append('direction', direction);
        if (keepNonZero) formData.append('keepNonZero', '1');

        const response = await fetch('/order_recalc.php', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const result = await response.json();

        if (result.success) {
            if (typeof Toast !== 'undefined' && Toast && typeof Toast.show === 'function') {
                Toast.show(result.message, 'success');
            } else {
                alert(result.message);
            }
        } else {
            const msg = 'Error: ' + (result.message || 'unknown');
            if (typeof Toast !== 'undefined' && Toast && typeof Toast.show === 'function') {
                Toast.show(msg, 'error');
            } else {
                alert(msg);
            }
        }
    } catch (err) {
        console.error(err);
        if (typeof Toast !== 'undefined' && Toast && typeof Toast.show === 'function') {
            Toast.show('AJAX request failed', 'error');
        } else {
            alert('AJAX request failed');
        }
    }
}

function bulkAjaxRecalc(dir) {
    const sel = document.getElementById('entity').value;
    const keep = document.getElementById('keepNonZero').checked;
    if (!sel) {
        if (typeof Toast !== 'undefined' && Toast && typeof Toast.show === 'function') {
            Toast.show('Select an entity first', 'warning');
        } else {
            alert('Select an entity first');
        }
        return;
    }
    reorderEntity(sel, dir, keep);
}

</script>
<?php echo $eruda; ?>
</body>
</html>
