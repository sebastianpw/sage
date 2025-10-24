<?php
// login.php
require_once "bootstrap.php";            // bootstrap includes AccessManager and starts session as needed
$spw = \App\Core\SpwBase::getInstance();

$alreadyAdmin = \App\Core\SetupManager::adminExists($pdo);

if (!$alreadyAdmin) {
    header("Location: setup.php");
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
    $googleClient->setRedirectUri('http://localhost:8080/google_callback.php');
    $googleClient->addScope('email');
    $googleClient->addScope('profile');
    $googleLoginUrl = $googleClient->createAuthUrl();
}

// Render UI — Google link uses AccessManager::urlWithRedirect so redirect is preserved across OAuth
require "eruda_var.php";
ob_start();
?>
<div style="max-width:400px; margin:50px auto; font-family:sans-serif; border:1px solid #ddd; padding:25px; border-radius:10px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">

    <img src="SAGE_nuicon.jpg" style="width: 55%; display:block;  margin: 0 auto;" />
    <h2 style="text-align:center;">Login</h2>

    <form method="post" action="">
        <?= AccessManager::renderHiddenRedirectInput($defaultRedirect) ?>
        <label>Username: <input type="text" name="username" required style="width:100%; padding:8px; margin-top:5px;"></label><br><br>
        <label>Password: <input type="password" name="password" required style="width:100%; padding:8px; margin-top:5px;"></label><br><br>
        <button type="submit" style="width:100%; padding:10px; background:#4CAF50; color:white; border:none; border-radius:5px;">Login</button>
    </form>

    <p style="text-align:center; margin:15px 0; color:#888;">or</p>

    <!-- Google login button: enabled only if JSON exists -->
    <?php if ($googleLoginEnabled): ?>
        <a href="<?= htmlspecialchars(AccessManager::urlWithRedirect('/google_login.php', $defaultRedirect), ENT_QUOTES, 'UTF-8') ?>"
           style="display:flex; align-items:center; justify-content:center; text-decoration:none; border:1px solid #ddd; padding:10px; border-radius:5px; color:#444; font-weight:bold; background:#fff;">
            <i class="fab fa-google" style="margin-right:10px; font-size:18px;"></i> Sign in with Google
        </a>
    <?php else: ?>
        <button disabled style="display:flex; align-items:center; justify-content:center; text-decoration:none; border:1px solid #ddd; padding:10px; border-radius:5px; color:#aaa; font-weight:bold; background:#f9f9f9; cursor:not-allowed;">
            <i class="fab fa-google" style="margin-right:10px; font-size:18px;"></i> Google login unavailable
        </button>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <p style="color:red; margin-top:15px;"><?= htmlspecialchars($message) ?></p>
    <?php endif; ?>
</div>
<?php
$content = ob_get_clean();
$content .= $eruda;
$spw->renderLayout($content, "User Login");
