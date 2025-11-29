<?php
require_once __DIR__ . '/../bootstrap.php';
require_once PROJECT_ROOT . '/src/Posts/PostManager.php';

use App\Posts\PostManager;

$postManager = new PostManager($pdo);
$all_posts_from_db = $postManager->getAllPosts();

$grid_posts_data = [];
foreach ($all_posts_from_db as $post) {
    $grid_posts_data[] = [
        'title' => $post['title'],
        // This is the crucial part: we now link to our dynamic view.php
        'file' => 'view.php?slug=' . urlencode($post['slug']), 
        'preview' => $post['preview_image_url']
    ];
}

// Load the grid template
$template_html = file_get_contents(PROJECT_ROOT . '/templates/post_grid.html');

// Inject the posts data (as a JSON string) into the template
$final_html = str_replace(
    '{{POSTS_JSON}}', 
    json_encode($grid_posts_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), 
    $template_html
);

// We need to adjust the back-link path inside the JS of the detail views
// This will make sure the static exported files point to index.html, not index.php
$final_html = str_replace('/posts/index.html', '/posts/', $final_html);


// Output the final, rendered page
echo $final_html;

