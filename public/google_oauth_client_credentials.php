<?php require_once __DIR__ . '/bootstrap.php'; require __DIR__ . '/env_locals.php';

// google_oauth_client_credentials.php

$jsonPath = PROJECT_ROOT . '/token/client_secret_google_oauth.json';

// Verify file exists
if (!file_exists($jsonPath)) {
    die('Google OAuth client secret JSON not found at: ' . htmlspecialchars($jsonPath));
}

// Load and decode JSON
$jsonData = json_decode(file_get_contents($jsonPath), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    die('Invalid JSON in Google client secret file: ' . json_last_error_msg());
}

// Return credentials array
return $jsonData['web'] ?? $jsonData;
