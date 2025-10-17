<?php

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Test View";
$content = "";

ob_start(); 
?>

<div class="view-container">
<?php /* view specific content goes here */ ?>
</div>

<?php          
require "floatool.php";
$content = ob_get_clean();
$content .= $eruda;
$spw->renderLayout($content, $pageTitle);

