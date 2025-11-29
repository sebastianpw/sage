<?php
namespace App\Core;

use App\Core\PyApiProxy;

/**
 * Image manipulation service using Python API
 * Provides comprehensive image processing capabilities
 */
class PyApiImageService extends PyApiProxy
{
    /**
     * Resize an image
     * 
     * @param string $sourceImagePath Path to source image
     * @param int $width Target width
     * @param int $height Target height
     * @param bool $keepAspectRatio Whether to maintain aspect ratio
     * @return string|null Raw image data
     */
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





/**
 * Crop an image
 */
public function crop(string $sourceImagePath, int $x, int $y, int $x2, int $y2): ?string
{
    $endpoint = $this->apiUrl . '/image/crop';
    // CHANGE: Use x1, y1, x2, y2 instead of x, y, width, height
    $postData = [
        'x1' => $x,
        'y1' => $y, 
        'x2' => $x2,
        'y2' => $y2
    ];

    return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
}










/**
 * Rotate an image
 */
public function rotate(string $sourceImagePath, float $angle, bool $expand = true): ?string
{
    $endpoint = $this->apiUrl . '/image/rotate';
    // CHANGE: Use 'degrees' instead of 'angle' and proper boolean for 'expand'
    $postData = [
        'degrees' => $angle,
        'expand' => $expand ? 'true' : 'false'
    ];

    return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
}





    /**
     * Flip an image
     * 
     * @param string $sourceImagePath Path to source image
     * @param string $direction Flip direction: 'horizontal' or 'vertical'
     * @return string|null Raw image data
     */
    public function flip(string $sourceImagePath, string $direction = 'horizontal'): ?string
    {
        $endpoint = $this->apiUrl . '/image/flip';
        $postData = ['direction' => $direction];

        return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
    }

    /**
     * Apply a preset filter
     * 
     * @param string $sourceImagePath Path to source image
     * @param string $filterName Filter name (e.g., 'grayscale', 'blur', 'sharpen')
     * @return string|null Raw image data
     */

public function applyPresetFilter(string $sourceImagePath, string $filterName): ?string
{
    $endpoint = $this->apiUrl . '/image/filter/preset';
    // CHANGE: Use 'name' instead of 'filter_name' to match Python API
    $postData = ['name' => $filterName];

    return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
}




    /**
     * Apply custom filters
     * 
     * @param string $sourceImagePath Path to source image
     * @param array $params Filter parameters (e.g., ['brightness' => 1.2, 'contrast' => 1.1])
     * @return string|null Raw image data
     */


     
     
     
     
    /**
     * Apply custom filters
     * 
     * @param string $sourceImagePath Path to source image
     * @param array $params Filter parameters (e.g., ['blur_radius' => 2, 'sharpen_amount' => 150])
     * @return string|null Raw image data
     */
    public function applyCustomFilter(string $sourceImagePath, array $params): ?string
    {
        $endpoint = $this->apiUrl . '/image/filter/custom';
        $postData = [];
        
        if (isset($params['blur_radius'])) {
            $postData['blur_radius'] = $params['blur_radius'];
        }
        // FIX: Changed from 'sharpen' boolean to 'sharpen_amount' float
        if (isset($params['sharpen_amount'])) {
            $postData['sharpen_amount'] = $params['sharpen_amount'];
        }
        
        return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
    }

     
     


    /**
     * Enhance an image
     * 
     * @param string $sourceImagePath Path to source image
     * @param array $options Enhancement options
     * @return string|null Raw image data
     */


public function enhance(string $sourceImagePath, array $options = []): ?string
{
    $endpoint = $this->apiUrl . '/image/enhance';
    $postData = $options;

    return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
}


    /**
     * Draw bounding boxes on an image
     * 
     * @param string $sourceImagePath Path to source image
     * @param array $boxes Array of boxes [['x' => 10, 'y' => 20, 'width' => 100, 'height' => 150], ...]
     * @param string $color Hex color (e.g., '#FF0000') or CSS color name
     * @param bool $fill Whether to fill the boxes
     * @param int $thickness Line thickness for box borders
     * @return string|null Raw image data
     */
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

    /**
     * Draw text on an image
     * 
     * @param string $sourceImagePath Path to source image
     * @param string $text Text to draw
     * @param int $x X coordinate
     * @param int $y Y coordinate
     * @param string $color Text color
     * @param int $fontSize Font size
     * @param string|null $fontPath Optional path to font file
     * @return string|null Raw image data
     */
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

        if ($fontPath !== null) {
            $postData['font_path'] = $fontPath;
        }

        return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
    }

    /**
     * Composite multiple images
     * 
     * @param string $backgroundPath Path to background image
     * @param string $overlayPath Path to overlay image
     * @param int $x X position for overlay
     * @param int $y Y position for overlay
     * @param float $alpha Opacity of overlay (0.0 to 1.0)
     * @return string|null Raw image data
     */
    public function composite(string $backgroundPath, string $overlayPath, int $x = 0, int $y = 0, float $alpha = 1.0): ?string
    {
        $endpoint = $this->apiUrl . '/image/composite';
        $postData = [
            'x' => $x,
            'y' => $y,
            'alpha' => $alpha
        ];

        return $this->executeApiRequestWithImages(
            $endpoint,
            [
                'background' => $backgroundPath,
                'overlay' => $overlayPath
            ],
            $postData
        );
    }

    /**
     * Convert image format
     * 
     * @param string $sourceImagePath Path to source image
     * @param string $format Target format (e.g., 'png', 'jpeg', 'webp')
     * @param int|null $quality Quality for lossy formats (1-100)
     * @return string|null Raw image data
     */
    public function convert(string $sourceImagePath, string $format, ?int $quality = null): ?string
    {
        $endpoint = $this->apiUrl . '/image/convert';
        $postData = ['format' => $format];

        if ($quality !== null) {
            $postData['quality'] = $quality;
        }

        return $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
    }

    /**
     * Get image information
     * 
     * @param string $sourceImagePath Path to source image
     * @return array|null Image info (width, height, format, mode, etc.)
     */
    public function getInfo(string $sourceImagePath): ?array
    {
        $endpoint = $this->apiUrl . '/image/info';
        return $this->executeApiRequestWithImageJson($endpoint, $sourceImagePath);
    }

    /**
     * Save image with specific options
     * 
     * @param string $sourceImagePath Path to source image
     * @param string $outputPath Output path on server
     * @param string|null $format Output format
     * @param int|null $quality Quality for lossy formats
     * @return array|null Response with save confirmation
     */
    public function save(string $sourceImagePath, string $outputPath, ?string $format = null, ?int $quality = null): ?array
    {
        $endpoint = $this->apiUrl . '/image/save';
        $postData = ['output_path' => $outputPath];

        if ($format !== null) {
            $postData['format'] = $format;
        }

        if ($quality !== null) {
            $postData['quality'] = $quality;
        }

        $response = $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
        return json_decode($response, true);
    }

    /**
     * Health check for the image service
     * 
     * @return array|null Health status
     */
    public function health(): ?array
    {
        $endpoint = $this->apiUrl . '/image/_health';
        $response = $this->executeGetRequest($endpoint);
        return json_decode($response, true);
    }

    // Convenience methods for common operations

    /**
     * Create a thumbnail
     */
    public function createThumbnail(string $sourceImagePath, int $maxWidth = 200, int $maxHeight = 200): ?string
    {
        return $this->resize($sourceImagePath, $maxWidth, $maxHeight, true);
    }


/**
 * Apply grayscale filter
 */
public function grayscale(string $sourceImagePath): ?string
{
    return $this->applyPresetFilter($sourceImagePath, 'noir');
}



/**
 * Apply any preset filter by name
 */
public function applyPreset(string $sourceImagePath, string $presetName): ?string
{
    return $this->applyPresetFilter($sourceImagePath, $presetName);
}



    /**
     * Apply blur filter
     */
    public function blur(string $sourceImagePath, int $radius = 2): ?string
    {
        // FIX: The parameter key is now 'blur_radius' to match the Python API.
        return $this->applyCustomFilter($sourceImagePath, ['blur_radius' => $radius]);
    }




    /**
     * Adjust brightness
     */
    public function adjustBrightness(string $sourceImagePath, float $factor): ?string
    {
        return $this->applyCustomFilter($sourceImagePath, ['brightness' => $factor]);
    }

    /**
     * Adjust contrast
     */
    public function adjustContrast(string $sourceImagePath, float $factor): ?string
    {
        return $this->applyCustomFilter($sourceImagePath, ['contrast' => $factor]);
    }

    /**
     * Rotate 90 degrees clockwise
     */
    public function rotate90(string $sourceImagePath): ?string
    {
        return $this->rotate($sourceImagePath, 90, true);
    }

    /**
     * Rotate 180 degrees
     */
    public function rotate180(string $sourceImagePath): ?string
    {
        return $this->rotate($sourceImagePath, 180, true);
    }

    /**
     * Rotate 270 degrees clockwise (90 counter-clockwise)
     */
    public function rotate270(string $sourceImagePath): ?string
    {
        return $this->rotate($sourceImagePath, 270, true);
    }

    /**
     * Flip horizontally
     */
    public function flipHorizontal(string $sourceImagePath): ?string
    {
        return $this->flip($sourceImagePath, 'horizontal');
    }

    /**
     * Flip vertically
     */
    public function flipVertical(string $sourceImagePath): ?string
    {
        return $this->flip($sourceImagePath, 'vertical');
    }
}
