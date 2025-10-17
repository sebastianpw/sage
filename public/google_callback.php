<?php
// google_callback.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php'; // bootstrap loads vendor/autoload and AccessManager, starts session
// Path to your JSON (Termux home)
$clientJson = PROJECT_ROOT . '/token/client_secret_google_oauth.json';

// quick dev helper
function dev_die($msg) {
    header('Content-Type: text/plain; charset=utf-8');
    die("ERROR: {$msg}\n");
}

// Validate state for CSRF protection
if (!isset($_GET['state']) || !isset($_SESSION['oauth2state']) || $_GET['state'] !== $_SESSION['oauth2state']) {
    // clear state and fail
    unset($_SESSION['oauth2state']);
    dev_die('Invalid OAuth state (possible CSRF).');
}

// Make sure we have a code
if (!isset($_GET['code'])) {
    dev_die('No authorization code received.');
}

// Initialize Google Client
$client = new Google_Client();
$client->setAuthConfig($clientJson);
$client->setRedirectUri('http://localhost:8080/google_callback.php');
$client->addScope('email');
$client->addScope('profile');

// Exchange code for tokens
try {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
} catch (Exception $e) {
    dev_die('Exception fetching token: ' . $e->getMessage());
}
if (isset($token['error'])) {
    dev_die('Token error: ' . ($token['error_description'] ?? $token['error']));
}
$client->setAccessToken($token);

// Get user info
try {
    $oauth = new Google_Service_Oauth2($client);
    $googleUser = $oauth->userinfo->get();
} catch (Exception $e) {
    dev_die('Failed to fetch userinfo: ' . $e->getMessage());
}

// map fields
$googleId = $googleUser->id ?? null;
$email    = $googleUser->email ?? null;
$name     = $googleUser->name ?? null;
$given    = $googleUser->givenName ?? null;
$family   = $googleUser->familyName ?? null;
$picture  = $googleUser->picture ?? null;

if (!$googleId) dev_die('Google user id missing.');

// fetch picture blob (best-effort)
$googlePicBlob = null;
if (!empty($picture)) {
    $ch = curl_init($picture);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $imgData = curl_exec($ch);
    $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);
    if ($imgData !== false && $httpStatus >= 200 && $httpStatus < 300) {
        $googlePicBlob = $imgData;
    } else {
        error_log("Warning: failed to fetch Google picture: HTTP {$httpStatus} {$curlErr}");
    }
}

// now integrate with DB (use $pdo from bootstrap.php)
try {
    // 1) try find by google_id
    $stmt = $pdo->prepare("SELECT * FROM user WHERE google_id = ?");
    $stmt->execute([$googleId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user && !empty($email)) {
        // 2) try find by google_email
        $stmt = $pdo->prepare("SELECT * FROM user WHERE google_email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if ($user) {
        // update existing user
        $stmt = $pdo->prepare("
            UPDATE user SET
                google_id = :gid,
                google_email = :gemail,
                google_name = :gname,
                google_given_name = :ggiven,
                google_family_name = :gfamily,
                google_picture = :gurl,
                google_picture_blob = :gblob,
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->bindValue(':gid', $googleId);
        $stmt->bindValue(':gemail', $email);
        $stmt->bindValue(':gname', $name);
        $stmt->bindValue(':ggiven', $given);
        $stmt->bindValue(':gfamily', $family);
        $stmt->bindValue(':gurl', $picture);
        $stmt->bindValue(':gblob', $googlePicBlob, PDO::PARAM_LOB);
        $stmt->bindValue(':id', $user['id'], PDO::PARAM_INT);
        $stmt->execute();
    } else {
        // create a new user — must satisfy NOT NULL username/password
        $base = 'user';
        if (!empty($email) && strpos($email,'@') !== false) {
            $base = preg_replace('/[^a-z0-9._-]/', '_', strtolower(strtok($email,'@')));
        } elseif (!empty($name)) {
            $base = preg_replace('/[^a-z0-9._-]/', '_', strtolower(trim($name)));
            $base = substr($base,0,30) ?: 'user';
        }
        $candidate = $base;
        $i = 0;
        while (true) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM user WHERE username = ?");
            $st->execute([$candidate]);
            $c = (int)$st->fetchColumn();
            if ($c === 0) break;
            $i++;
            $candidate = $base . ($i === 1 ? '' : $i);
            if ($i > 9999) { $candidate = $base . '_' . bin2hex(random_bytes(4)); break; }
        }
        $newUsername = $candidate;
        $randomPassword = bin2hex(random_bytes(16));
        $passwordHash = password_hash($randomPassword, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare("
            INSERT INTO user
            (username, password, name, google_name, google_given_name, google_family_name, google_email, google_id, google_picture, google_picture_blob, created_at)
            VALUES
            (:username, :password, :name, :gname, :ggiven, :gfamily, :gemail, :gid, :gurl, :gblob, NOW())
        ");
        $stmt->bindValue(':username', $newUsername);
        $stmt->bindValue(':password', $passwordHash);
        $stmt->bindValue(':name', $name);
        $stmt->bindValue(':gname', $name);
        $stmt->bindValue(':ggiven', $given);
        $stmt->bindValue(':gfamily', $family);
        $stmt->bindValue(':gemail', $email);
        $stmt->bindValue(':gid', $googleId);
        $stmt->bindValue(':gurl', $picture);
        $stmt->bindValue(':gblob', $googlePicBlob, PDO::PARAM_LOB);
        $stmt->execute();

        $newId = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
        $stmt->execute([$newId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    dev_die('Database error: '.$e->getMessage());
}

if (!$user || empty($user['id'])) dev_die('Failed to create or retrieve user record.');

// create native session exactly like old login
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['username'] = $user['username'];
$_SESSION['role'] = $user['role'] ?? 'user';
$_SESSION['login_type'] = 'google';

// persist cookie
$lifetime = 60 * 60 * 24 * 30;
setcookie(session_name(), session_id(), time() + $lifetime, "/");

// cleanup state
unset($_SESSION['oauth2state']);

// Get the original requested page (consumes the pending redirect stored earlier)
$target = AccessManager::consumePendingRedirect('/dashboard.php');

// Final redirect
header('Location: ' . $target);
exit;
