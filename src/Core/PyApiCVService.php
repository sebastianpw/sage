<?php
namespace App\Core;

use App\Core\PyApiProxy;
use Exception;

/**
 * Computer Vision Service Wrapper
 * 
 * Interacts with the PyAPI /cv endpoints to perform image analysis
 * using Pollinations.ai (via FreeImage.host uploads).
 */
class PyApiCVService extends PyApiProxy
{
    /**
     * Analyze an image using Computer Vision
     * 
     * @param string $imagePath Local path to the image file to upload
     * @param string $prompt    Instruction for the AI (default: "Describe this image")
     * @param string $model     Model to use
     * 
     * @return array|null       Returns array containing 'description', 'image_url', 'model' on success
     * @throws Exception        If file is missing or API returns an error
     */
    public function analyze(string $imagePath, string $prompt = "Describe this image", string $model = "qwen-vision"): ?array
    {
        // Construct endpoint URL: base + prefix (/cv) + route (/analyze)
        $endpoint = $this->apiUrl . '/cv/analyze';

        $postData = [
            'prompt' => $prompt,
            'model' => $model
        ];

        // executeApiRequestWithImageJson automatically handles:
        // 1. Verifying file existence
        // 2. Creating the CURLFile object (as 'file')
        // 3. Sending the POST request
        // 4. Decoding the JSON response
        return $this->executeApiRequestWithImageJson($endpoint, $imagePath, $postData);
    }

    /**
     * Check the health of the CV service
     * 
     * @return array|null Response containing service status and providers
     */
    public function health(): ?array
    {
        $endpoint = $this->apiUrl . '/cv/health';
        $response = $this->executeGetRequest($endpoint);
        return json_decode($response, true);
    }
}
