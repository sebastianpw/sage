<?php
// login.php
require_once "bootstrap.php";            // bootstrap includes AccessManager and starts session as needed
$spw = \App\Core\SpwBase::getInstance();

$alreadyAdmin = \App\Core\SetupManager::adminExists($pdo);

if (!$alreadyAdmin) {
    header("Location: setup.php");
    exit;
}

$message = '';

// Default redirect shown in the form is rendered by AccessManager::renderHiddenRedirectInput()
// The final redirect after login comes from the posted hidden input (preserves GET-only selection)
$defaultRedirect = '/dashboard.php';

// If already logged in, send user straight away to safe redirect (use GET redirect if present)
if (\AccessManager::isAuthenticated()) {
    // prefer safe GET redirect if present
    $getRedirect = $_GET['redirect'] ?? $defaultRedirect;
    if (!preg_match('#^/[a-zA-Z0-9/_\-\.\?=&%]*$#', $getRedirect)) {
        $getRedirect = $defaultRedirect;
    }
    header("Location: {$getRedirect}");
    exit;
}

// Handle local login (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $stmt = $pdo->prepare("SELECT * FROM `user` WHERE `username` = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            // Successful login: create session exactly like old login
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'] ?? 'user';
            $_SESSION['login_type'] = 'local';

            // persist cookie (optional)
            $lifetime = 60 * 60 * 24 * 30;
            setcookie(session_name(), session_id(), time() + $lifetime, "/");

            // Redirect to the posted redirect (hidden input). Validate it conservatively.
            $postedRedirect = $_POST['redirect'] ?? $defaultRedirect;
            if (!preg_match('#^/[a-zA-Z0-9/_\-\.\?=&%]*$#', $postedRedirect)) {
                $postedRedirect = $defaultRedirect;
            }

            header("Location: {$postedRedirect}");
            exit;
        } else {
            $message = "Invalid username or password.";
        }
    } else {
        $message = "Please enter username and password.";
    }
}

// --- Determine if Google login is available ---
$googleJsonPath = PROJECT_ROOT . '/token/client_secret_google_oauth.json';
$googleLoginEnabled = file_exists($googleJsonPath) && is_readable($googleJsonPath);
$googleLoginUrl = '';
if ($googleLoginEnabled) {
    $googleClient = new Google_Client();
    $googleClient->setAuthConfig($googleJsonPath);
    // keep existing redirect; you may want to make this dynamic
    $googleClient->setRedirectUri('http://localhost:8080/google_callback.php');
    $googleClient->addScope('email');
    $googleClient->addScope('profile');
    $googleLoginUrl = $googleClient->createAuthUrl();
}

// Render UI
require "eruda_var.php";
ob_start();
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

<div class="container" style="max-width:420px; margin:60px auto; padding:0 12px;">
  <div style="padding:20px;" class="card" role="main" aria-label="Login box">
    <div style="text-align:center; margin-bottom:12px;">
      <img class="sage-logo-icon" src="SAGE_nuicon.jpg" alt="SAGE" style="width:55%; max-width:180px; display:inline-block;">
    </div>

    <h2 style="text-align:center; margin:6px 0 18px 0;">Login</h2>

    <form method="post" action="">
      <?= AccessManager::renderHiddenRedirectInput($defaultRedirect) ?>

      <div class="form-group">
        <label class="form-label" for="username">Username</label>
        <input id="username" name="username" type="text" required class="form-control" />
      </div>

      <div class="form-group" style="margin-bottom:8px;">
        <label class="form-label" for="password">Password</label>
        <input id="password" name="password" type="password" required class="form-control" />
      </div>

      <div style="margin-top:12px;">
        <button type="submit" class="btn btn-primary" style="width:100%;">Login</button>
      </div>
    </form>

    <div style="text-align:center; margin:14px 0; color:var(--muted);">or</div>

    <?php if ($googleLoginEnabled): ?>
      <a href="<?= htmlspecialchars(\AccessManager::urlWithRedirect('/google_login.php', $defaultRedirect), ENT_QUOTES, 'UTF-8') ?>"
         class="btn secondary" style="display:flex; align-items:center; justify-content:center; gap:10px; width:100%; box-sizing:border-box;">
        <i class="fab fa-google" style="margin-right:0px; font-size:18px;"></i>Sign in with Google
      </a>
    <?php else: ?>
      <button disabled class="btn secondary" title="Google login not available" style="display:flex; align-items:center; justify-content:center; gap:10px; width:100%; box-sizing:border-box; cursor:not-allowed;">
        <i class="fab fa-google" style="margin-right:0px; font-size:18px;"></i>Google login unavailable
      </button>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
      <div style="margin-top:14px;">
        <div class="notification notification-danger">
          <?= htmlspecialchars($message) ?>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php
$content = ob_get_clean();
$content .= $eruda;
$spw->renderLayout($content, "User Login");
