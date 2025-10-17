<?php
// public/babylon_view.php
require __DIR__ . '/bootstrap.php'; // adjust path if needed

// Ensure autoload can find App\View\BabylonViewer
use App\View\BabylonViewer;

// PROJECT_ROOT is loaded by your bootstrap/load_root
$viewer = new BabylonViewer(PROJECT_ROOT, '/public/models', '/');

echo $eruda;

$viewer->render();
