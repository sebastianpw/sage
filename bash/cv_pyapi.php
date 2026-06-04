<?php
/**
 * Test script for PyApiCVService
 * Usage: php test_cv.php path/to/image.jpg "Your prompt here"
 */

// Adjust path to bootstrap as needed based on your file structure
require_once __DIR__ . '/../public/bootstrap.php';

use App\Core\PyApiCVService;

// 1. Parse Arguments
$imagePath = $argv[1] ?? null;
$prompt = $argv[2] ?? "Describe this image in detail";
$model = $argv[3] ?? "claude-large";

if (!$imagePath || !file_exists($imagePath)) {
    echo "Usage: php test_cv.php <path_to_image> [prompt] [model]\n";
    exit(1);
}

echo "--- PyAPI CV Test ---\n";
echo "Image:  $imagePath\n";
echo "Prompt: $prompt\n";
echo "Model:  $model\n";
echo "---------------------\n";

try {
    // 2. Instantiate Service
    $cv = new PyApiCVService();
    
    // 3. Call Analyze
    echo "Sending request to PyAPI...\n";
    $result = $cv->analyze($imagePath, $prompt, $model);
    
    // 4. Output Results
    echo "\n[SUCCESS]\n";
    echo "Description:\n";
    echo "---------------------\n";
    echo $result['description'] ?? "No description returned";
    echo "\n---------------------\n";
    echo "Public URL: " . ($result['image_url'] ?? 'N/A') . "\n";

} catch (Exception $e) {
    echo "\n[ERROR] " . $e->getMessage() . "\n";
    exit(1);
}
