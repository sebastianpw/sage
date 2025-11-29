<?php
namespace App\Core;

use CURLFile;
use Exception;

/**
 * Base class for Python API proxy services
 */
abstract class PyApiProxy
{
    protected string $apiUrl;
    protected SpwBase $spw;

    public function __construct(string $apiUrl = 'http://127.0.0.1:8009')
    {
        $this->apiUrl = rtrim($apiUrl, '/');
        $this->spw = SpwBase::getInstance();
    }



/**
 * Debug method to log API requests
 */
protected function debugApiRequest(string $endpoint, array $postData): void
{
    /*
    $debugInfo = [
        'endpoint' => $endpoint,
        'post_data' => $postData,
        'timestamp' => date('Y-m-d H:i:s')
    ];

    // Log to file
    $logFile = $this->spw->getProjectPath() . '/public/temp_image_tests/api_debug.log';
    file_put_contents($logFile, json_encode($debugInfo, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);
     */

    // Also log curl command for testing
    $curlCmd = "curl -X POST ";
    foreach ($postData as $key => $value) {
        if ($value instanceof CURLFile) {
            $curlCmd .= "-F '{$key}=@{$value->getFilename()}' ";
        } else {
            $curlCmd .= "-F '{$key}={$value}' ";
        }
    }
    $curlCmd .= $endpoint;
    /*
        file_put_contents($logFile, "CURL: " . $curlCmd . "\n\n", FILE_APPEND);
     */
}





/**
 * Execute an API request with a single image file upload
 */
protected function executeApiRequestWithImage(string $endpoint, string $sourceImagePath, array $postData = []): ?string
{
    if (!file_exists($sourceImagePath)) {
        throw new Exception("Source image not found at: {$sourceImagePath}");
    }
    
    $postData['file'] = new CURLFile(
        $sourceImagePath, 
        mime_content_type($sourceImagePath), 
        basename($sourceImagePath)
    );

    return $this->executeCurlRequest($endpoint, $postData);
}


    /**
     * Execute an API request with a single image file upload
     *
    protected function executeApiRequestWithImage(string $endpoint, string $sourceImagePath, array $postData = []): ?string
    {
        if (!file_exists($sourceImagePath)) {
            throw new Exception("Source image not found at: {$sourceImagePath}");
        }
        
        $postData['image'] = new CURLFile(
            $sourceImagePath, 
            mime_content_type($sourceImagePath), 
            basename($sourceImagePath)
        );

        return $this->executeCurlRequest($endpoint, $postData);
    }
     */


    /**
     * Execute an API request with multiple image files
     */
    protected function executeApiRequestWithImages(string $endpoint, array $imagePaths, array $postData = []): ?string
    {
        foreach ($imagePaths as $key => $path) {
            if (!file_exists($path)) {
                throw new Exception("Image not found at: {$path}");
            }
            $postData[$key] = new CURLFile(
                $path,
                mime_content_type($path),
                basename($path)
            );
        }

        return $this->executeCurlRequest($endpoint, $postData);
    }

    /**
     * Execute an API request without file upload (for info/health endpoints)
     */
    protected function executeApiRequest(string $endpoint, array $postData = []): ?string
    {
        return $this->executeCurlRequest($endpoint, $postData);
    }

    /**
     * Execute an API request with a single image and return JSON decoded response
     */
    protected function executeApiRequestWithImageJson(string $endpoint, string $sourceImagePath, array $postData = []): ?array
    {
        $response = $this->executeApiRequestWithImage($endpoint, $sourceImagePath, $postData);
        
        if ($response === null) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to decode JSON response: " . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Execute an API request with multiple images and return JSON decoded response
     */
    protected function executeApiRequestWithImagesJson(string $endpoint, array $imagePaths, array $postData = []): ?array
    {
        $response = $this->executeApiRequestWithImages($endpoint, $imagePaths, $postData);
        
        if ($response === null) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to decode JSON response: " . json_last_error_msg());
        }

        return $decoded;
    }







/**
 * Core cURL execution logic
 */
private function executeCurlRequest(string $endpoint, array $postData): ?string
{
    // Debug the request
    $this->debugApiRequest($endpoint, $postData);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);

    $responseBody = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    /*
    // Log response
    $logFile = $this->spw->getProjectPath() . '/public/temp_image_tests/api_debug.log';
    file_put_contents($logFile, "Response HTTP: {$httpCode}\n", FILE_APPEND);
    file_put_contents($logFile, "Response Body: {$responseBody}\n\n", FILE_APPEND);
     */

    if ($error) {
        throw new Exception("cURL Error calling Python API: {$error}");
    }




if ($httpCode !== 200) {
    $errorDetails = json_decode($responseBody, true);
    // FIX: Handle case where detail might be an array
    if (is_array($errorDetails['detail'] ?? null)) {
        $message = json_encode($errorDetails['detail']);
    } else {
        $message = $errorDetails['detail'] ?? $responseBody;
    }
    throw new Exception("Python API returned HTTP {$httpCode}: {$message}");
}



    return $responseBody;
}







    /**
     * Core cURL execution logic
     *
    private function executeCurlRequest(string $endpoint, array $postData): ?string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error calling Python API: {$error}");
        }

        if ($httpCode !== 200) {
            $errorDetails = json_decode($responseBody, true);
            $message = $errorDetails['detail'] ?? $responseBody;
            throw new Exception("Python API returned HTTP {$httpCode}: {$message}");
        }

        return $responseBody;
    }
     */

    /**
     * Execute a GET request (for health checks, etc.)
     */
    protected function executeGetRequest(string $endpoint): ?string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("cURL Error calling Python API: {$error}");
        }

        if ($httpCode !== 200) {
            throw new Exception("Python API returned HTTP {$httpCode}");
        }

        return $responseBody;
    }
}
