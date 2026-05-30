<?php
require_once __DIR__ . '/../bootstrap.php';
require_once PROJECT_ROOT . '/src/Posts/PostManager.php';

use App\Posts\PostManager;

$postManager = new PostManager($pdo);
$all_posts_from_db = $postManager->getAllPosts();

$grid_posts_data = [];
foreach ($all_posts_from_db as $post) {
    $item = [
        'title' => $post['title'],
        'preview' => $post['preview_image_url'],
        'type' => $post['post_type'] // Pass type to JS for styling (e.g. icons)
    ];

    // Handle URL References to link directly from the grid
    if ($post['post_type'] === 'url_reference') {
        $media = json_decode($post['media_items'], true);
        // Normalize: supports [{"url":"..."}] or {"url":"..."}
        $data = (is_array($media) && isset($media[0])) ? $media[0] : $media;
        
        $item['file'] = $data['url'] ?? '#';
        $item['target'] = $data['target'] ?? '_self';
    } else {
        // Standard posts link to the view page
        $item['file'] = 'view.php?slug=' . urlencode($post['slug']);
    }

    $grid_posts_data[] = $item;
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
