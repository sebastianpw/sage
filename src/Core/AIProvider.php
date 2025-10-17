<?php

namespace App\Core;

/**
 * AIProvider - Centralized AI Communication Module
 * 
 * Provides unified interface for communicating with various AI providers:
 * - Groq API
 * - Pollinations API
 * 
 * Handles authentication, error handling, and response parsing for all providers.
 */
class AIProvider
{
    // Known Groq model identifiers
    private const GROQ_MODELS = [
        'llama-3.3-70b-versatile',
        'meta-llama/llama-guard-4-12b',
        'playai-tts',
        'openai/gpt-oss-120b',
        'meta-llama/llama-prompt-guard-2-86m',
        'allam-2-7b',
        'moonshotai/kimi-k2-instruct-0905',
        'whisper-large-v3-turbo',
        'whisper-large-v3',
        'playai-tts-arabic',
        'meta-llama/llama-prompt-guard-2-22m',
        'qwen/qwen3-32b',
        'llama-3.1-8b-instant',
        'groq/compound',
        'moonshotai/kimi-k2-instruct',
        'meta-llama/llama-4-scout-17b-16e-instruct',
        'groq/compound-mini',
        'openai/gpt-oss-20b',
        'meta-llama/llama-4-maverick-17b-128e-instruct',
    ];
    
    private ?FileLogger $logger = null;
    
    public function __construct(?FileLogger $logger = null)
    {
        $this->logger = $logger;
    }
    
    /**
     * Main entry point: Send a message to an AI model and get a response
     * 
     * @param string $model Model identifier (e.g., 'groq/compound', 'gpt-5-nano')
     * @param array $messages Array of message objects with 'role' and 'content' keys
     * @param array $options Optional parameters (temperature, max_tokens, etc.)
     * @return string AI response content
     * @throws \RuntimeException on communication or parsing errors
     */
    public function sendMessage(string $model, array $messages, array $options = []): string
    {
        $this->log('info', 'AI request', [
            'model' => $model,
            'message_count' => count($messages),
            'options' => $options
        ]);
        
        // Route to appropriate provider based on model
        if ($this->isGroqModel($model)) {
            return $this->sendToGroqApi($model, $messages, $options);
        } else {
            return $this->sendToPollinationsApi($model, $messages, $options);
        }
    }
    
    /**
     * Convenience method: Send a simple text prompt to a model
     * 
     * @param string $model Model identifier
     * @param string $prompt The user prompt
     * @param string|null $systemPrompt Optional system prompt
     * @param array $options Optional parameters
     * @return string AI response
     */
    public function sendPrompt(string $model, string $prompt, ?string $systemPrompt = null, array $options = []): string
    {
        $messages = [];
        
        if ($systemPrompt !== null) {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt
            ];
        }
        
        $messages[] = [
            'role' => 'user',
            'content' => $prompt
        ];
        
        return $this->sendMessage($model, $messages, $options);
    }
    
    // ============================================================================
    // PROVIDER DETECTION
    // ============================================================================
    
    private function isGroqModel(string $model): bool
    {
        if (stripos($model, 'groq') !== false) {
            return true;
        }
        
        foreach (self::GROQ_MODELS as $m) {
            if (strcasecmp($m, $model) === 0) {
                return true;
            }
        }
        
        return false;
    }
    
    // ============================================================================
    // GROQ API PROVIDER
    // ============================================================================
    
    private function sendToGroqApi(string $model, array $messages, array $options): string
    {
        $endpoint = getenv('GROQ_ENDPOINT') ?: 'https://api.groq.com/openai/v1/chat/completions';
        $apiKey = $this->getGroqApiKey();
        
        if (empty($apiKey)) {
            throw new \RuntimeException('GROQ_API_KEY not found. Set GROQ_API_KEY or place token in ~/.groq_api_key');
        }
        
        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];
        
        // Add optional parameters
        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = $options['max_tokens'];
        }
        
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: spw-aiprovider/1.0',
            'Authorization: Bearer ' . $apiKey,
        ];
        
        $response = $this->executeCurlRequest($endpoint, $payloadJson, $headers, 'Groq');
        
        $data = json_decode($response, true);
        if (is_array($data) && isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        
        $jsonCandidate = $this->extractFirstJsonObject($response);
        if ($jsonCandidate !== null) {
            return $jsonCandidate;
        }
        
        throw new \RuntimeException('No usable reply from Groq API. Raw response start: ' . mb_substr($response, 0, 2000));
    }
    
    private function getGroqApiKey(): ?string
    {
        $envKey = getenv('GROQ_API_KEY') ?: (isset($_SERVER['GROQ_API_KEY']) ? $_SERVER['GROQ_API_KEY'] : null);
        
        if ($envKey) {
            return $envKey;
        }
        
        return $this->readTokenFromHome(['.groq_api_key', '.groqtoken', '.groq_apikey']);
    }
    
    // ============================================================================
    // POLLINATIONS API PROVIDER (Default)
    // ============================================================================
    
    private function sendToPollinationsApi(string $model, array $messages, array $options): string
    {
        $endpoint = getenv('POLLINATIONS_ENDPOINT') ?: 'https://text.pollinations.ai/v1/chat/completions';
        $apiKey = $this->getPollinationsApiKey();
        
        if (empty($apiKey)) {
            throw new \RuntimeException('No Pollinations API key found. Set POLLINATIONS_API_KEY or place token in ~/.pollinationsaitoken');
        }
        
        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];
        
        // Add optional parameters
        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = $options['max_tokens'];
        }
        
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: spw-aiprovider/1.0',
            'Authorization: Bearer ' . $apiKey,
        ];
        
        $response = $this->executeCurlRequest($endpoint, $payloadJson, $headers, 'Pollinations');
        
        $data = json_decode($response, true);
        if (is_array($data) && isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        
        $jsonCandidate = $this->extractFirstJsonObject($response);
        if ($jsonCandidate !== null) {
            return $jsonCandidate;
        }
        
        throw new \RuntimeException('Pollinations returned a response but no usable content found. Response start: ' . mb_substr($response, 0, 2000));
    }
    
    private function getPollinationsApiKey(): ?string
    {
        $envKey = getenv('POLLINATIONS_API_KEY') ?: (isset($_SERVER['POLLINATIONS_API_KEY']) ? $_SERVER['POLLINATIONS_API_KEY'] : null);
        
        if ($envKey) {
            return $envKey;
        }
        
        return $this->readTokenFromHome(['.pollinationsaitoken']);
    }
    
    // ============================================================================
    // SHARED UTILITIES
    // ============================================================================
    
    private function executeCurlRequest(string $endpoint, string $payload, array $headers, string $providerName): string
    {
        $this->log('info', "{$providerName} API request", [
            'endpoint' => $endpoint,
            'payload_preview' => mb_substr($payload, 0, 1000)
        ]);
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        
        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->log('info', "{$providerName} API response", [
            'http_code' => $httpCode,
            'curl_error' => $curlErr ?: null,
            'response_length' => is_string($response) ? strlen($response) : 0
        ]);
        
        if ($response === false || $curlErr) {
            throw new \RuntimeException("Curl error when calling {$providerName}: " . ($curlErr ?: 'unknown'));
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
            $snippet = is_string($response) ? mb_substr($response, 0, 2000) : '';
            throw new \RuntimeException("{$providerName} endpoint returned HTTP {$httpCode}: {$snippet}");
        }
        
        return $response;
    }
    
    private function readTokenFromHome(array $candidateFilenames): ?string
    {
	    //$home = getenv('HOME') ?: (isset($_SERVER['HOME']) ? $_SERVER['HOME'] : null);
	$home = PROJECT_ROOT . '/token';
        if (!$home) {
            return null;
        }
        
        foreach ($candidateFilenames as $fname) {
            $path = $home . '/' . $fname;
            if (is_readable($path)) {
                $content = trim(@file_get_contents($path));
                if ($content !== '') {
                    return $content;
                }
            }
        }
        
        return null;
    }
    
    private function extractFirstJsonObject(string $text): ?string
    {
        if (preg_match('/\{(?:[^{}]*|(?R))*\}/s', $text, $m)) {
            $candidate = $m[0];
            $decoded = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $candidate;
            }
        }
        return null;
    }
    
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger === null) {
            return;
        }
        
        $method = strtolower($level);
        if (method_exists($this->logger, $method)) {
            $this->logger->$method([$message => $context]);
        }
    }
}
