<?php
require_once __DIR__ . '/../bootstrap.php';
require_once PROJECT_ROOT . '/src/Posts/PostManager.php';

use App\Posts\PostManager;

$postManager = new PostManager($pdo);
// CHANGED: Added 'sort_order' to the default post array
$post = [
    'id' => '', 'title' => '', 'slug' => '', 'post_type' => 'image_grid',
    'preview_image_url' => '', 'content' => '', 'media_items' => '', 'sort_order' => 0
];
$pageTitle = 'Create New Post';
$formAction = 'save';

if (isset($_GET['id'])) {
    $post = $postManager->getPostById($_GET['id']);
    $pageTitle = 'Edit Post';
    $formAction = 'update';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
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
            <h1><?php echo $pageTitle; ?></h1>
            <a href="posts_admin.php">&larr; Back to Admin</a>
        </div>

        <div class="form-container">
            <form action="posts_admin.php" method="post">
                <input type="hidden" name="action" value="<?php echo $formAction; ?>">
                <?php if ($post['id']): ?>
                    <input type="hidden" name="id" value="<?php echo $post['id']; ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="title">Title</label>
                    <input type="text" id="title" name="title" class="form-control" value="<?php echo htmlspecialchars($post['title']); ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="slug">URL Slug</label>
                        <input type="text" id="slug" name="slug" class="form-control" value="<?php echo htmlspecialchars($post['slug']); ?>">
                        <small>Leave blank to auto-generate from title.</small>
                    </div>

                    <!-- NEW: Sort Order Field -->
                    <div class="form-group">
                        <label for="sort_order">Sort Order</label>
                        <input type="number" id="sort_order" name="sort_order" class="form-control" value="<?php echo (int)$post['sort_order']; ?>">
                        <small>Higher numbers appear first.</small>
                    </div>
                </div>

                <div class="form-group">
                    <label for="post_type">Post Type</label>
                    <select id="post_type" name="post_type" class="form-control">
                        <option value="image_grid" <?php echo ($post['post_type'] === 'image_grid') ? 'selected' : ''; ?>>Image Grid</option>
                        <option value="image_swiper" <?php echo ($post['post_type'] === 'image_swiper') ? 'selected' : ''; ?>>Image Swiper</option>
                        <option value="video_playlist" <?php echo ($post['post_type'] === 'video_playlist') ? 'selected' : ''; ?>>Video Playlist</option>
                        <option value="youtube_playlist" <?php echo ($post['post_type'] === 'youtube_playlist') ? 'selected' : ''; ?>>YouTube Playlist</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="preview_image_url">Preview Image URL</label>
                    <input type="text" id="preview_image_url" name="preview_image_url" class="form-control" value="<?php echo htmlspecialchars($post['preview_image_url']); ?>" required>
                    <small>Accepts relative paths (e.g., /img/preview.jpg) and full URLs.</small>
                </div>

                <div class="form-group">
                    <label for="content">Content (HTML)</label>
                    <textarea id="content" name="content" class="form-control"><?php echo htmlspecialchars($post['content']); ?></textarea>
                    <small>The main text/HTML content of the post.</small>
                </div>
                
                <div class="form-group">
                    <label for="media_items">Media Items (JSON)</label>
                    <textarea id="media_items" name="media_items" class="form-control" required><?php echo htmlspecialchars($post['media_items']); ?></textarea>
                    <small>The JSON array of images or videos for the post detail page.</small>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Post</button>
                    <a href="posts_admin.php" class="btn-link">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

