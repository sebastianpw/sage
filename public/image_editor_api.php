<?php
// public/image_editor_api.php
// Handles temporary image edits WITHOUT saving to database
// Only creates temp files that get saved to DB when user clicks "Save"

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\PyApiImageService;
use App\Core\SpwBase;

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$action = $data['action'] ?? null;
$sourceFile = $data['source_file'] ?? null; // Can be a frame filename or temp filename

if (!$action || !$sourceFile) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters: action, source_file']);
    exit;
}

try {
    $imageService = new PyApiImageService();
    $spw = SpwBase::getInstance();
    $projectRoot = $spw->getProjectPath();
    
    // Build full path to source
    $sourceFullPath = $projectRoot . '/public/' . ltrim($sourceFile, '/');
    
    if (!file_exists($sourceFullPath)) {
        throw new Exception("Source file not found: {$sourceFile}");
    }
    
    $processedImageData = null;
    $note = "Temp: {$action}";
    
    // Route actions (same as before but no DB operations)
    switch ($action) {
        case 'rotate':
            $angle = floatval($data['angle'] ?? 0);
            $processedImageData = $imageService->rotate($sourceFullPath, $angle);
            $note .= " by {$angle}°";
            break;

        case 'resize':
            $width = intval($data['width'] ?? 0);
            $height = intval($data['height'] ?? 0);
            if ($width <= 0 || $height <= 0) throw new Exception("Invalid dimensions for resize.");
            $processedImageData = $imageService->resize($sourceFullPath, $width, $height, true);
            $note .= " to {$width}x{$height}";
            break;

        case 'filter':
            $filterType = $data['filter_type'] ?? 'unknown';
            $params = $data['params'] ?? [];
            $note = "Temp Filter: {$filterType}";

            if ($filterType === 'composite') {
                $enhanceOptions = [];
                $customFilterOptions = [];

                if (isset($params['brightness'])) $enhanceOptions['brightness'] = 1 + ($params['brightness'] / 100);
                if (isset($params['contrast'])) $enhanceOptions['contrast'] = 1 + ($params['contrast'] / 100);
                if (isset($params['blur_radius'])) $customFilterOptions['blur_radius'] = floatval($params['blur_radius']);
                if (isset($params['sharpen_amount'])) $customFilterOptions['sharpen_amount'] = intval($params['sharpen_amount']);
                
                if (empty($enhanceOptions) && empty($customFilterOptions)) {
                    throw new Exception("No adjustment parameters provided.");
                }

                $currentImagePath = $sourceFullPath;
                $tempFiles = [];

                if (!empty($enhanceOptions)) {
                    $imageData = $imageService->enhance($currentImagePath, $enhanceOptions);
                    if (!$imageData) throw new Exception("Failed to apply enhancements.");
                    
                    $tempFile = tempnam(sys_get_temp_dir(), 'img-edit-');
                    file_put_contents($tempFile, $imageData);
                    $currentImagePath = $tempFile;
                    $tempFiles[] = $tempFile;
                }

                if (!empty($customFilterOptions)) {
                    $processedImageData = $imageService->applyCustomFilter($currentImagePath, $customFilterOptions);
                } else {
                    $processedImageData = file_get_contents($currentImagePath);
                }

                foreach ($tempFiles as $file) {
                    if (file_exists($file)) unlink($file);
                }

            } else {
                $presetName = $filterType === 'grayscale' ? 'noir' : $filterType;
                $processedImageData = $imageService->applyPreset($sourceFullPath, $presetName);
            }
            break;
            
        case 'crop':
            $mode = $data['mode'] ?? 'crop';
            $coords = $data['coords'] ?? null;
            if (!$coords) throw new Exception("Missing crop coordinates");
            
            // Use ImageEditTool for cropping
            require_once __DIR__ . '/ImageEditTool.php';
            $iet = new ImageEditTool();
            
            // Generate temp filename
            $tempBasename = 'temp_' . uniqid() . '_' . time();
            
            if ($mode === 'crop') {
                $derivedRel = $iet->createCroppedImage($sourceFile, $coords, $tempBasename);
            } else {
                $derivedRel = $iet->createMaskedImage($sourceFile, $coords, $tempBasename);
            }
            
            // Read the file that was created
            $derivedPath = $projectRoot . '/public/' . ltrim($derivedRel, '/');
            $processedImageData = file_get_contents($derivedPath);
            
            // Return the relative path for next operation
            echo json_encode([
                'success' => true,
                'filename' => $derivedRel,
                'message' => 'Crop applied (temporary)',
                'is_temp' => true
            ]);
            exit;

        default:
            throw new Exception("Unsupported action '{$action}'");
    }

    if (!$processedImageData) {
        throw new Exception("Python API did not return image data for action '{$action}'.");
    }

    // Save to temporary file in frames directory
    $pi = pathinfo($sourceFile);
    $extension = $pi['extension'] ?? 'png';
    
    // Create unique temp filename
    $tempBasename = 'temp_' . uniqid() . '_' . time();
    $tempFilename = $tempBasename . '.' . $extension;
    
    // Use same directory structure as source
    $dirnameRel = ($pi['dirname'] && $pi['dirname'] !== '.') ? $pi['dirname'] : '';
    //$tempRel = ($dirnameRel ? (rtrim($dirnameRel, '/') . '/') : '') . $tempFilename;
    $tempRel = 'temp/' . $tempFilename;
    $tempFull = $projectRoot . '/public/' . ltrim($tempRel, '/');
    
    // Ensure directory exists
    $tempDir = dirname($tempFull);
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    
    if (file_put_contents($tempFull, $processedImageData) === false) {
        throw new Exception("Failed to save temporary image.");
    }
    
    echo json_encode([
        'success' => true,
        'filename' => $tempRel,
        'message' => "{$action} applied (temporary)",
        'is_temp' => true
    ]);

} catch (Exception $e) {
    error_log("image_editor_temp_api.php Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
