<?php
require_once __DIR__ . '/bootstrap.php';

use App\Core\SpwBase;
use App\Gallery\FrameDetails;

$spw = SpwBase::getInstance();
$mysqli = $spw->getMysqli();

$frameId = isset($_GET['frame_id']) ? (int)$_GET['frame_id'] : 0;

if (!$frameId) {
    http_response_code(400); // Bad Request
    echo "Frame ID is required.";
    exit;
}

$frameDetails = new FrameDetails($mysqli);
if ($frameDetails->load($frameId)) {
    // Successfully loaded, now render the content part
    echo $frameDetails->renderContent();
} else {
    // Frame not found or other error
    http_response_code(404); // Not Found
    echo htmlspecialchars($frameDetails->error ?: "Frame not found.");
    exit;
}

