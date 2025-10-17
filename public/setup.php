<?php
namespace App\Core;

date_default_timezone_set('UTC');

if (!defined('PUBLIC_PATH_REL')) {
    define('PUBLIC_PATH_REL', '/public');
}

require "error_reporting.php";
require "load_root.php"; // PROJECT_ROOT
require PROJECT_ROOT . '/vendor/autoload.php';
require "eruda_var.php";

use App\Core\SetupManager;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

// DEV_MODE env var to allow rerun for development/testing
$devMode = false;

$alreadyAdmin = SetupManager::adminExists($pdo);

$errors = [];
$success = false;

// -------------------- Handle POST actions --------------------

// 1) Manual create admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_manual') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if ($username === '') $errors[] = "Username required";
    if ($password === '') $errors[] = "Password required";
    if ($password !== $password2) $errors[] = "Passwords do not match";
    if (empty($errors) && SetupManager::usernameExists($pdo, $username)) {
        $errors[] = "Username already exists";
    }

    if (empty($errors)) {
        $created = SetupManager::createAdmin($pdo, $username, $password, $name, $email);
        if ($created) {
            $success = true;
            header('Location: /login.php?setup=1');
            exit;
        } else {
            $errors[] = "Failed to create user (DB error)";
        }
    }
}

// 2) Promote current session user to admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'promote_current') {
    $sessionUser = SetupManager::getSessionUser($pdo);
    if (!$sessionUser) {
        $errors[] = "No authenticated user in session. Please sign in with Google first.";
    } else {
        if ($alreadyAdmin) {
            $errors[] = "An admin already exists. Promotion not allowed.";
        } else {
            if (empty($sessionUser['google_id']) && !$devMode) {
                $errors[] = "Promotion only allowed for users created via Google sign-in.";
            } else {
                if (SetupManager::promoteToAdmin($pdo, (int)$sessionUser['id'])) {
                    $_SESSION['role'] = 'admin';
                    header('Location: /dashboard.php');
                    exit;
                } else {
                    $errors[] = "Database error promoting user to admin.";
                }
            }
        }
    }
}

// -------------------- Google redirect handling --------------------

// Always set a session redirect if not already set
if (!isset($_SESSION['redirect_after_google'])) {
    $_SESSION['redirect_after_google'] = '/setup.php';
}

// If admin exists and not devMode -> redirect to login
if ($alreadyAdmin && !$devMode) {
    header('Location: /login.php');
    exit;
}

// -------------------- Render UI --------------------
$pageTitle = "Initial Setup - Create Admin";
ob_start();

$sessionUser = SetupManager::getSessionUser($pdo);
?>
<div style="max-width:400px; margin:50px auto; font-family:sans-serif; border:1px solid #ddd; padding:25px; border-radius:10px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
    <h2 style="text-align:center;">Initial Setup</h2>

    <?php if ($alreadyAdmin): ?>
        <p style="color:#666; font-size:0.9rem; text-align:center;">An admin account already exists. DEV_MODE is enabled so you may still create another admin.</p>
    <?php else: ?>
        <p style="color:#666; font-size:0.95rem; text-align:center;">Create the first admin account or sign in with Google. After signing in with Google you can promote that account to admin from here.</p>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div style="color:#a00; margin-bottom:12px;">
            <ul style="margin:0; padding-left:18px;"><?php foreach ($errors as $e) echo "<li>" . htmlspecialchars($e) . "</li>"; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($sessionUser && !$alreadyAdmin): ?>
        <div style="margin-bottom:12px; padding:10px; border:1px solid #eee; border-radius:6px;">
            <p style="margin:0 0 8px 0;"><strong>Signed in:</strong> <?= htmlspecialchars($sessionUser['username'] ?? 'n/a') ?></p>
            <p style="margin:0 0 8px 0; color:#666;">
                <?php if (!empty($sessionUser['google_id'])): ?>
                    Signed in through Google (<?= htmlspecialchars($sessionUser['google_email'] ?? '') ?>)
                <?php else: ?>
                    Local account
                <?php endif; ?>
            </p>
            <form method="post">
                <input type="hidden" name="action" value="promote_current">
                <button type="submit" style="width:100%; padding:10px; background:#4CAF50; color:white; border:none; border-radius:5px;">Promote this account to admin</button>
            </form>
        </div>
        <p style="text-align:center;"><a href="/logout.php">Sign out and use a different account</a></p>
    <?php endif; ?>

    <?php if (!$sessionUser): ?>

<?php
// --- Determine if Google login is available ---
$googleJsonPath = PROJECT_ROOT . '/token/client_secret_google_oauth.json';
$googleLoginEnabled = file_exists($googleJsonPath) && is_readable($googleJsonPath);
$googleLoginUrl = '';
?>

	<div style="margin-bottom:12px; text-align:center;">

    <!-- Google login button: enabled only if JSON exists -->
    <?php if ($googleLoginEnabled): ?>
        <p style="color:#666;">Sign in with Google first and return here to promote that account.</p>
        <a href="/google_login.php?redirect=<?= urlencode($_SESSION['redirect_after_google']) ?>"
               style="display:flex; align-items:center; justify-content:center; text-decoration:none; border:1px solid #ddd; padding:10px; border-radius:5px; color:#444; font-weight:bold; background:#fff;">
            <i class="fab fa-google" style="margin-right:10px; font-size:18px;"></i> Sign in with Google
        </a>
    <?php else: ?>
        <button disabled style="display:flex; align-items:center; justify-content:center; text-decoration:none; border:1px solid #ddd; padding:10px; border-radius:5px; color:#aaa; font-weight:bold; background:#f9f9f9; cursor:not-allowed;">
            <i class="fab fa-google" style="margin-right:10px; font-size:18px;"></i> Google login unavailable
        </button>
    <?php endif; ?>



        </div>
    <?php endif; ?>

    <?php if (!$sessionUser || $devMode): ?>
        <form method="post">
            <input type="hidden" name="action" value="create_manual">
            <label>Username:
                <input name="username" required style="width:100%; padding:8px; margin-top:5px;" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
            </label><br><br>
            <label>Password:
                <input name="password" type="password" required style="width:100%; padding:8px; margin-top:5px;">
            </label><br><br>
            <label>Confirm Password:
                <input name="password2" type="password" required style="width:100%; padding:8px; margin-top:5px;">
            </label><br><br>
            <label>Full name (optional):
                <input name="name" style="width:100%; padding:8px; margin-top:5px;" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </label><br><br>
            <label>Email (optional):
                <input name="email" style="width:100%; padding:8px; margin-top:5px;" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </label><br><br>
            <button type="submit" style="width:100%; padding:10px; background:#4CAF50; color:white; border:none; border-radius:5px;">Create admin</button>
        </form>
    <?php endif; ?>

    <p style="text-align:center; margin-top:12px;"><a href="/login.php" style="color:#666;">Back to login</a></p>
    <hr style="margin-top:16px;">
    <p style="font-size:0.9rem;color:#666;">
        Notes: Inserts admin directly. In production set <code>DEV_MODE=false</code> or remove <code>setup.php</code> after use.
    </p>
</div>
<?php
$content = ob_get_clean();
$content .= $eruda ?? '';
$spw->renderLayout($content, $pageTitle);	
