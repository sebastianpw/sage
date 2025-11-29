<?php
session_start();
require_once __DIR__ . '/../bootstrap.php';
require_once PROJECT_ROOT . '/src/Posts/PostManager.php';
require_once __DIR__ . '/post_renderer.php';

use App\Posts\PostManager;

$postManager = new PostManager($pdo);
$notification = '';

// Handle Actions (POST requests)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    // --- SAVE NEW POST ---
    if ($_POST['action'] === 'save') {
        $postManager->createPost($_POST);
        $_SESSION['notification'] = "Post '{$_POST['title']}' created successfully!";
        header('Location: posts_admin.php');
        exit;
    }

    // --- UPDATE EXISTING POST ---
    if ($_POST['action'] === 'update' && isset($_POST['id'])) {
        $postManager->updatePost($_POST['id'], $_POST);
        $_SESSION['notification'] = "Post '{$_POST['title']}' updated successfully!";
        header('Location: posts_admin.php');
        exit;
    }

    // --- DELETE POST ---
    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        $postManager->deletePost($_POST['id']);
        $_SESSION['notification'] = 'Post deleted successfully!';
        header('Location: posts_admin.php');
        exit;
    }

    // --- EXPORT SINGLE POST ---
    if ($_POST['action'] === 'export_single' && isset($_POST['id'])) {
        $post = $postManager->getPostById($_POST['id']);
        if ($post) {
            $htmlContent = renderPostHtml($post, true); // true for static export
            header('Content-Type: text/html');
            header('Content-Disposition: attachment; filename="' . $post['slug'] . '.html"');
            echo $htmlContent;
            exit;
        }
    }
    
    // --- EXPORT GRID VIEW ---
    if ($_POST['action'] === 'export_grid') {
        $all_posts = $postManager->getAllPosts();
        $grid_data = [];
        foreach ($all_posts as $p) {
            $grid_data[] = [
                'title' => $p['title'],
                'file' => $p['slug'] . '.html', // Static link
                'preview' => $p['preview_image_url']
            ];
        }
        $template = file_get_contents(PROJECT_ROOT . '/templates/post_grid.html');
        $htmlContent = str_replace('{{POSTS_JSON}}', json_encode($grid_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), $template);
        
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="index.html"');
        echo $htmlContent;
        exit;
    }
    
    // --- EXPORT ALL AS ZIP ---
    if ($_POST['action'] === 'export_zip') {
        if (!class_exists('ZipArchive')) {
            die('ZipArchive class not found. Please enable the PHP zip extension.');
        }
        
        $all_posts = $postManager->getAllPosts();
        $zip = new ZipArchive();
        $zipFileName = 'posts_export_' . date('Y-m-d') . '.zip';
        $zipFilePath = sys_get_temp_dir() . '/' . $zipFileName;

        if ($zip->open($zipFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
            die("An error occurred creating your ZIP file.");
        }

        // 1. Generate and add index.html (grid view)
        $grid_data = [];
        foreach ($all_posts as $p) {
            $grid_data[] = ['title' => $p['title'], 'file' => $p['slug'] . '.html', 'preview' => $p['preview_image_url']];
        }
        $grid_template = file_get_contents(PROJECT_ROOT . '/templates/post_grid.html');
        $grid_html = str_replace('{{POSTS_JSON}}', json_encode($grid_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), $grid_template);
        $zip->addFromString('index.html', $grid_html);

        // 2. Generate and add each individual post HTML
        foreach ($all_posts as $post) {
            $post_html = renderPostHtml($post, true); // true for static export
            $zip->addFromString($post['slug'] . '.html', $post_html);
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipFileName . '"');
        header('Content-Length: ' . filesize($zipFilePath));
        readfile($zipFilePath);
        unlink($zipFilePath); // Clean up the temp file
        exit;
    }
}

// Check for notifications from session
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    unset($_SESSION['notification']);
}

// Fetch all posts for display
$posts = $postManager->getAllPosts();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAGE Posts Admin</title>
    <script>
    (function() {
        try {
        var theme = localStorage.getItem('spw_theme');
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
        } else if (theme === 'light') {
            document.documentElement.setAttribute('data-theme', 'light');
        }
        // If no theme is set, we do nothing and let the CSS media query handle it.
        } catch (e) {
        // Fails gracefully
        }
    })();
    </script>
    <link rel="stylesheet" href="/css/base.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>SAGE Posts Admin</h1>
            <div class="actions-group">
                <a href="post_form.php" class="btn btn-primary">Create New Post</a>
                <a href="index.php" class="btn btn-secondary">View Post Grid</a>
                <form action="posts_admin.php" method="post" style="display:inline;">
                    <button type="submit" name="action" value="export_grid" class="btn btn-secondary">Export Grid HTML</button>
                </form>
                <form action="posts_admin.php" method="post" style="display:inline;">
                    <button type="submit" name="action" value="export_zip" class="btn btn-secondary">Download All as ZIP</button>
                </form>
            </div>
        </div>

        <?php if ($notification): ?>
            <div class="notification"><?php echo htmlspecialchars($notification); ?></div>
        <?php endif; ?>

        <?php if (empty($posts)): ?>
            <div class="empty-state">
                <p>No posts found.</p>
                <a href="post_form.php" class="btn btn-primary">Create Your First Post</a>
            </div>
        <?php else: ?>
            <table class="posts-table posts-table--responsive">
                <thead>
                    <tr>
                        <th>Title / Slug</th>
                        <th>Type</th>
                        <th>Preview</th>
                        <th>Order</th><!-- NEW COLUMN HEADER -->
                        <th>Last Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($posts as $post): ?>
                        <tr>
                            <td data-label="Title">
                                <div class="post-title"><?php echo htmlspecialchars($post['title']); ?></div>
                                <div class="post-slug"><?php echo htmlspecialchars($post['slug']); ?></div>
                            </td>
                            <td data-label="Type"><span class="post-type-badge"><?php echo str_replace('_', ' ', $post['post_type']); ?></span></td>
                            <td data-label="Preview"><img src="<?php echo htmlspecialchars($post['preview_image_url']); ?>" alt="Preview" style="width: 60px; height: 60px; object-fit: cover; border-radius: 4px;"></td>
                            <!-- NEW COLUMN DATA -->
                            <td data-label="Order" style="font-weight: bold;"><?php echo (int)$post['sort_order']; ?></td>
                            <td data-label="Updated"><?php echo date('M j, Y H:i', strtotime($post['updated_at'])); ?></td>
                            <td data-label="Actions" class="action-cell">
                                <div class="flex-gap">
                                    <a href="post_form.php?id=<?php echo $post['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                            
                                    <form action="posts_admin.php" method="post" style="display:inline-block; margin:0;">
                                        <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                                        <button type="submit" name="action" value="export_single" class="btn btn-sm btn-secondary">Export</button>
                                    </form>
                            
                                    <form action="posts_admin.php" method="post" onsubmit="return confirm('Are you sure you want to delete this post?');" style="display:inline-block; margin:0;">
                                        <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
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
</body>
</html>

