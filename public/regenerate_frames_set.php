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
    $mode = $_POST['mode'] ?? 'default'; // 'default' or 'clear'  
    $entity = $_POST['entity'] ?? '';  
  
    if (!in_array($entity, $entitiesList, true)) {  
        $updateResults = "<p class='error'>Invalid entity selected.</p>";  
    } else {  
        // Determine column name based on entity type
        $columnName = ($entity === 'animatics') ? 'regenerate_videos' : 'regenerate_images';
        
        if ($mode === 'clear') {  
            // Clear mode: set regenerate column = 0 for all rows in selected entity (only rows that are != 0)  
            $sql = "UPDATE {$entity} SET {$columnName} = 0 WHERE {$columnName} != 0";  
            if ($mysqli->query($sql) !== false) {  
                $affected = $mysqli->affected_rows;  
                $updateResults = "<p class='success'>Cleared {$columnName} (set =0) for $affected rows in '{$entity}'.</p>";  
            } else {  
                $updateResults = "<p class='error'>Failed to clear '{$entity}': " . htmlspecialchars($mysqli->error) . "</p>";  
            }  
        } else {  
            // Default mode: set regenerate column = 1 for a slice (existing behaviour)  
            $startId = isset($_POST['start_id']) ? (int)$_POST['start_id'] : 0;  
            $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 1;  
  
            // Prepare statement - table name cannot be parameterized, so we've already validated it above  
            $stmt = $mysqli->prepare("UPDATE {$entity}   
                SET {$columnName} = 1   
                WHERE id >= ?   
                ORDER BY id ASC   
                LIMIT ?");  
            if ($stmt) {  
                $stmt->bind_param('ii', $startId, $limit);  
  
                if ($stmt->execute()) {  
                    $affected = $stmt->affected_rows;  
                    $updateResults = "<p class='success'>Set {$columnName}=1 for $affected rows in '{$entity}'.</p>";  
                } else {  
                    $updateResults = "<p class='error'>Failed to update '{$entity}': " . htmlspecialchars($mysqli->error) . "</p>";  
                }  
                $stmt->close();  
            } else {  
                $updateResults = "<p class='error'>Failed to prepare statement for '{$entity}': " . htmlspecialchars($mysqli->error) . "</p>";  
            }  
        }  
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
.field-row { margin-bottom: 12px; }  
.mode-label { font-weight: 600; margin-right: 8px; }  
.mode-select { display: inline-block; min-width: 200px; }  
.small-note { font-size: 0.9em; color: var(--muted); margin-top: 6px; }  
</style>  
<?php  
echo $spw->getJquery();  
?>  
<script>  
document.addEventListener('DOMContentLoaded', function () {  
    var modeSelect = document.getElementById('mode_select');  
    var startRow = document.getElementById('row_start_id');  
    var limitRow = document.getElementById('row_limit');  
    var submitButton = document.getElementById('submit_button');  
    var modeInput = document.getElementById('mode_input');  
    var form = document.querySelector('form');  
    var entitySelect = document.getElementById('entity');
    var modeNote = document.getElementById('mode_note');
  
    function updateLabelsForEntity() {
        var entity = entitySelect.value;
        var columnName = (entity === 'animatics') ? 'regenerate_videos' : 'regenerate_images';
        var mode = modeSelect.value;
        
        // Update mode select options
        var defaultOption = modeSelect.options[0];
        var clearOption = modeSelect.options[1];
        defaultOption.text = 'Default — set ' + columnName + ' = 1 (existing behaviour)';
        clearOption.text = 'Clear — set ' + columnName + ' = 0 (only entity select will be used)';
        
        // Update submit button text
        if (mode === 'clear') {
            submitButton.textContent = 'Clear Flags';
        } else {
            submitButton.textContent = (entity === 'animatics') ? 'Regenerate Videos' : 'Regenerate Images';
        }
    }

    function applyMode(mode) {  
        modeInput.value = mode;  
        if (mode === 'clear') {  
            // hide start/limit inputs and disable them so they don't get submitted  
            startRow.style.display = 'none';  
            limitRow.style.display = 'none';  
            document.getElementById('start_id').disabled = true;  
            document.getElementById('limit').disabled = true;  
            submitButton.textContent = 'Clear Flags';  
            submitButton.classList.remove('btn-primary');  
            submitButton.classList.add('btn-danger');  
        } else {  
            // default mode  
            startRow.style.display = '';  
            limitRow.style.display = '';  
            document.getElementById('start_id').disabled = false;  
            document.getElementById('limit').disabled = false;  
            var entity = entitySelect.value;
            submitButton.textContent = (entity === 'animatics') ? 'Regenerate Videos' : 'Regenerate Images';
            submitButton.classList.remove('btn-danger');  
            submitButton.classList.add('btn-primary');  
        }  
    }  
  
    // initialize to default  
    updateLabelsForEntity();
    applyMode(modeSelect.value || 'default');  
  
    modeSelect.addEventListener('change', function () {  
        updateLabelsForEntity();
        applyMode(modeSelect.value);  
    });  
    
    entitySelect.addEventListener('change', function () {
        updateLabelsForEntity();
    });
  
    // Confirmation when submitting in clear mode  
    form.addEventListener('submit', function (e) {  
        var entity = entitySelect.value;
        var columnName = (entity === 'animatics') ? 'regenerate_videos' : 'regenerate_images';
        
        if (modeInput.value === 'clear') {  
            var entityName = entitySelect.options[entitySelect.selectedIndex].text;  
            var msg = "Really want to clear all flags?\n\nThis will set " + columnName + " = 0 for all rows in '" + entityName + "'.";  
            if (!confirm(msg)) {  
                e.preventDefault();  
                return false;  
            }  
            // no further checks; allow submit  
        }  
        // default mode: proceed normally  
    });  
});  
</script>  
</head>  
<body>  
<div style="display: flex; align-items: center; margin: 20px 20px 0 20px; gap: 10px;">  
    <a href="/dashboard.php" title="Dashboard" style="text-decoration: none; font-size: 24px; display:none;">&#x1F5C3;</a>  
    <h2 style="margin: 0; margin-left:35px;">Regenerate Images</h2>  
</div>  
<div style="margin: 20px;">  
<div class="card">  
    <form method="post" novalidate>  

        <div class="field-row">  
            <span class="mode-label">Mode:</span>  
            <select id="mode_select" class="mode-select">  
                <option value="default" selected>Default — set regenerate_images = 1 (existing behaviour)</option>  
                <option value="clear">Clear — set regenerate_images = 0 (only entity select will be used)</option>  
            </select>  
            <div class="small-note" id="mode_note">Switch to <strong>Clear</strong> to clear all regenerate flags for an entity.</div>  
        </div>  

        <!-- hidden input to send the chosen mode to the server -->  
        <input type="hidden" name="mode" id="mode_input" value="default">  

        <div class="field-row">  
            <label for="entity">Entity:</label>  
            <select name="entity" id="entity" class="form-control">  
                <?php foreach ($entitiesList as $e): ?>  
                    <option value="<?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></option>  
                <?php endforeach; ?>  
            </select>  
        </div>  

        <div class="field-row" id="row_start_id">  
            <label for="start_id">Starting ID:</label>  
            <input type="number" name="start_id" id="start_id" class="form-control" value="0" min="0">  
        </div>  

        <div class="field-row" id="row_limit">  
            <label for="limit">Limit:</label>  
            <input type="number" name="limit" id="limit" class="form-control" value="1" min="1">  
        </div>  

        <div class="field-row">  
            <button type="submit" id="submit_button" class="btn btn-primary button">Regenerate Images</button>  
        </div>  

    </form>  
</div>  

<?php if ($updateResults): ?>  
    <div class="notification notification-success" style="margin-top: 20px;"><?= $updateResults ?></div>  
<?php endif; ?>

</div>  
<?php // require_once "forge_tool.php"; echo $eruda; ?>  
</body>  
</html>
