<?php

/**
 * Renders the HTML for a single post using the appropriate template.
 *
 * @param array $post The post data from the database.
 * @param bool $forStaticExport If true, sets links to '.html' files for static site compatibility.
 * @return string The rendered HTML.
 */
function renderPostHtml(array $post, bool $forStaticExport = false): string {
    // Determine the correct "Back to Grid" link based on context
    $backLink = $forStaticExport ? 'index.html' : './';

    // Construct the path to the template based on the post's type
    $templatePath = PROJECT_ROOT . '/templates/post_detail_' . $post['post_type'] . '.html';

    if (!file_exists($templatePath)) {
        return "Error: Template for post type '{$post['post_type']}' not found at {$templatePath}.";
    }

    $template = file_get_contents($templatePath);

    // Prepare the placeholders and their corresponding values
    $replacements = [
        '{{POST_TITLE}}' => htmlspecialchars($post['title']),
        '{{POST_CONTENT}}' => $post['content'], 
        '{{BACK_TO_GRID_URL}}' => $backLink
    ];

    // Handle the media items (the JSON data) based on the post type
    switch ($post['post_type']) {
        case 'image_grid':
        case 'image_swiper':
        case 'video_playlist':
            // These templates directly consume the JSON string
            $replacements['{{MEDIA_ITEMS_JSON}}'] = $post['media_items'];
            break;
            
        case 'youtube_playlist':
            // This template needs a single URL extracted from the JSON
            $media = json_decode($post['media_items'], true);
            $embedUrl = $media[0]['url'] ?? ''; 
            $replacements['{{YOUTUBE_EMBED_URL}}'] = htmlspecialchars($embedUrl);
            break;

        case 'url_reference':
            // Extract the target URL for the redirect template
            $media = json_decode($post['media_items'], true);
            // Support both array structure [{"url":...}] or object structure {"url":...}
            if (isset($media[0]['url'])) {
                $url = $media[0]['url'];
            } elseif (isset($media['url'])) {
                $url = $media['url'];
            } else {
                $url = '#';
            }
            $replacements['{{TARGET_URL}}'] = htmlspecialchars($url);
            break;
    }
    
    // Find all placeholders in the template and replace them with the actual data
    return str_replace(array_keys($replacements), array_values($replacements), $template);
}
