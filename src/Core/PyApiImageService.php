<?php
namespace App\Core;

use App\Core\PyApiProxy;
use Exception;

/**
 * Image manipulation service using Python API
 * Provides comprehensive image processing capabilities
 */
class PyApiImageService extends PyApiProxy
{
    // --- Standard Transformations ---

    public function resize(string $sourceImagePath, int $width, int $height, bool $keepAspectRatio = true): ?string
    {
        $endpoint = $this->apiUrl . '/image/resize';
        $postData = [
            'width' => $width,
            'height' => $height,
            'keep_aspect_ratio' => $keepAspectRatio ? 'true' : 'false'
        ];
        return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
    }

    public function crop(string $sourceImagePath, int $x, int $y, int $x2, int $y2): ?string
    {
        $endpoint = $this->apiUrl . '/image/crop';
        $postData = ['x1' => $x, 'y1' => $y, 'x2' => $x2, 'y2' => $y2];
        return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
    }

    public function rotate(string $sourceImagePath, float $angle, bool $expand = true): ?string
    {
        $endpoint = $this->apiUrl . '/image/rotate';
        $postData = ['degrees' => $angle, 'expand' => $expand ? 'true' : 'false'];
        return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
    }

    public function flip(string $sourceImagePath, string $direction = 'horizontal'): ?string
    {
        $endpoint = $this->apiUrl . '/image/flip';
        $postData = ['direction' => $direction];
        return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
    }

    // --- Filters & Enhancements ---

    public function applyPresetFilter(string $sourceImagePath, string $filterName): ?string
    {
        $endpoint = $this->apiUrl . '/image/filter/preset';
        $postData = ['name' => $filterName];
        return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
    }

    public function applyCustomFilter(string $sourceImagePath, array $params): ?string
    {
        $endpoint = $this->apiUrl . '/image/filter/custom';
        $postData = [];
        if (isset($params['blur_radius'])) $postData['blur_radius'] = $params['blur_radius'];
        if (isset($params['sharpen_amount'])) $postData['sharpen_amount'] = $params['sharpen_amount'];
        return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
    }

    public function enhance(string $sourceImagePath, array $options = []): ?string
    {
        $endpoint = $this->apiUrl . '/image/enhance';
        return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $options);
    }

    // --- Drawing & Composition ---

    public function drawBoxes(string $sourceImagePath, array $boxes, string $color = '#00FF00', bool $fill = false, int $thickness = 2): ?string
    {
        $endpoint = $this->apiUrl . '/image/draw/boxes';
        $postData = [
            'boxes' => json_encode($boxes),
            'color' => $color,
            'fill' => $fill ? 'true' : 'false',
            'thickness' => $thickness
        ];
        return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
    }

    public function drawPolygons(string $sourceImagePath, array $polygons, string $color = '#00FF00', bool $fill = false): ?string
    {
        $endpoint = $this->apiUrl . '/image/draw/polygons';
        $postData = [
            'polygons' => json_encode($polygons),
            'color' => $color,
            'fill' => $fill ? 'true' : 'false'
        ];
        return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
    }

    public function drawText(string $sourceImagePath, string $text, int $x, int $y, string $color = '#FFFFFF', int $fontSize = 20, ?string $fontPath = null): ?string
    {
        $endpoint = $this->apiUrl . '/image/draw/text';
        $postData = [
            'text' => $text,
            'x' => $x,
            'y' => $y,
            'color' => $color,
            'font_size' => $fontSize
        ];
        if ($fontPath !== null) $postData['font_path'] = $fontPath;
        return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
    }

    public function composite(string $backgroundPath, string $overlayPath, int $x = 0, int $y = 0, float $alpha = 1.0): ?string
    {
        $endpoint = $this->apiUrl . '/image/composite';
        $postData = ['x' => $x, 'y' => $y, 'alpha' => $alpha];
        return $this->executeApiRequestWithImages(
            $endpoint,
            ['background' => $backgroundPath, 'overlay' => $overlayPath],
            $postData
        );
    }

    public function outpaint(string $sourceImagePath, int $width, ?int $height = null, ?int $x = null, ?int $y = null, string $color = '#00FF00'): ?string
    {
        $endpoint = $this->apiUrl . '/image/outpaint';
        $postData = ['width' => $width, 'color' => $color];
        if ($height !== null) $postData['height'] = $height;
        if ($x !== null) $postData['x'] = $x;
        if ($y !== null) $postData['y'] = $y;
        return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
    }

    // --- Utilities ---

    public function convert(string $sourceImagePath, string $format, ?int $quality = null): ?string
    {
        $endpoint = $this->apiUrl . '/image/convert';
        $postData = ['format' => $format];
        if ($quality !== null) $postData['quality'] = $quality;
        return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
    }

    public function getInfo(string $sourceImagePath): ?array
    {
        $endpoint = $this->apiUrl . '/image/info';
        return $this->executeApiRequestWithImageJson($endpoint, $sourceImagePath);
    }

    public function save(string $sourceImagePath, string $outputPath, ?string $format = null, ?int $quality = null): ?array
    {
        $endpoint = $this->apiUrl . '/image/save';
        $postData = ['output_path' => $outputPath];
        if ($format !== null) $postData['format'] = $format;
        if ($quality !== null) $postData['quality'] = $quality;
        $response = $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
        return json_decode($response, true);
    }

    public function health(): ?array
    {
        $endpoint = $this->apiUrl . '/image/_health';
        $response = $this->executeGetRequest($endpoint);
        return json_decode($response, true);
    }

    // --- REMBG / Background Removal (Echo Service) ---

    private function getRemBgBaseUrl(): string
    {
        $scriptPath = \App\Core\SpwBase::getInstance()->getProjectPath() . '/bash/pyapi_echo.sh';
        
        if (file_exists($scriptPath)) {
            // Use explicit 'sh' execution compatible with Termux/PHP environment
            $command = 'sh ' . escapeshellarg($scriptPath);
            $output = shell_exec($command . ' 2>&1');
            
            if ($output !== null) {
                $url = trim($output);
                if (!empty($url) && (strpos($url, 'http') === 0)) {
                    return rtrim($url, '/');
                }
            }
        }
        
        // Fallback to standard URL if script fails
        return $this->apiUrl;
    }

    public function checkRemBgHealth(): bool
    {
        try {
            $baseUrl = $this->getRemBgBaseUrl();
            $endpoint = $baseUrl . '/health';
            
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 2); 
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $httpCode === 200;
        } catch (Exception $e) {
            return false;
        }
    }

    public function removeBackground(string $sourceImagePath, string $model = 'u2net', string $quality = 'film'): ?string
    {
        $baseUrl = $this->getRemBgBaseUrl();

        // 1. Start Task
        $startEndpoint = $baseUrl . '/remove-bg-async';
        $postData = [
            'model' => $model,
            'quality' => $quality,
            'output' => 'rgba'
        ];
        
        if (!file_exists($sourceImagePath)) throw new Exception("Source image not found");
        
        $postData['file'] = new \CURLFile($sourceImagePath, mime_content_type($sourceImagePath), basename($sourceImagePath));
        
        $ch = curl_init($startEndpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || $httpCode != 200) {
            $msg = "Failed to start rembg task";
            if ($response) {
                $errJson = json_decode($response, true);
                if (isset($errJson['detail'])) $msg .= ": " . (is_string($errJson['detail']) ? $errJson['detail'] : json_encode($errJson['detail']));
            } else {
                $msg .= ": HTTP $httpCode";
            }
            throw new Exception($msg);
        }

        $json = json_decode($response, true);
        $taskId = $json['task_id'] ?? null;
        if (!$taskId) throw new Exception("No task ID returned from rembg service");

        // 2. Poll for completion
        $maxAttempts = 90; // 180 seconds
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            sleep(2); 
            
            $statusEndpoint = $baseUrl . '/status/' . $taskId;
            $ch = curl_init($statusEndpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $res = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            if ($code == 200) {
                if (strpos($contentType, 'image') !== false) {
                    return $res; 
                }
                
                $statusJson = json_decode($res, true);
                $status = $statusJson['status'] ?? 'UNKNOWN';
                
                if ($status === 'FAILED') {
                    throw new Exception("Rembg task failed: " . ($statusJson['error'] ?? 'Unknown error'));
                }
            } else {
                throw new Exception("Error polling status: HTTP $code");
            }
            
            $attempt++;
        }

        throw new Exception("Rembg task timed out");
    }

    // --- Convenience Methods ---
    
    public function createThumbnail(string $sourceImagePath, int $maxWidth = 200, int $maxHeight = 200): ?string
    {
        return $this->resize($sourceImagePath, $maxWidth, $maxHeight, true);
    }

    public function grayscale(string $sourceImagePath): ?string
    {
        return $this->applyPresetFilter($sourceImagePath, 'noir');
    }

    public function applyPreset(string $sourceImagePath, string $presetName): ?string
    {
        return $this->applyPresetFilter($sourceImagePath, $presetName);
    }

    public function blur(string $sourceImagePath, int $radius = 2): ?string
    {
        return $this->applyCustomFilter($sourceImagePath, ['blur_radius' => $radius]);
    }

    public function adjustBrightness(string $sourceImagePath, float $factor): ?string
    {
        return $this->applyCustomFilter($sourceImagePath, ['brightness' => $factor]);
    }

    public function adjustContrast(string $sourceImagePath, float $factor): ?string
    {
        return $this->applyCustomFilter($sourceImagePath, ['contrast' => $factor]);
    }

    public function rotate90(string $sourceImagePath): ?string
    {
        return $this->rotate($sourceImagePath, 90, true);
    }

    public function rotate180(string $sourceImagePath): ?string
    {
        return $this->rotate($sourceImagePath, 180, true);
    }

    public function rotate270(string $sourceImagePath): ?string
    {
        return $this->rotate($sourceImagePath, 270, true);
    }

    public function flipHorizontal(string $sourceImagePath): ?string
    {
        return $this->flip($sourceImagePath, 'horizontal');
    }

    public function flipVertical(string $sourceImagePath): ?string
    {
        return $this->flip($sourceImagePath, 'vertical');
    }
}
