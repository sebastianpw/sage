<?php
// 1. Intercept the Pollinations token passed from the landing page
if (isset($_GET['polli_token'])) {
    $tokenDir = dirname(__DIR__) . '/token';
    if (!is_dir($tokenDir)) {
        @mkdir($tokenDir, 0700, true);
    }
    $tokenFile = $tokenDir . '/.pollinationsaitoken';
    
    // Save the token and restrict permissions
    if (@file_put_contents($tokenFile, trim($_GET['polli_token'])) !== false) {
        @chmod($tokenFile, 0600);
    }
}

// 2. Boot the app (this handles session and redirects to /setup.php if no admin exists)
require_once __DIR__ . '/bootstrap.php';

// 3. Send them to the dashboard
header('Location: /dashboard.php');
exit;
