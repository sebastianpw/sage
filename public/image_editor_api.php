<?php
// public/image_editor_api.php
// Handles temporary image edits WITHOUT saving to database.
// All existing actions unchanged. New: 'grade' action, 'chromakey_bg' action.

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\PyApiImageService;
use App\Core\SpwBase;

header('Content-Type: application/json; charset=utf-8');

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

$action     = $data['action']      ?? null;
$sourceFile = $data['source_file'] ?? null;

if (!$action || !$sourceFile) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters: action, source_file']);
    exit;
}

try {
    $imageService = new PyApiImageService();
    $spw          = SpwBase::getInstance();
    $projectRoot  = $spw->getProjectPath();

    // Build full path to source
    $sourceFullPath = $projectRoot . '/public/' . ltrim($sourceFile, '/');

    if (!file_exists($sourceFullPath)) {
        throw new Exception("Source file not found: {$sourceFile}");
    }

    $processedImageData = null;
    $note               = "Temp: {$action}";

    switch ($action) {

        // ── EXISTING ACTIONS (ALL UNCHANGED) ────────────────────────────

        case 'rotate':
            $angle              = floatval($data['angle'] ?? 0);
            $processedImageData = $imageService->rotate($sourceFullPath, $angle);
            $note              .= " by {$angle}°";
            break;

        case 'resize':
            $width  = intval($data['width']  ?? 0);
            $height = intval($data['height'] ?? 0);
            if ($width <= 0 || $height <= 0) throw new Exception("Invalid dimensions for resize.");
            $processedImageData = $imageService->resize($sourceFullPath, $width, $height, true);
            $note              .= " to {$width}x{$height}";
            break;

        case 'outpaint':
            $width  = intval($data['width'] ?? 0);
            $height = !empty($data['height']) ? intval($data['height']) : null;
            $x      = isset($data['x']) && $data['x'] !== '' ? intval($data['x']) : null;
            $y      = isset($data['y']) && $data['y'] !== '' ? intval($data['y']) : null;
            $color  = $data['color'] ?? '#00FF00';
            if ($width <= 0) throw new Exception("Invalid width for outpaint.");
            $processedImageData = $imageService->outpaint($sourceFullPath, $width, $height, $x, $y, $color);
            $note              .= " with canvas {$width}x" . ($height ?: $width);
            break;

        case 'filter':
            $filterType = $data['filter_type'] ?? 'unknown';
            $params     = $data['params']       ?? [];
            $note       = "Temp Filter: {$filterType}";

            if ($filterType === 'composite') {
                $enhanceOptions      = [];
                $customFilterOptions = [];

                if (isset($params['brightness']))    $enhanceOptions['brightness']     = 1 + ($params['brightness'] / 100);
                if (isset($params['contrast']))      $enhanceOptions['contrast']       = 1 + ($params['contrast']   / 100);
                if (isset($params['blur_radius']))   $customFilterOptions['blur_radius']   = floatval($params['blur_radius']);
                if (isset($params['sharpen_amount'])) $customFilterOptions['sharpen_amount'] = intval($params['sharpen_amount']);

                if (empty($enhanceOptions) && empty($customFilterOptions)) {
                    throw new Exception("No adjustment parameters provided.");
                }

                $currentImagePath = $sourceFullPath;
                $tempFiles        = [];

                if (!empty($enhanceOptions)) {
                    $imageData = $imageService->enhance($currentImagePath, $enhanceOptions);
                    if (!$imageData) throw new Exception("Failed to apply enhancements.");
                    $tempFile = tempnam(sys_get_temp_dir(), 'img-edit-');
                    file_put_contents($tempFile, $imageData);
                    $currentImagePath = $tempFile;
                    $tempFiles[]      = $tempFile;
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
                $presetName         = $filterType === 'grayscale' ? 'noir' : $filterType;
                $processedImageData = $imageService->applyPreset($sourceFullPath, $presetName);
            }
            break;

        case 'crop':
            $mode   = $data['mode']   ?? 'crop';
            $coords = $data['coords'] ?? null;
            if (!$coords) throw new Exception("Missing crop coordinates");

            require_once __DIR__ . '/ImageEditTool.php';
            $iet          = new ImageEditTool();
            $tempBasename = 'temp_' . uniqid() . '_' . time();

            if ($mode === 'crop') {
                $derivedRel = $iet->createCroppedImage($sourceFile, $coords, $tempBasename);
            } else {
                $derivedRel = $iet->createMaskedImage($sourceFile, $coords, $tempBasename);
            }

            echo json_encode([
                'success'  => true,
                'filename' => $derivedRel,
                'message'  => 'Crop applied (temporary)',
                'is_temp'  => true,
            ]);
            exit;

        case 'draw_mask':
            $polygons = $data['polygons'] ?? [];
            $boxes    = $data['boxes']    ?? [];

            if (empty($polygons) && empty($boxes)) {
                throw new Exception("No masks provided.");
            }

            $color = $data['color'] ?? '#00FF00';
            $fill  = $data['fill']  ?? true;

            if (!empty($polygons)) {
                $processedImageData = $imageService->drawPolygons($sourceFullPath, $polygons, $color, $fill);
                $note              .= " with " . count($polygons) . " polygons";
            } else {
                $processedImageData = $imageService->drawBoxes($sourceFullPath, $boxes, $color, $fill);
                $note              .= " with " . count($boxes) . " boxes";
            }
            break;

        case 'remove_bg':
            if (!$imageService->checkRemBgHealth()) {
                throw new Exception("Background removal service is offline (Echo not reachable).");
            }
            $model              = $data['model']   ?? 'u2net';
            $quality            = $data['quality'] ?? 'fast';
            $processedImageData = $imageService->removeBackground($sourceFullPath, $model, $quality);
            $note               = "Background Removed";
            break;

        // ── NEW: CHROMAKEY_BG action ─────────────────────────────────────
        // Sends image to /image/chromakey-async, polls /status/{task_id}.
        // Returns a temp file exactly like other actions — history/undo works.

        case 'chromakey_bg':
            if (!$imageService->checkRemBgHealth()) {
                throw new Exception("Background removal service is offline (Echo not reachable).");
            }
            $color     = $data['color']     ?? '#00FF00';
            $threshold = isset($data['threshold']) ? floatval($data['threshold']) : 0.15;
            $softness  = isset($data['softness'])  ? floatval($data['softness'])  : 0.05;

            $processedImageData = $imageService->removeBackgroundChromakey($sourceFullPath, $color, $threshold, $softness);
            $note               = "Greenscreen Removed";
            // Force PNG extension for the temp file since result is always RGBA PNG
            $extension = 'png';

            // Save directly and exit (bypass the shared extension detection below)
            $tempBasename = 'temp_' . uniqid() . '_' . time();
            $tempFilename = $tempBasename . '.png';
            $tempRel      = 'temp/' . $tempFilename;
            $tempFull     = $projectRoot . '/public/' . ltrim($tempRel, '/');
            $tempDir      = dirname($tempFull);
            if (!is_dir($tempDir)) mkdir($tempDir, 0755, true);
            if (file_put_contents($tempFull, $processedImageData) === false) {
                throw new Exception("Failed to save temporary image.");
            }
            echo json_encode([
                'success'  => true,
                'filename' => $tempRel,
                'message'  => 'Greenscreen removal applied (temporary)',
                'is_temp'  => true,
            ]);
            exit;

        // ── NEW: GRADE action ────────────────────────────────────────────
        // Sends settings_json to Pillow /image/grade endpoint.
        // Returns a temp file exactly like other actions — history/undo works.

        case 'grade':
            $settings = $data['settings'] ?? null;
            if (!$settings) throw new Exception("Missing settings for grade action.");

            $settingsJson = json_encode($settings, JSON_UNESCAPED_UNICODE);
            $pyApiUrl     = 'http://127.0.0.1:8009/image/grade';

            $postData = [
                'file'          => new CURLFile($sourceFullPath, mime_content_type($sourceFullPath), basename($sourceFullPath)),
                'settings_json' => $settingsJson,
            ];

            $ch = curl_init($pyApiUrl);
            curl_setopt($ch, CURLOPT_POST,           1);
            curl_setopt($ch, CURLOPT_POSTFIELDS,     $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_TIMEOUT,        120);
            $responseBody = curl_exec($ch);
            $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError    = curl_error($ch);
            curl_close($ch);

            if ($curlError) throw new Exception("PyAPI cURL error (grade): $curlError");
            if ($httpCode !== 200) {
                $errJson = json_decode($responseBody, true);
                $detail  = $errJson['detail'] ?? $responseBody;
                throw new Exception("PyAPI grade returned HTTP $httpCode: $detail");
            }

            $processedImageData = $responseBody;
            $note               = "Temp: Color Grade";
            break;

        // ─────────────────────────────────────────────────────────────────

        default:
            throw new Exception("Unsupported action '{$action}'");
    }

    if (!$processedImageData) {
        throw new Exception("Python API did not return image data for action '{$action}'.");
    }

    // Save to temp directory (unchanged logic)
    $pi           = pathinfo($sourceFile);
    $extension    = $pi['extension'] ?? 'png';
    $tempBasename = 'temp_' . uniqid() . '_' . time();
    $tempFilename = $tempBasename . '.' . $extension;
    $tempRel      = 'temp/' . $tempFilename;
    $tempFull     = $projectRoot . '/public/' . ltrim($tempRel, '/');

    $tempDir = dirname($tempFull);
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    if (file_put_contents($tempFull, $processedImageData) === false) {
        throw new Exception("Failed to save temporary image.");
    }

    echo json_encode([
        'success'  => true,
        'filename' => $tempRel,
        'message'  => "{$action} applied (temporary)",
        'is_temp'  => true,
    ]);

} catch (Exception $e) {
    error_log("image_editor_api.php Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
