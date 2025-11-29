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


<link rel="stylesheet" href="/css/base.css">

<style>
.sage-logo-icon {
    width: 55%;
    aspect-ratio: 1 / 1;
    clip-path: inset(5% 5% 5% 5% round 18%);
    object-fit: cover;
    border-radius: 18%;
    display: block;
    margin: 0 auto 20px;
}
</style>

<script>
  (function() {
    try {
      var theme = localStorage.getItem('spw_theme');
      if (theme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
      } else if (theme === 'light') {
        document.documentElement.setAttribute('data-theme', 'light');
      }
    } catch (e) {
      // ignore
    }
  })();
</script>



<div class="container">
    <div style="text-align:center; margin-bottom:12px;">
      <img class="sage-logo-icon" src="SAGE_nuicon.jpg" alt="SAGE" style="width:55%; max-width:180px; display:inline-block;">
    </div>
  <div class="card" style="max-width:760px; margin: 32px auto;">
    <div class="card-header">Initial Setup</div>
    <div class="card-body">

      <?php if ($alreadyAdmin): ?>
        <p class="text-muted" style="margin-top:0;">
          An admin account already exists. <strong>DEV_MODE</strong> is enabled so you may still create another admin.
        </p>
      <?php else: ?>
        <p class="text-muted" style="margin-top:0;">
          Create the first admin account or sign in with Google. After signing in with Google you can promote that account to admin from here.
        </p>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="notification notification-error" role="alert">
          <ul style="margin:0; padding-left:18px;">
            <?php foreach ($errors as $e): ?>
              <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (!empty($success)): ?>
        <div class="notification notification-success" role="status">
          Admin created. Redirecting to login...
        </div>
      <?php endif; ?>

      <?php if ($sessionUser && !$alreadyAdmin): ?>
        <div class="card" style="margin-bottom:12px;">
          <div class="card-body">
            <p style="margin:0 0 8px 0;"><strong>Signed in:</strong> <?= htmlspecialchars($sessionUser['username'] ?? 'n/a') ?></p>
            <p class="text-muted" style="margin:0 0 8px 0;">
              <?php if (!empty($sessionUser['google_id'])): ?>
                Signed in through Google (<?= htmlspecialchars($sessionUser['google_email'] ?? '') ?>)
              <?php else: ?>
                Local account
              <?php endif; ?>
            </p>

            <form method="post" style="margin-top:8px;">
              <input type="hidden" name="action" value="promote_current">
              <button type="submit" class="btn btn-primary" style="width:100%;">Promote this account to admin</button>
            </form>
          </div>
        </div>

        <p style="text-align:center; margin-bottom:12px;">
          <a class="btn btn-link" href="/logout.php">Sign out and use a different account</a>
        </p>
      <?php endif; ?>

      <?php if (!$sessionUser): ?>
        <?php
        // --- Determine if Google login is available ---
        $googleJsonPath = PROJECT_ROOT . '/token/client_secret_google_oauth.json';
        $googleLoginEnabled = file_exists($googleJsonPath) && is_readable($googleJsonPath);
        ?>
        <div style="margin-bottom:12px; text-align:center;">
          <?php if ($googleLoginEnabled): ?>
            <p class="text-muted">Sign in with Google first and return here to promote that account.</p>
            <a href="/google_login.php?redirect=<?= urlencode($_SESSION['redirect_after_google']) ?>"
               class="btn btn-secondary" style="display:inline-flex; align-items:center; justify-content:center; gap:8px;">
             <i class="fab fa-google" style="margin-right:0px; font-size:18px;"></i> Sign in with Google
                        </a>
          <?php else: ?>
            <button disabled class="btn btn-secondary" style="opacity:0.6; cursor:not-allowed; display:inline-flex; align-items:center; gap:8px;">
             <i class="fab fa-google" style="margin-right:0px; font-size:18px;"></i> Google login unavailable
            </button>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (!$sessionUser || $devMode): ?>
        <form method="post" class="form-container" style="margin-top:8px;">
          <input type="hidden" name="action" value="create_manual">

          <div class="form-group">
            <label for="username">Username</label>
            <input id="username" name="username" class="form-control" required value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
          </div>

          <div class="form-row">
            <div class="form-group" style="flex:1">
              <label for="password">Password</label>
              <input id="password" name="password" type="password" class="form-control" required>
            </div>
            <div class="form-group" style="flex:1">
              <label for="password2">Confirm Password</label>
              <input id="password2" name="password2" type="password" class="form-control" required>
            </div>
          </div>

          <div class="form-group">
            <label for="name">Full name (optional)</label>
            <input id="name" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label for="email">Email (optional)</label>
            <input id="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
          </div>

          <div class="form-actions">
            <button type="submit" class="btn btn-primary">Create admin</button>
            <a href="/login.php" class="btn btn-link" style="align-self:center;">Back to login</a>
          </div>
        </form>
      <?php endif; ?>

      <hr style="margin-top:16px;">
      <p class="text-muted" style="font-size:0.95rem;">
        Notes: Inserts admin directly. In production set <code>DEV_MODE=false</code> or remove <code>setup.php</code> after use.
      </p>
    </div>
  </div>
</div>

<?php
$content = ob_get_clean();
$content .= $eruda ?? '';
$spw->renderLayout($content, $pageTitle);
