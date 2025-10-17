<?php
require "error_reporting.php";
require "eruda_var.php";
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';


$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$pageTitle = "Profile View";
$content = "";


// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM user WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("User not found.");
}

ob_start();
?>

<div style="max-width: 500px; margin: 40px auto; font-family: sans-serif; border: 1px solid #ddd; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
    <h2>Profile</h2>

    <?php if (!empty($user['google_picture_blob'])): ?>
        <div style="text-align:center; margin-bottom: 15px;">
            <img src="data:image/jpeg;base64,<?php echo base64_encode($user['google_picture_blob']); ?>" 
                 alt="Profile Picture" style="border-radius: 50%; width: 96px; height: 96px;">
        </div>
    <?php endif; ?>

    <p><strong>Name:</strong> <?php echo htmlspecialchars($user['name'] ?? $user['google_name'] ?? ''); ?></p>
    <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
    <p><strong>Email:</strong> <?php echo htmlspecialchars($user['google_email'] ?? $user['email'] ?? ''); ?></p>
    <p><strong>Role:</strong> <?php echo htmlspecialchars($user['role']); ?></p>

    <?php if (!empty($user['description'])): ?>
        <p><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($user['description'])); ?></p>
    <?php endif; ?>

    <p>
        <a href="/dashboard.php">Back to Dashboard</a> |
        <a href="/logout.php">Logout</a>
    </p>
</div>

<?php
require "floatool.php";
$content = ob_get_clean();
$content .= $eruda;
$spw->renderLayout($content, $pageTitle);
