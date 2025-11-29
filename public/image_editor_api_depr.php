<?php

/* DEPRECATED - NOT IN USE ANYMORE */

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\FramesManager;
use App\Core\PyApiImageService;

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$action = $data['action'] ?? null;
$frameId = isset($data['frame_id']) ? intval($data['frame_id']) : null;

if (!$action || !$frameId) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters: action, frame_id']);
    exit;
}

try {
    $fm = FramesManager::getInstance();
    $imageService = new PyApiImageService();

    $frame = $fm->loadFrameRow($frameId);
    if (!$frame) {
        throw new Exception("Source frame #{$frameId} not found.");
    }

    $projectRoot = \App\Core\SpwBase::getInstance()->getProjectPath();
    $sourceFullPath = $projectRoot . '/public/' . ltrim($frame['filename'], '/');
    $processedImageData = null;
    $note = "PyAPI: {$action}";

    // --- Route actions to the PyApiImageService ---
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

            
            
            
            
           //
// ... inside the switch ($action) ...
//



//
// ... inside the switch ($action) ...
//

        case 'filter':
            $filterType = $data['filter_type'] ?? 'unknown';
            $params = $data['params'] ?? [];
            $note = "PyAPI Filter: {$filterType}";

            if ($filterType === 'composite') { // Handles all sliders: Brightness, Contrast, Blur, and now Sharpen
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

                // --- Step 1: Apply brightness/contrast enhancement if needed ---
                if (!empty($enhanceOptions)) {
                    $imageData = $imageService->enhance($currentImagePath, $enhanceOptions);
                    if (!$imageData) throw new Exception("Failed to apply enhancements.");
                    
                    $tempFile = tempnam(sys_get_temp_dir(), 'img-edit-');
                    file_put_contents($tempFile, $imageData);
                    $currentImagePath = $tempFile;
                    $tempFiles[] = $tempFile;
                }

                // --- Step 2: Apply custom filters (sharpen/blur) if needed ---
                if (!empty($customFilterOptions)) {
                    $processedImageData = $imageService->applyCustomFilter($currentImagePath, $customFilterOptions);
                } else {
                    $processedImageData = file_get_contents($currentImagePath);
                }

                // --- Step 3: Clean up all temporary files ---
                foreach ($tempFiles as $file) {
                    if (file_exists($file)) unlink($file);
                }

            } else { // Handles all other preset filters like grayscale, vintage, etc.
                $presetName = $filterType === 'grayscale' ? 'noir' : $filterType;
                $processedImageData = $imageService->applyPreset($sourceFullPath, $presetName);
            }
            break;



/*

        case 'filter':
            $filterType = $data['filter_type'] ?? 'unknown';
            $params = $data['params'] ?? [];
            $note = "PyAPI Filter: {$filterType}";

            if ($filterType === 'composite') { // Handles all sliders: Brightness, Contrast, and now Blur
                $enhanceOptions = [];
                $blurRadius = floatval($params['blur_radius'] ?? 0);

                if (isset($params['brightness'])) $enhanceOptions['brightness'] = 1 + ($params['brightness'] / 100);
                if (isset($params['contrast'])) $enhanceOptions['contrast'] = 1 + ($params['contrast'] / 100);
                
                if (empty($enhanceOptions) && $blurRadius <= 0) {
                    throw new Exception("No adjustment parameters provided.");
                }

                $currentImagePath = $sourceFullPath;
                $tempFile = null;

                // --- Step 1: Apply brightness/contrast enhancement if needed ---
                if (!empty($enhanceOptions)) {
                    $enhancedImageData = $imageService->enhance($currentImagePath, $enhanceOptions);
                    if (!$enhancedImageData) throw new Exception("Failed to apply enhancements.");
                    
                    // Save result to a temporary file to be used as input for the next step
                    $tempFile = tempnam(sys_get_temp_dir(), 'img-edit-');
                    file_put_contents($tempFile, $enhancedImageData);
                    $currentImagePath = $tempFile; // The next operation will use this new temp image
                }

                // --- Step 2: Apply blur if needed ---
                if ($blurRadius > 0) {
                    $processedImageData = $imageService->blur($currentImagePath, $blurRadius);
                } else {
                    // If no blur was applied, the final image is the one from the enhance step (or original)
                    $processedImageData = file_get_contents($currentImagePath);
                }

                // --- Step 3: Clean up temporary file ---
                if ($tempFile && file_exists($tempFile)) {
                    unlink($tempFile);
                }

            } else { // Handles all other preset filters like grayscale, vintage, etc.
                $presetName = $filterType === 'grayscale' ? 'noir' : $filterType;
                $processedImageData = $imageService->applyPreset($sourceFullPath, $presetName);
            }
            break;

            */
            
            
            
            /*
        case 'filter':
            $filterType = $data['filter_type'] ?? 'unknown';
            $params = $data['params'] ?? [];
            $note = "PyAPI Filter: {$filterType}";

            if ($filterType === 'composite') { // This correctly handles Brightness/Contrast
                $enhanceOptions = [];
                if (isset($params['brightness'])) $enhanceOptions['brightness'] = 1 + ($params['brightness'] / 100);
                if (isset($params['contrast'])) $enhanceOptions['contrast'] = 1 + ($params['contrast'] / 100);
                if (empty($enhanceOptions)) throw new Exception("No brightness/contrast parameters provided.");
                $processedImageData = $imageService->enhance($sourceFullPath, $enhanceOptions);

            } elseif ($filterType === 'blur') { // FIX: Added special handling for the blur filter
                // Blur is a custom filter, not a preset, so we call the specific blur method.
                $processedImageData = $imageService->blur($sourceFullPath, 2); // Using a default radius of 2

            } else { // Handles all other preset filters like grayscale, vintage, etc.
                $presetName = $filterType === 'grayscale' ? 'noir' : $filterType;
                $processedImageData = $imageService->applyPreset($sourceFullPath, $presetName);
            }
            break;
            
            */
            
            
            
        default:
            throw new Exception("Unsupported action '{$action}'");
    }

    if (!$processedImageData) {
        throw new Exception("Python API did not return image data for action '{$action}'.");
    }

    // --- Save the processed image and register it in the database ---
    $forcedBasename = $fm->getNextFrameBasenameFromDB();
    $pi = pathinfo($frame['filename']);
    $dirnameRel = ($pi['dirname'] && $pi['dirname'] !== '.') ? $pi['dirname'] : '';
    $extension = $pi['extension'] ?? 'png'; // Default to png for consistency

    $derivedRel = ($dirnameRel ? (rtrim($dirnameRel, '/') . '/') : '') . $forcedBasename . '.' . $extension;
    $destFull = $projectRoot . '/public/' . ltrim($derivedRel, '/');

    if (file_put_contents($destFull, $processedImageData) === false) {
        throw new Exception("Failed to save processed image from Python API.");
    }
    
    // Register the new frame in the database
    $registerOpts = [
        'tool' => 'py-api-service',
        'mode' => $action,
        'userId' => $_SESSION['user_id'] ?? null,
        'note' => $note,
        'coords' => $data // Save all incoming params for debugging
    ];
    
    $result = $fm->registerDerivedFrameFromOriginal($frame, $derivedRel, null, $registerOpts);
    
    if (empty($result['success'])) {
        throw new Exception($result['message'] ?? 'Frame registration failed.');
    }
    
    // Add the new filename to the response, as the JS expects it
    $result['filename'] = $derivedRel;
    echo json_encode($result);

} catch (Exception $e) {
    // Log the full error for debugging
    error_log("image_editor_api.php Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
