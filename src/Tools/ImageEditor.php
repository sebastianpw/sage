<?php
namespace App\Tools;

use App\Core\SpwBase;
use App\Core\FramesManager;
use Exception;

/**
 * ImageEditor - Full-featured image manipulation module
 * Handles cropping, layering, transformations with AJAX endpoints
 * Works both as dedicated view and modal overlay
 */
class ImageEditor {
    protected SpwBase $spw;
    protected FramesManager $fm;
    protected string $projectRoot;
    protected string $framesDirRel;
    protected string $framesDir;
    protected ?string $lastError = null;
    protected array $supportedFormats = ['jpg', 'jpeg', 'png', 'webp'];

    public function __construct() {
        $this->spw = SpwBase::getInstance();
        $this->fm = FramesManager::getInstance();
        $this->projectRoot = $this->spw->getProjectPath();
        $this->framesDirRel = $this->spw->getFramesDirRel();
        $this->framesDir = $this->spw->getFramesDir();
    }

    public function getLastError(): ?string {
        return $this->lastError;
    }

    /**
     * Resolve source image path from relative filename
     */
    protected function resolveSourcePath(string $srcRel): ?string {
        $srcRelTrim = ltrim($srcRel, '/');
        
        $candidates = [
            $this->projectRoot . '/public/' . $srcRelTrim,
            $this->projectRoot . '/' . $srcRelTrim,
            rtrim($this->framesDir, '/') . '/' . basename($srcRelTrim),
        ];

        foreach ($candidates as $cand) {
            if (file_exists($cand) && is_file($cand)) {
                return $cand;
            }
        }

        return null;
    }

    /**
     * Create GD resource from file
     */
    protected function createImageResource(string $fullPath): ?\GdImage {
        $info = @getimagesize($fullPath);
        if (!$info) {
            $this->lastError = "Invalid image file: {$fullPath}";
            return null;
        }

        $mime = $info['mime'] ?? '';
        $img = null;

        switch ($mime) {
            case 'image/jpeg':
                $img = @imagecreatefromjpeg($fullPath);
                break;
            case 'image/png':
                $img = @imagecreatefrompng($fullPath);
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $img = @imagecreatefromwebp($fullPath);
                }
                break;
        }

        if (!$img) {
            $this->lastError = "Failed to create image resource from {$fullPath}";
            return null;
        }

        return $img;
    }

    /**
     * Save GD resource to file
     */
    protected function saveImageResource(\GdImage $img, string $destPath, string $format = 'png'): bool {
        $dir = dirname($destPath);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                $this->lastError = "Failed to create directory: {$dir}";
                return false;
            }
        }

        $format = strtolower($format);
        $saved = false;

        switch ($format) {
            case 'jpg':
            case 'jpeg':
                $saved = @imagejpeg($img, $destPath, 92);
                break;
            case 'png':
                $saved = @imagepng($img, $destPath, 9);
                break;
            case 'webp':
                if (function_exists('imagewebp')) {
                    $saved = @imagewebp($img, $destPath, 90);
                }
                break;
        }

        if (!$saved) {
            $this->lastError = "Failed to save image to {$destPath}";
        }

        return $saved;
    }

    /**
     * Crop image to specified coordinates
     */
    public function cropImage(string $srcRel, array $coords, string $forcedBasename): ?string {
        $srcFull = $this->resolveSourcePath($srcRel);
        if (!$srcFull) {
            $this->lastError = "Source file not found: {$srcRel}";
            return null;
        }

        $img = $this->createImageResource($srcFull);
        if (!$img) {
            return null;
        }

        $x = max(0, intval($coords['x'] ?? 0));
        $y = max(0, intval($coords['y'] ?? 0));
        $w = max(1, intval($coords['width'] ?? imagesx($img)));
        $h = max(1, intval($coords['height'] ?? imagesy($img)));

        // Clamp to image bounds
        $imgW = imagesx($img);
        $imgH = imagesy($img);
        if ($x + $w > $imgW) $w = $imgW - $x;
        if ($y + $h > $imgH) $h = $imgH - $y;

        // Create cropped image
        $cropped = imagecrop($img, ['x' => $x, 'y' => $y, 'width' => $w, 'height' => $h]);
        imagedestroy($img);

        if (!$cropped) {
            $this->lastError = "Crop operation failed";
            return null;
        }

        // Build destination path
        $pi = pathinfo($srcRel);
        $dirnameRel = isset($pi['dirname']) && $pi['dirname'] !== '.' ? rtrim($pi['dirname'], '/') : '';
        $extension = isset($pi['extension']) ? strtolower($pi['extension']) : 'png';

        $destRel = ($dirnameRel ? ($dirnameRel . '/') : '') . $forcedBasename . '.' . $extension;
        $destFull = $this->projectRoot . '/public/' . ltrim($destRel, '/');

        $saved = $this->saveImageResource($cropped, $destFull, $extension);
        imagedestroy($cropped);

        return $saved ? ltrim($destRel, '/') : null;
    }

    /**
     * Resize image to specified dimensions
     */
    public function resizeImage(string $srcRel, int $width, int $height, string $forcedBasename, bool $maintainAspect = true): ?string {
        $srcFull = $this->resolveSourcePath($srcRel);
        if (!$srcFull) {
            $this->lastError = "Source file not found: {$srcRel}";
            return null;
        }

        $img = $this->createImageResource($srcFull);
        if (!$img) {
            return null;
        }

        $srcW = imagesx($img);
        $srcH = imagesy($img);

        if ($maintainAspect) {
            $ratio = min($width / $srcW, $height / $srcH);
            $width = intval($srcW * $ratio);
            $height = intval($srcH * $ratio);
        }

        $resized = imagescale($img, $width, $height);
        imagedestroy($img);

        if (!$resized) {
            $this->lastError = "Resize operation failed";
            return null;
        }

        $pi = pathinfo($srcRel);
        $dirnameRel = isset($pi['dirname']) && $pi['dirname'] !== '.' ? rtrim($pi['dirname'], '/') : '';
        $extension = isset($pi['extension']) ? strtolower($pi['extension']) : 'png';

        $destRel = ($dirnameRel ? ($dirnameRel . '/') : '') . $forcedBasename . '.' . $extension;
        $destFull = $this->projectRoot . '/public/' . ltrim($destRel, '/');

        $saved = $this->saveImageResource($resized, $destFull, $extension);
        imagedestroy($resized);

        return $saved ? ltrim($destRel, '/') : null;
    }

    /**
     * Layer images - composite multiple images together
     */
    public function layerImages(array $layers, string $forcedBasename, int $canvasWidth, int $canvasHeight): ?string {
        // Create base canvas
        $canvas = imagecreatetruecolor($canvasWidth, $canvasHeight);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);
        imagesavealpha($canvas, true);
        imagealphablending($canvas, true);

        foreach ($layers as $layer) {
            $srcRel = $layer['src'] ?? null;
            if (!$srcRel) continue;

            $srcFull = $this->resolveSourcePath($srcRel);
            if (!$srcFull) continue;

            $layerImg = $this->createImageResource($srcFull);
            if (!$layerImg) continue;

            $x = intval($layer['x'] ?? 0);
            $y = intval($layer['y'] ?? 0);
            $opacity = intval($layer['opacity'] ?? 100);
            $width = intval($layer['width'] ?? imagesx($layerImg));
            $height = intval($layer['height'] ?? imagesy($layerImg));

            // Resize layer if needed
            if ($width !== imagesx($layerImg) || $height !== imagesy($layerImg)) {
                $resized = imagescale($layerImg, $width, $height);
                imagedestroy($layerImg);
                $layerImg = $resized;
            }

            // Apply opacity
            if ($opacity < 100) {
                imagefilter($layerImg, IMG_FILTER_COLORIZE, 0, 0, 0, 127 * (1 - $opacity / 100));
            }

            // Composite onto canvas
            imagecopy($canvas, $layerImg, $x, $y, 0, 0, imagesx($layerImg), imagesy($layerImg));
            imagedestroy($layerImg);
        }

        // Save result
        $destRel = $this->framesDirRel . '/' . $forcedBasename . '.png';
        $destFull = $this->projectRoot . '/public/' . ltrim($destRel, '/');

        $saved = $this->saveImageResource($canvas, $destFull, 'png');
        imagedestroy($canvas);

        return $saved ? ltrim($destRel, '/') : null;
    }

    /**
     * Rotate image by specified degrees
     */
    public function rotateImage(string $srcRel, float $angle, string $forcedBasename): ?string {
        $srcFull = $this->resolveSourcePath($srcRel);
        if (!$srcFull) {
            $this->lastError = "Source file not found: {$srcRel}";
            return null;
        }

        $img = $this->createImageResource($srcFull);
        if (!$img) {
            return null;
        }

        $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
        $rotated = imagerotate($img, $angle, $transparent);
        imagedestroy($img);

        if (!$rotated) {
            $this->lastError = "Rotation failed";
            return null;
        }

        imagesavealpha($rotated, true);

        $pi = pathinfo($srcRel);
        $dirnameRel = isset($pi['dirname']) && $pi['dirname'] !== '.' ? rtrim($pi['dirname'], '/') : '';
        $extension = isset($pi['extension']) ? strtolower($pi['extension']) : 'png';

        $destRel = ($dirnameRel ? ($dirnameRel . '/') : '') . $forcedBasename . '.' . $extension;
        $destFull = $this->projectRoot . '/public/' . ltrim($destRel, '/');

        $saved = $this->saveImageResource($rotated, $destFull, $extension);
        imagedestroy($rotated);

        return $saved ? ltrim($destRel, '/') : null;
    }

    /**
     * Apply filter to image
     */
    public function applyFilter(string $srcRel, string $filterType, array $params, string $forcedBasename): ?string {
        $srcFull = $this->resolveSourcePath($srcRel);
        if (!$srcFull) {
            $this->lastError = "Source file not found: {$srcRel}";
            return null;
        }

        $img = $this->createImageResource($srcFull);
        if (!$img) {
            return null;
        }

        $success = false;
        switch ($filterType) {
            case 'grayscale':
                $success = imagefilter($img, IMG_FILTER_GRAYSCALE);
                break;
            case 'brightness':
                $level = intval($params['level'] ?? 0);
                $success = imagefilter($img, IMG_FILTER_BRIGHTNESS, $level);
                break;
            case 'contrast':
                $level = intval($params['level'] ?? 0);
                $success = imagefilter($img, IMG_FILTER_CONTRAST, $level);
                break;
            case 'blur':
                $success = imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);
                break;
        }

        if (!$success) {
            imagedestroy($img);
            $this->lastError = "Filter application failed";
            return null;
        }

        $pi = pathinfo($srcRel);
        $dirnameRel = isset($pi['dirname']) && $pi['dirname'] !== '.' ? rtrim($pi['dirname'], '/') : '';
        $extension = isset($pi['extension']) ? strtolower($pi['extension']) : 'png';

        $destRel = ($dirnameRel ? ($dirnameRel . '/') : '') . $forcedBasename . '.' . $extension;
        $destFull = $this->projectRoot . '/public/' . ltrim($destRel, '/');

        $saved = $this->saveImageResource($img, $destFull, $extension);
        imagedestroy($img);

        return $saved ? ltrim($destRel, '/') : null;
    }

    /**
     * Get image information
     */
    public function getImageInfo(string $srcRel): ?array {
        $srcFull = $this->resolveSourcePath($srcRel);
        if (!$srcFull) {
            $this->lastError = "Source file not found: {$srcRel}";
            return null;
        }

        $info = @getimagesize($srcFull);
        if (!$info) {
            $this->lastError = "Failed to get image info";
            return null;
        }

        return [
            'width' => $info[0],
            'height' => $info[1],
            'mime' => $info['mime'] ?? '',
            'size' => filesize($srcFull),
            'path' => $srcFull
        ];
    }
}
