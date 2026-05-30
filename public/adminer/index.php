<?php 
require_once __DIR__ . '/../bootstrap.php'; 
require __DIR__ . '/../env_locals.php';

error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

ob_start();
require "adminer.php";
$adminerContent = ob_get_clean();

$content = '<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script><div style="display: flex; align-items: center; margin: 20px 20px 0 20px; gap: 10px;>
    <div style="position: absolute;">
        <a href="/dashboard.php" title="Dashboard" style="text-decoration: none; font-size: 24px; display: inline-block; position: absolute; top: 10px; left: 10px; z-index: 999;">
        &#x1F5C3;
    </a>
    <h2 style="margin: 0; padding 0 0 20px 0; position: absolute; top: 10px; left: 50px;">Adminer</h2>

    </div>

</div>
<div style="margin: 0; padding: 0;"> <br />  </div>
    <div style="position: absolute; top: 50px; margin: 0 20px 80px 20px;">  ' . $adminerContent . '</div>';

$spw = \App\Core\SpwBase::getInstance();
$spw->renderLayout($content, "Adminer");





