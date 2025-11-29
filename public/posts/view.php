<?php
require_once __DIR__ . '/../bootstrap.php';
require_once PROJECT_ROOT . '/src/Posts/PostManager.php';
require_once __DIR__ . '/post_renderer.php'; // We need our new helper file

use App\Posts\PostManager;

// Check if a 'slug' is provided in the URL (e.g., view.php?slug=sage-v0-3-swipe)
if (!isset($_GET['slug'])) {
    http_response_code(404);
    die('<h1>404 - Post Not Found</h1><p>No post specified.</p>');
}

$postManager = new PostManager($pdo);
$post = $postManager->getPostBySlug($_GET['slug']);

// Check if a post with that slug was found in the database
if (!$post) {
    http_response_code(404);
    die('<h1>404 - Post Not Found</h1><p>The requested post does not exist.</p>');
}

// Use our renderer to generate the HTML for this post
// The 'false' argument means we are rendering for the live site, not a static export
echo renderPostHtml($post, false);

