<?php 
require_once __DIR__ . '/bootstrap.php'; 
require __DIR__ . '/env_locals.php';

// serve_download.php

// Collect GET params for forwarding
$pageId     = isset($_GET['page_id']) ? (int)$_GET['page_id'] : null;
$contentId  = isset($_GET['content_id']) ? (int)$_GET['content_id'] : null;

// Build query string for statichtml.php
$query = [];
if ($pageId !== null) {
    $query['page_id'] = $pageId;
}
if ($contentId !== null) {
    $query['content_id'] = $contentId;
}
$queryString = http_build_query($query);

// Start output buffering
ob_start();

// Include the PHP script that generates the HTML
include __DIR__ . '/statichtml.php';

// Get the generated HTML from the buffer
$html = ob_get_clean();

// Create a filename based on params
$filename = "static";
if ($pageId !== null) {
    $filename .= "_page{$pageId}";
}
if ($contentId !== null) {
    $filename .= "_content{$contentId}";
}
$filename .= ".html";

// Force download headers
header('Content-Type: text/html; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($html));

// Output the content
echo $html;
exit;
