<?php
// save_controls.php
// Receives JSON data and saves it to 'flight_controls.json'

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get raw JSON input
    $json = file_get_contents('php://input');
    
    // Validate that it is valid JSON
    $data = json_decode($json);
    if ($data === null) {
        http_response_code(400);
        echo "Invalid JSON";
        exit;
    }

    // Write to file
    $file = 'flight_controls.json';
    if (file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT))) {
        echo "Success";
    } else {
        http_response_code(500);
        echo "Error writing file. Check folder permissions.";
    }
} else {
    echo "Only POST allowed.";
}
?>