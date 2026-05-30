<?php
// sketches2animatics.php

require __DIR__ . '/bootstrap.php';

$spw = \App\Core\SpwBase::getInstance();
$mysqli = $spw->getMysqli();

// Configuration
$defaultPrefix = "((cinematic anime)), ((anime - moody neo-noir, cel-shaded, dramatic lighting; mature psychological tone, hand-drawn linework, expressive faces, widescreen composition, high-detail environments))";

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prefixText = $_POST['prefix_text'] ?? '';
    $fromId = isset($_POST['from_id']) ? (int)$_POST['from_id'] : 0;
    $toId = isset($_POST['to_id']) ? (int)$_POST['to_id'] : 0;
    
    // Checkbox: if present in POST, it's checked (1), otherwise 0
    $regenVideos = isset($_POST['regenerate_videos']) ? 1 : 0;

    if ($fromId <= 0 || $toId < $fromId) {
        $message = "<p class='error'>Invalid ID range provided.</p>";
    } else {
        // 1. Select rows from sketches
        $sqlSelect = "SELECT name, description FROM sketches WHERE id BETWEEN ? AND ?";
        $stmtSelect = $mysqli->prepare($sqlSelect);

        if ($stmtSelect) {
            $stmtSelect->bind_param("ii", $fromId, $toId);
            $stmtSelect->execute();
            $result = $stmtSelect->get_result();

            $importedCount = 0;
            $errorCount = 0;

            // Prepare Insert Statement for Animatics
            $sqlInsert = "INSERT INTO animatics (name, description, regenerate_videos) VALUES (?, ?, ?)";
            $stmtInsert = $mysqli->prepare($sqlInsert);

            if ($stmtInsert) {
                while ($row = $result->fetch_assoc()) {
                    $originalName = $row['name'];
                    $originalDesc = $row['description'] ?? '';

                    // Construct new description
                    $finalDesc = $originalDesc;
                    if (!empty($prefixText)) {
                        // Append original description to prefix with a space/newline separator
                        $finalDesc = trim($prefixText) . " " . $originalDesc;
                    }

                    // Execute Insert
                    $stmtInsert->bind_param("ssi", $originalName, $finalDesc, $regenVideos);
                    
                    if ($stmtInsert->execute()) {
                        $importedCount++;
                    } else {
                        // Usually fails if name is unique, though animatics table def didn't strictly show a unique key
                        // we capture errors regardless.
                        $errorCount++;
                    }
                }
                $stmtInsert->close();

                $message = "<p class='success'>Batch Complete. Imported: <strong>$importedCount</strong> items.</p>";
                if ($errorCount > 0) {
                    $message .= "<p class='error'>Failed to import $errorCount items (possible duplicate names).</p>";
                }

            } else {
                $message = "<p class='error'>Failed to prepare INSERT statement: " . htmlspecialchars($mysqli->error) . "</p>";
            }
            $stmtSelect->close();
        } else {
            $message = "<p class='error'>Failed to prepare SELECT statement: " . htmlspecialchars($mysqli->error) . "</p>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Import Sketches to Animatics</title>

<script>
  (function() {
    try {
      var theme = localStorage.getItem('spw_theme');
      if (theme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
      } else if (theme === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
      }
    } catch (e) {}
  })();
</script>

<script src="/js/theme-manager.js"></script>
<link rel="stylesheet" href="/css/base.css">

<style>
.success { color: var(--success); font-weight: 600; }
.error { color: var(--error); font-weight: 600; }
.card { padding: 20px; border-radius: var(--radius); background: var(--card); box-shadow: var(--shadow-sm); }
.field-row { margin-bottom: 15px; }
.field-row label { display: block; margin-bottom: 5px; font-weight: 600; }
textarea.form-control { width: 100%; height: 120px; resize: vertical; font-family: monospace; font-size: 0.9em; }
.help-text { font-size: 0.85em; color: var(--muted); margin-top: 4px; }
.flex-row { display: flex; gap: 20px; }
.flex-item { flex: 1; }
</style>

<?php echo $spw->getJquery(); ?>

</head>
<body>

<div style="display: flex; align-items: center; margin: 20px 20px 0 20px; gap: 10px;">
    <a href="/dashboard.php" title="Dashboard" style="text-decoration: none; font-size: 24px; display:none;">&#x1F5C3;</a>
    <h2 style="margin: 0; margin-left:35px;">Import Sketches to Animatics</h2>
</div>

<div style="margin: 20px;">

    <div class="card">
        <form method="post">
            
            <!-- Description Prefix -->
            <div class="field-row">
                <label for="prefix_text">Prefix Description Text:</label>
                <textarea name="prefix_text" id="prefix_text" class="form-control"><?= htmlspecialchars($defaultPrefix) ?></textarea>
                <div class="help-text">This text will be prepended to the original description. Leave empty to use only the original description.</div>
            </div>

            <!-- ID Range -->
            <div class="flex-row">
                <div class="field-row flex-item">
                    <label for="from_id">From Sketch ID:</label>
                    <input type="number" name="from_id" id="from_id" class="form-control" placeholder="100" required min="1">
                </div>
                <div class="field-row flex-item">
                    <label for="to_id">To Sketch ID:</label>
                    <input type="number" name="to_id" id="to_id" class="form-control" placeholder="150" required min="1">
                </div>
            </div>

            <!-- Options -->
            <div class="field-row">
                <label style="display: inline-flex; align-items: center; cursor: pointer;">
                    <input type="checkbox" name="regenerate_videos" value="1" checked style="margin-right: 10px;">
                    Set 'regenerate_videos' to 1 (Generate immediately)
                </label>
            </div>

            <div class="field-row">
                <button type="submit" class="btn btn-primary button">Import Rows</button>
            </div>

        </form>
    </div>

    <?php if ($message): ?>
        <div class="notification" style="margin-top: 20px;">
            <?= $message ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
