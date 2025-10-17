<?php 
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$userId = $_SESSION['user_id'];

require __DIR__ . '/../vendor/autoload.php';

use App\Core\ChatUiAjax;

// Instantiate the AJAX handler
$ajax = new ChatUiAjax($userId);

// Pass all POST parameters to the handler
$response = $ajax->handle($_POST);

// Return JSON
header('Content-Type: application/json');
echo json_encode($response);
exit;


