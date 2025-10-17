<?php
// ImageEditTool.php
// Filesystem/image-manipulation only. NO DB operations here.
// Keep as a helpful tool for creating derived images (GD).
// Place/require this file where your endpoints need it.

class ImageEditTool {
    protected string PROJECT_ROOT;
    protected string FRAMES_ROOT;      // absolute path to frames folder (from load_root.php)
    protected string $framesDirRel;     // relative frames dir (relative to public/, as you use)
    protected ?string $lastError = null;

    public function __construct(string $projectRoot) {
        $this->PROJECT_ROOT = rtrim($projectRoot, '/');

        // load_root.php is expected to define PROJECT_ROOT and FRAMES_ROOT
        if (file_exists(__DIR__ . '/load_root.php')) {
            require __DIR__ . '/load_root.php';
        } elseif (file_exists($this->PROJECT_ROOT . '/load_root.php')) {
            require $this->PROJECT_ROOT . '/load_root.php';
        }

        // FRAMES_ROOT should be set by load_root.php
        $this->FRAMES_ROOT = isset(FRAMES_ROOT) ? rtrim(FRAMES_ROOT, '/') : ($this->PROJECT_ROOT . '/public/frames');

        // Make a relative path consistent with how filenames are stored (strip PROJECT_ROOT/public/)
        $publicPrefix = rtrim($this->PROJECT_ROOT, '/') . '/public/';
        $this->framesDirRel = str_replace($publicPrefix, '', $this->FRAMES_ROOT) . '/';
    }

    public function getLastError(): ?string {
        return $this->lastError;
    }

    /**
     * Resolve the source filename candidates and return the first full path found.
     * Returns full absolute path or null if not found.
     */
    protected function resolveSourceFullPath(string $srcRel): ?string
    {
        $srcRelTrim = ltrim($srcRel, '/');

        $candidates = [
            $this->PROJECT_ROOT . '/public/' . $srcRelTrim,
            $this->PROJECT_ROOT . '/' . $srcRelTrim,
            rtrim($this->FRAMES_ROOT, '/') . '/' . basename($srcRelTrim),
        ];

        foreach ($candidates as $cand) {
            if (file_exists($cand) && is_file($cand)) return $cand;
        }

        return null;
    }

    /**
     * Create derived image by drawing a rectangle overlay.
     * - $srcRel: stored filename relative to public/, e.g. "frames/.../frame000123.jpg"
     * - $coords: array with keys x,y,width,height in natural pixel units
     * - $forcedBasename: REQUIRED basename (e.g. "frame0000123") provided by FramesManager
     *
     * Returns the derived filename relative to public/, e.g. "frames/.../frame0000123.jpg"
     * Throws Exception on failure.
     */
    public function createDerivedImage(string $srcRel, array $coords, string $forcedBasename): string
    {
        // resolve source file
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
                $this->lastError = "Unsupported mime: {$mime}";
                throw new Exception($this->lastError);
        }
        if (!$img) {
            $this->lastError = "Failed to create image resource from {$srcFull}";
            throw new Exception($this->lastError);
        }

        // natural coordinates expected in $coords: x,y,width,height
        $x = isset($coords['x']) ? intval(round($coords['x'])) : 0;
        $y = isset($coords['y']) ? intval(round($coords['y'])) : 0;
        $w = isset($coords['width']) ? max(0, intval(round($coords['width']))) : 0;
        $h = isset($coords['height']) ? max(0, intval(round($coords['height']))) : 0;

        // clamp to image bounds
        $imgW = imagesx($img);
        $imgH = imagesy($img);
        if ($x < 0) $x = 0;
        if ($y < 0) $y = 0;
        if ($x > $imgW) $x = $imgW;
        if ($y > $imgH) $y = $imgH;
        if ($w <= 0) $w = max(1, $imgW - $x);
        if ($h <= 0) $h = max(1, $imgH - $y);
        if ($x + $w > $imgW) $w = $imgW - $x;
        if ($y + $h > $imgH) $h = $imgH - $y;

        @imagealphablending($img, true);
        @imagesavealpha($img, true);

        // overlay rectangle and border
        $alphaFill = 0; // fully opaque overlay color; change to >0 for translucency
        $fill = imagecolorallocatealpha($img, 0, 200, 0, $alphaFill);
        imagefilledrectangle($img, $x, $y, $x + $w, $y + $h, $fill);
        $border = imagecolorallocatealpha($img, 0, 160, 0, 40);
        imagerectangle($img, $x, $y, $x + $w, $y + $h, $border);

        // build destination relative path using forced basename (no fallback)
        $pi = pathinfo($srcRel);
        $dirnameRel = isset($pi['dirname']) ? rtrim($pi['dirname'], '/') : '';
        $extension = isset($pi['extension']) ? strtolower($pi['extension']) : 'png';

        // IMPORTANT: forcedBasename is used exactly as provided (expected to be created by FramesManager)
        $destRel = ($dirnameRel ? ($dirnameRel . '/') : '') . $forcedBasename . '.' . $extension;
        $destFull = $this->PROJECT_ROOT . '/public/' . ltrim($destRel, '/');

        $dir = dirname($destFull);
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                imagedestroy($img);
                $this->lastError = "Failed to create destination directory: {$dir}";
                throw new Exception($this->lastError);
            }
        }

        $saved = false;
        if ($mime === 'image/jpeg') {
            $saved = @imagejpeg($img, $destFull, 90);
        } elseif ($mime === 'image/png') {
            $saved = @imagepng($img, $destFull);
        } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
            $saved = @imagewebp($img, $destFull);
        } else {
            $saved = @imagepng($img, $destFull);
        }

        imagedestroy($img);

        if (!$saved) {
            $this->lastError = "Failed to save derived image to {$destFull}";
            throw new Exception($this->lastError);
        }

        // return relative path (consistent with frames filenames that are relative to public/)
        return ltrim($destRel, '/');
    }
}
