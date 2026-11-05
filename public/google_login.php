<?php
// google_login.php
require_once 'bootstrap.php'; // boots AccessManager, session, etc.

// Preserve the requested GET redirect into session before leaving for Google
AccessManager::storePendingRedirectFromGet('/dashboard.php');

// Load credentials
$creds = include __DIR__ . '/google_oauth_client_credentials.php';
$clientId = $creds['client_id'];
$redirectUri = $creds['redirect_uris'][0];

// scope + params
$scope = 'openid email profile';

// Append Drive scope if requested for the gdriver tool
// Reverted to drive.metadata.readonly to match your Google Cloud Console config
if (!empty($_GET['drive'])) {
    $scope .= ' https://www.googleapis.com/auth/drive.metadata.readonly';
}

// create a random state for CSRF protection and store it in session
$state = bin2hex(random_bytes(8));
$_SESSION['oauth2state'] = $state;

// Build the Google authorization URL with stronger prompt flags
$params = [
    'response_type' => 'code',
    'client_id'     => $clientId,
    'redirect_uri'  => $redirectUri,
    'scope'         => $scope,
    'access_type'   => 'offline',
    'include_granted_scopes' => 'false',
    'prompt'        => 'select_account consent',
    'state'         => $state,
];

$authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

// For debugging: write the URL to a temp log file (optional)
//@file_put_contents(__DIR__ . '/google_debug_url.log', date('c') . " -> $authUrl\n", FILE_APPEND);

// Redirect to Google
header('Location: ' . $authUrl);
exit;