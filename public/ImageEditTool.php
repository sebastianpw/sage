<?php
// ImageEditTool.php
// Filesystem/image-manipulation only. NO DB operations here.

class ImageEditTool {
    protected string $projectRoot;
    protected ?string $lastError = null;

    public function __construct() {
        $this->projectRoot = \App\Core\SpwBase::getInstance()->getProjectPath();
    }

    public function getLastError(): ?string {
        return $this->lastError;
    }

    /**
     * Creates a new image with a semi-transparent green mask overlay.
     * This is the functionality you previously called "crop".
     */
    public function createMaskedImage(string $srcRel, array $coords, string $forcedBasename): string
    {
        $imageData = $this->_loadImageResource($srcRel);
        $img = $imageData['resource'];

        $x = intval(round($coords['x'] ?? 0));
        $y = intval(round($coords['y'] ?? 0));
        $w = max(0, intval(round($coords['width'] ?? 0)));
        $h = max(0, intval(round($coords['height'] ?? 0)));

        // Clamp to image bounds
        $imgW = imagesx($img);
        $imgH = imagesy($img);
        $x = max(0, min($x, $imgW));
        $y = max(0, min($y, $imgH));
        if ($x + $w > $imgW) $w = $imgW - $x;
        if ($y + $h > $imgH) $h = $imgH - $y;

        imagealphablending($img, true);

        // A semi-transparent green mask (alpha 80/127) looks better
        $fill = imagecolorallocatealpha($img, 0, 220, 0, 80);
        imagefilledrectangle($img, $x, $y, $x + $w, $y + $h, $fill);
        $border = imagecolorallocatealpha($img, 0, 180, 0, 40);
        imagerectangle($img, $x, $y, $x + $w, $y + $h, $border);

        return $this->_saveImageResource($img, $srcRel, $forcedBasename, $imageData['mime']);
    }

    /**
     * Creates a new image by truly cropping it to the specified boundaries.
     */
    public function createCroppedImage(string $srcRel, array $coords, string $forcedBasename): string
    {
        $imageData = $this->_loadImageResource($srcRel);
        $img = $imageData['resource'];

        $cropRect = [
            'x' => intval(round($coords['x'] ?? 0)),
            'y' => intval(round($coords['y'] ?? 0)),
            'width' => max(1, intval(round($coords['width'] ?? imagesx($img)))),
            'height' => max(1, intval(round($coords['height'] ?? imagesy($img))))
        ];

        // Clamp crop box to be within image boundaries before cropping
        $imgW = imagesx($img);
        $imgH = imagesy($img);
        $cropRect['x'] = max(0, min($cropRect['x'], $imgW));
        $cropRect['y'] = max(0, min($cropRect['y'], $imgH));
        if ($cropRect['x'] + $cropRect['width'] > $imgW) {
            $cropRect['width'] = $imgW - $cropRect['x'];
        }
        if ($cropRect['y'] + $cropRect['height'] > $imgH) {
            $cropRect['height'] = $imgH - $cropRect['y'];
        }

        $croppedImg = imagecrop($img, $cropRect);
        imagedestroy($img);

        if (!$croppedImg) {
            $this->lastError = "Image crop operation failed.";
            throw new Exception($this->lastError);
        }

        return $this->_saveImageResource($croppedImg, $srcRel, $forcedBasename, $imageData['mime']);
    }

    // --- Private Helper Methods to reduce code duplication ---

    private function resolveSourceFullPath(string $srcRel): ?string
    {
        $srcRelTrim = ltrim($srcRel, '/');
        $candidates = [
            $this->projectRoot . '/public/' . $srcRelTrim,
            $this->projectRoot . '/' . $srcRelTrim,
            rtrim(\App\Core\SpwBase::getInstance()->getFramesDir(), '/') . '/' . basename($srcRelTrim),
        ];
        foreach ($candidates as $cand) {
            if (file_exists($cand) && is_file($cand)) return $cand;
        }
        return null;
    }

    private function _loadImageResource(string $srcRel): array
    {
        $srcFull = $this->resolveSourceFullPath($srcRel);
        if (!$srcFull) {
            $this->lastError = "Source file not found for {$srcRel}";
            throw new Exception($this->lastError);
        }

        $info = @getimagesize($srcFull);
        if (!$info) {
            $this->lastError = "getimagesize failed for {$srcFull}";
            throw new Exception($this->lastError);
        }
        
        $mime = $info['mime'] ?? '';
        $img = null;
        switch ($mime) {
            case 'image/jpeg': $img = @imagecreatefromjpeg($srcFull); break;
            case 'image/png':  $img = @imagecreatefrompng($srcFull);  break;
            case 'image/webp': if (function_exists('imagecreatefromwebp')) $img = @imagecreatefromwebp($srcFull); break;
            default:
                $this->lastError = "Unsupported mime type: {$mime}";
                throw new Exception($this->lastError);
        }

        if (!$img) {
            $this->lastError = "Failed to create image resource from {$srcFull}";
            throw new Exception($this->lastError);
        }

        return ['resource' => $img, 'mime' => $mime, 'path' => $srcFull];
    }

    private function _saveImageResource(\GdImage $img, string $originalSrcRel, string $forcedBasename, string $mime): string
    {
        $pi = pathinfo($originalSrcRel);
        $dirnameRel = ($pi['dirname'] && $pi['dirname'] !== '.') ? $pi['dirname'] : '';
        $extension = $pi['extension'] ?? 'png';

        $destRel = ($dirnameRel ? (rtrim($dirnameRel, '/') . '/') : '') . $forcedBasename . '.' . $extension;
        $destFull = $this->projectRoot . '/public/' . ltrim($destRel, '/');

        $dir = dirname($destFull);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            imagedestroy($img);
            $this->lastError = "Failed to create destination directory: {$dir}";
            throw new Exception($this->lastError);
        }

        $saved = false;
        switch ($mime) {
            case 'image/jpeg':
                $saved = @imagejpeg($img, $destFull, 90);
                break;
            case 'image/png':
                imagesavealpha($img, true);
                $saved = @imagepng($img, $destFull);
                break;
            case 'image/webp':
                if (function_exists('imagewebp')) {
                    imagesavealpha($img, true);
                    $saved = @imagewebp($img, $destFull);
                } else { // Fallback to PNG if WEBP function doesn't exist
                    imagesavealpha($img, true);
                    $saved = @imagepng($img, $destFull);
                }
                break;
            default: // Fallback to PNG for unknown types
                imagesavealpha($img, true);
                $saved = @imagepng($img, $destFull);
        }
        
        imagedestroy($img);

        if (!$saved) {
            $this->lastError = "Failed to save derived image to {$destFull}";
            throw new Exception($this->lastError);
        }

        return ltrim($destRel, '/');
    }
}

