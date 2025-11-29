<?php
require_once __DIR__ . '/bootstrap.php';
require      __DIR__ . '/env_locals.php';

require_once PROJECT_ROOT . '/src/Dictionary/DictionaryManager.php';

use App\Dictionary\DictionaryManager;

$dictManager = new DictionaryManager($pdo);
$notification = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'save') {
        $dictManager->createDictionary($_POST);
        $_SESSION['notification'] = "Dictionary '{$_POST['title']}' created successfully!";
        header('Location: dictionaries_admin.php');
        exit;
    }

    if ($_POST['action'] === 'update' && isset($_POST['id'])) {
        $dictManager->updateDictionary($_POST['id'], $_POST);
        $_SESSION['notification'] = "Dictionary '{$_POST['title']}' updated successfully!";
        header('Location: dictionaries_admin.php');
        exit;
    }

    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $dictManager->deleteDictionary($_POST['id']);
        $_SESSION['notification'] = 'Dictionary deleted successfully!';
        header('Location: dictionaries_admin.php');
        exit;
    }
}

if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    unset($_SESSION['notification']);
}

$dictionaries = $dictManager->getAllDictionaries();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dictionary Admin</title>
    <script>
    (function() {
        try {
            var theme = localStorage.getItem('spw_theme');
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else if (theme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        } catch (e) {}
    })();
    </script>
    <link rel="stylesheet" href="/css/base.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Dictionary Admin</h1>
            <div class="actions-group">
                <a href="dictionary_form.php" class="btn btn-primary">Create New Dictionary</a>
                <a href="lemma_viewer.php" class="btn btn-secondary">View All Lemmas</a>
            </div>
        </div>

        <?php if ($notification): ?>
            <div class="notification"><?php echo htmlspecialchars($notification); ?></div>
        <?php endif; ?>

        <?php if (empty($dictionaries)): ?>
            <div class="empty-state">
                <p>No dictionaries found.</p>
                <a href="dictionary_form.php" class="btn btn-primary">Create Your First Dictionary</a>
            </div>
        <?php else: ?>
            <table class="posts-table posts-table--responsive">
                <thead>
                    <tr>
                        <th>Title / Slug</th>
                        <th>Source</th>
                        <th>Language</th>
                        <th>Lemmas</th>
                        <th>Order</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dictionaries as $dict): ?>
                        <tr>
                            <td data-label="Title">
                                <div class="post-title"><?php echo htmlspecialchars($dict['title']); ?></div>
                                <div class="post-slug"><?php echo htmlspecialchars($dict['slug']); ?></div>
                            </td>
                            <td data-label="Source">
                                <?php if ($dict['source_author']): ?>
                                    <strong><?php echo htmlspecialchars($dict['source_author']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($dict['source_title'] ?? ''); ?></small>
                                <?php else: ?>
                                    <span style="opacity: 0.5;">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Language">
                                <span class="post-type-badge"><?php echo strtoupper($dict['language_code']); ?></span>
                            </td>
                            <td data-label="Lemmas" style="font-weight: bold;">
                                <?php echo number_format($dict['actual_lemma_count']); ?>
                            </td>
                            <td data-label="Order" style="font-weight: bold;">
                                <?php echo (int)$dict['sort_order']; ?>
                            </td>
                            <td data-label="Updated">
                                <?php echo date('M j, Y H:i', strtotime($dict['updated_at'])); ?>
                            </td>
                            <td data-label="Actions" class="action-cell">
                                <div class="flex-gap">
                                    <a href="dictionary_form.php?id=<?php echo $dict['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                    <a href="dictionary_parse.php?id=<?php echo $dict['id']; ?>" class="btn btn-sm btn-primary">Parse</a>
                                    <a href="lemma_viewer.php?dict_id=<?php echo $dict['id']; ?>" class="btn btn-sm btn-secondary">View Lemmas</a>
                                    <form action="dictionaries_admin.php" method="post" onsubmit="return confirm('Are you sure? This will delete all associated lemma mappings.');" style="display:inline-block; margin:0;">
                                        <input type="hidden" name="id" value="<?php echo $dict['id']; ?>">
                                        <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>
</body>
</html>
