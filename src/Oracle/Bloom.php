<?php
namespace App\Oracle;

class Bloom {
    private $pyApiBaseUrl;

    public function __construct(string $pyApiBaseUrl = 'http://127.0.0.1:8009') {
        $this->pyApiBaseUrl = rtrim($pyApiBaseUrl, '/');
    }

    /**
     * Generates a Bloom filter hint by calling the Python service.
     *
     * @param array $dictionaryIds An array of integer dictionary IDs.
     * @param int $numWords The number of words to sample.
     * @param float $errorRate The desired error rate for the filter.
     * @param int|null $seed An optional seed for reproducibility.
     * @return array|null The decoded JSON response from the service, or null on failure.
     * @throws \Exception If the request fails or returns an error.
     */
    public function generateHint(array $dictionaryIds, int $numWords = 200, float $errorRate = 0.01, ?int $seed = null): ?array {
        $endpoint = $this->pyApiBaseUrl . '/bloom/generate';
        
        $payload = [
            'dictionary_ids' => array_map('intval', $dictionaryIds),
            'num_words' => $numWords,
            'error_rate' => $errorRate,
        ];

        if ($seed !== null) {
            $payload['seed'] = $seed;
        }

        $options = [
            'http' => [
                'header'  => "Content-Type: application/json\r\n",
                'method'  => 'POST',
                'content' => json_encode($payload),
                'timeout' => 15, // 15 second timeout
                'ignore_errors' => true // To read response body on 4xx/5xx errors
            ],
        ];

        $context  = stream_context_create($options);
        $response = @file_get_contents($endpoint, false, $context);

        if ($response === false) {
            throw new \Exception("Failed to connect to the Bloom Oracle service at {$endpoint}.");
        }

        $statusCode = $this->parseHttpStatusCode($http_response_header);
        $data = json_decode($response, true);

        if ($statusCode >= 400) {
            $errorMessage = $data['detail'] ?? 'An unknown error occurred.';
            throw new \Exception("Bloom Oracle service returned an error (HTTP {$statusCode}): {$errorMessage}");
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Failed to decode JSON response from Bloom Oracle service.");
        }

        return $data;
    }

    /**
     * Helper to extract HTTP status code from response headers.
     */
    private function parseHttpStatusCode(array $headers): int {
        if (empty($headers[0])) return 0;
        preg_match('{HTTP\/\S*\s(\d{3})}', $headers[0], $match);
        return isset($match[1]) ? (int)$match[1] : 0;
    }
}

