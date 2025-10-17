<?php
require "error_reporting.php";
require __DIR__ . '/../vendor/autoload.php';
require "eruda_var.php";

$page_id = isset($_GET['page_id']) ? (int)$_GET['page_id'] : 0;
$content_element_id = isset($_GET['content_element_id']) ? (int)$_GET['content_element_id'] : 0;

if (!$page_id) {
    echo "Error: missing page_id";
    exit;
}

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// Load content
if ($content_element_id) {
    // Single content element
    $stmt = $pdo->prepare("SELECT html FROM content_elements WHERE id = :id AND page_id = :page_id");
    $stmt->bindValue(':id', $content_element_id, PDO::PARAM_INT);
    $stmt->bindValue(':page_id', $page_id, PDO::PARAM_INT);
    $stmt->execute();
    $htmlContents = $stmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    // All content elements for the page
    $stmt = $pdo->prepare("SELECT html FROM content_elements WHERE page_id = :page_id ORDER BY id ASC");
    $stmt->bindValue(':page_id', $page_id, PDO::PARAM_INT);
    $stmt->execute();
    $htmlContents = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SAGE • STARLIGHT GUARDIANS</title>


<link rel="stylesheet" href="gallery.css">

<style>
body { font-family: Arial, sans-serif; margin:0; padding:0; background:#f9f9f9; }
.editor-grid { display: flex; gap: 5px; }
.editor-grid > div { flex: 1; }
.editor-grid img { max-width: 100%; height: auto; }
</style>

</head>
<body>

<div class="album-container">
<div class="album grid">

<?php
foreach ($htmlContents as $html) {
    echo $html;
}
?>

</div>
</div>


<?php echo \App\Core\SpwBase::getInstance()->getJquery(); ?>

<?php echo $eruda; ?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- Venobox CSS via CDN -->
    <link href="https://cdn.jsdelivr.net/npm/venobox@2.1.8/dist/venobox.min.css" rel="stylesheet">
<?php else: ?>
    <!-- Venobox CSS via local copy -->
    <link href="/vendor/venobox/venobox.min.css" rel="stylesheet">
<?php endif; ?>

<?php if (\App\Core\SpwBase::CDN_USAGE): ?>
    <!-- Venobox JS via CDN -->
    <script src="https://cdn.jsdelivr.net/npm/venobox@2.1.8/dist/venobox.min.js"></script>
<?php else: ?>
    <!-- Venobox JS via local copy -->
    <script src="/vendor/venobox/venobox.min.js"></script>
<?php endif; ?>



<script>                                                $(document).ready(function(){
    try { $('.venobox').venobox(); } catch(e){}
});
</script>

</body>
</html>
