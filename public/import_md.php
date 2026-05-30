<?php
// public/import_md.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php'; // Ensures $pdo is available

$spw = \App\Core\SpwBase::getInstance();
$pageTitle = "Import MD Files";

// Directory to scan
$docDirRel = '/docs/';
$docDirAbs = realpath($spw->getPublicPath() . $docDirRel);

ob_start();
?>

<style>
    body { background-color: #0d1117; color: #c9d1d9; font-family: sans-serif; padding: 2rem; }
    .log-box { background: #161b22; border: 1px solid #30363d; padding: 20px; border-radius: 6px; max-width: 800px; margin: 0 auto; }
    h1 { border-bottom: 1px solid #30363d; padding-bottom: 10px; }
    .success { color: #238636; }
    .skip { color: #e3b341; }
    .error { color: #f85149; }
    .item { margin-bottom: 5px; font-family: monospace; }
</style>

<div class="log-box">
    <h1>Importing Documents...</h1>
    
    <?php
    if (!$docDirAbs || !is_dir($docDirAbs)) {
        echo "<div class='error'>Error: 'docs' directory not found at: " . htmlspecialchars($spw->getPublicPath() . $docDirRel) . "</div>";
    } else {
        // 1. Get all .md files recursively
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docDirAbs, FilesystemIterator::SKIP_DOTS));
        
        $countImported = 0;
        $countSkipped = 0;

        foreach ($iterator as $fileinfo) {
            if ($fileinfo->isFile() && strtolower($fileinfo->getExtension()) === 'md') {
                
                // Get Filename (Name) and Content
                $filePath = $fileinfo->getRealPath();
                $fileName = $fileinfo->getBasename('.md'); // Use filename as title
                $content = file_get_contents($filePath);

                // Check for duplicates
                $stmt = $pdo->prepare("SELECT id FROM documentations WHERE name = ?");
                $stmt->execute([$fileName]);
                $exists = $stmt->fetch();

                echo "<div class='item'>";
                if ($exists) {
                    echo "<span class='skip'>[SKIPPED]</span> " . htmlspecialchars($fileName) . " (Already exists)";
                    $countSkipped++;
                } else {
                    try {
                        // Insert
                        $insert = $pdo->prepare("INSERT INTO documentations (name, content, category_id, is_active) VALUES (?, ?, ?, 1)");
                        // Assuming Category ID 1 exists (General)
                        $insert->execute([$fileName, $content, 1]);
                        
                        echo "<span class='success'>[IMPORTED]</span> " . htmlspecialchars($fileName);
                        $countImported++;
                    } catch (Exception $e) {
                        echo "<span class='error'>[ERROR]</span> " . htmlspecialchars($fileName) . ": " . $e->getMessage();
                    }
                }
                echo "</div>";
            }
        }
        
        echo "<hr><p><strong>Done.</strong> Imported: $countImported | Skipped: $countSkipped</p>";
        echo "<a href='view_md.php' style='color:#58a6ff'>Go to Documentation Index &rarr;</a>";
    }
    ?>
</div>

<?php
$content = ob_get_clean();
$spw->renderLayout($content, $pageTitle);
