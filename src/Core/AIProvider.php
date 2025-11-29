<?php

namespace App\Core;

/**
 * AIProvider - Centralized AI Communication Module
 * 
 * Provides unified interface for communicating with various AI providers:
 * - Cohere API
 * - Mistral API
 * - Groq API
 * - Pollinations API
 * - Google Gemini (Generative Language API) — minimal integration
 * 
 * Handles authentication, error handling, and response parsing for all providers.
 */
class AIProvider
{
    // Add this after the other model constants
    public const DEFAULT_MODEL = 'openai';

    // Known Groq model identifiers
    private const GROQ_MODELS = [
        'llama-3.3-70b-versatile',
        'openai/gpt-oss-120b',
        'allam-2-7b',
        'moonshotai/kimi-k2-instruct-0905',
        'qwen/qwen3-32b',
        'llama-3.1-8b-instant',
        'groq/compound',
        'moonshotai/kimi-k2-instruct',
        'meta-llama/llama-4-scout-17b-16e-instruct',
        'groq/compound-mini',
        'openai/gpt-oss-20b',
        'meta-llama/llama-4-maverick-17b-128e-instruct',
    ];

    private const QWEN_MODELS = [
        'qwen/qwen2-7b-instruct-awq',
        'qwen/Qwen2.5-7B-Instruct-AWQ',
        'qwen/Qwen2.5-14B-Instruct-AWQ',
        'qwen/Qwen2.5-32B-Instruct-AWQ',
        'qwen/Qwen2.5-Coder-14B-Instruct-AWQ',
        'internlm/internlm2-chat-7b-4bits',
        'internlm/internlm2-chat-20b-4bits',
        'internlm/internlm2_5-20b-chat-4bit-awq',
    ];
    
    private const POLLINATIONS_FREE_MODELS = [
        'openai',
        'openai-fast',
        'chickytutor',
    ];

    private const POLLINATIONS_MODELS = [
        'mistral',
        'openai-large',
        'openai-reasoning',
        'roblox-rp',
        'rtist',
    ];

    private const GEMINI_MODELS = [
        'gemini-3-pro-preview',
        'gemini-2.5-pro',
        'gemini-2.5-flash',
        'gemini-2.5-flash-lite',
        'gemini-2.0-flash',
        'gemini-2.0-flash-lite',
        'gemini-2.0-pro-exp',
    ];

    private const ANTHROPIC_MODELS = [
        'claude-haiku-4-5-20251001',
        'claude-sonnet-4-5-20250929',
        'claude-opus-4-1-20250805',
        'claude-opus-4-20250514',
        'claude-sonnet-4-20250514',
        'claude-3-7-sonnet-20250219',
        'claude-3-5-haiku-20241022',
        'claude-3-haiku-20240307',
    ];

    private const MISTRAL_MODELS = [
        'codestral-2508',
        'mistral-large-2411',
        'mistral-medium-2508',
        'mistral-small-2506',
        'magistral-medium-2509',
        'ministral-8b-2410',
    ];

    // New: Cohere model identifiers
    private const COHERE_MODELS = [
        'command-a-03-2025',
        'command-r7b-12-2024',
        'command-r-plus-08-2024',
        'command-a-reasoning-08-2025',
    ];

    private ?FileLogger $logger = null;

    /**
     * Get the default model for general use
     */
    public static function getDefaultModel(): string
    {
        return self::DEFAULT_MODEL;
    }

    public static function getAllModels(): array
    {
        $allConstants = array_merge(
            self::POLLINATIONS_FREE_MODELS,
            self::GEMINI_MODELS,
            self::MISTRAL_MODELS,
            //self::ANTHROPIC_MODELS,
            self::GROQ_MODELS,
            self::COHERE_MODELS,
            self::POLLINATIONS_MODELS,
            self::QWEN_MODELS
        );

        return $allConstants;
    }
    
    public function __construct(?FileLogger $logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Return the canonical model catalog used by the UI and CLI.
     */
    public static function getModelCatalog(): array
    {
        // Humanize short name helper
        $humanize = function (string $id): string {
            $base = preg_replace('#^.*/#', '', $id);
            $base = str_replace(['-', '_'], ' ', $base);
            $prefix = strstr($id, '/', true);
            $label = trim(($prefix ? ucfirst($prefix) . ' ' : '') . $base);
            $label = preg_replace('/\s+/', ' ', $label);
            return mb_convert_case($label, MB_CASE_TITLE);
        };
        
        $cohere = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::COHERE_MODELS);
        $mistral = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::MISTRAL_MODELS);
        $anthropic = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::ANTHROPIC_MODELS);
        $groq = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::GROQ_MODELS);
        $qwen_local = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::QWEN_MODELS);
        $pollinations_seed = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::POLLINATIONS_MODELS);
        $pollinations_free = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::POLLINATIONS_FREE_MODELS);
        $gemini_text = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::GEMINI_MODELS);

        return [
            'Pollinations API - Free' => $pollinations_free,
            'Gemini Text / Coding Models' => $gemini_text,
            'Mistral API' => $mistral,
            //'Anthropic (Claude)' => $anthropic,
            'Groq API' => $groq,
            'Cohere API' => $cohere,
            'Pollinations API - Seed' => $pollinations_seed,
            'Qwen / InternLM (Local via zrok)' => $qwen_local
        ];
    }
    
    /**
     * Main entry point: Send a message to an AI model and get a response
     */
    public function sendMessage(string $model, array $messages, array $options = []): string
    {
        $this->log('info', 'AI request', [
            'model' => $model,
            'message_count' => count($messages),
            'options' => $options
        ]);

        // Route to appropriate provider based on model
        if ($this->isCohereModel($model)) {
            return $this->sendToCohereApi($model, $messages, $options);
        } elseif ($this->isMistralModel($model)) {
            return $this->sendToMistralApi($model, $messages, $options);
        } elseif ($this->isGroqModel($model)) {
            return $this->sendToGroqApi($model, $messages, $options);
        } elseif ($this->isAnthropicModel($model)) {
            return $this->sendToAnthropicApi($model, $messages, $options);
        } elseif ($this->isGoogleModel($model)) {
            return $this->sendToGoogleApi($model, $messages, $options);
        } elseif ($this->isQwenLocalModel($model)) {
            return $this->sendToQwenLocal($model, $messages, $options);
        } else {
            return $this->sendToPollinationsApi($model, $messages, $options);
        }
    }
        
    /**
     * Convenience method: Send a simple text prompt to a model
     */
    public function sendPrompt(string $model, string $prompt, ?string $systemPrompt = null, array $options = []): string
    {
        $messages = [];
        if ($systemPrompt !== null) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $prompt];
        return $this->sendMessage($model, $messages, $options);
    }
    
    // ============================================================================
    // PROVIDER DETECTION
    // ============================================================================
    
    private function isGroqModel(string $model): bool
    {
        if (stripos($model, 'groq') !== false) return true;
        foreach (self::GROQ_MODELS as $m) {
            if (strcasecmp($m, $model) === 0) return true;
        }
        return false;
    }

    private function isGoogleModel(string $model): bool
    {
        $m = strtolower($model);
        if (strpos($m, 'gemini') !== false || strpos($m, 'google') !== false) return true;
        if (in_array($m, ['google', 'gemini'], true)) return true;
        return false;
    }

    private function isAnthropicModel(string $model): bool
    {
        foreach (self::ANTHROPIC_MODELS as $m) {
            if (strcasecmp($m, $model) === 0) return true;
        }
        $lower = strtolower($model);
        return (strpos($lower, 'claude') !== false || strpos($lower, 'anthropic') !== false);
    }

    private function isQwenLocalModel(string $model): bool
    {
        $m = strtolower($model);
        if ((strpos($m, 'qwen') !== false && strpos($m, 'awq') !== false) || strpos($m, 'internlm') !== false) {
            return true;
        }
        foreach (self::QWEN_MODELS as $qm) {
            if (strcasecmp($qm, $model) === 0) return true;
        }
        return false;
    }

    private function isMistralModel(string $model): bool
    {
        foreach (self::MISTRAL_MODELS as $m) {
            if (strcasecmp($m, $model) === 0) return true;
        }
        $lowerModel = strtolower($model);
        return (strpos($lowerModel, 'mistral-') === 0 || strpos($lowerModel, 'codestral-') === 0);
    }

    private function isCohereModel(string $model): bool
    {
        foreach (self::COHERE_MODELS as $m) {
            if (strcasecmp($m, $model) === 0) return true;
        }
        return (stripos($model, 'command') !== false);
    }

    private function isPollinationsFreeModel(string $model): bool
    {
        return in_array(strtolower($model), array_map('strtolower', self::POLLINATIONS_FREE_MODELS), true);
    }

    // ============================================================================
    // COHERE API PROVIDER
    // ============================================================================
    
    private function sendToCohereApi(string $model, array $messages, array $options): string
    {
        $endpoint = getenv('COHERE_ENDPOINT') ?: 'https://api.cohere.ai/compatibility/v1/chat/completions';
        $apiKey = $this->getCohereApiKey();
        if (empty($apiKey)) {
            throw new \RuntimeException('COHERE_API_KEY not found.');
        }

        $payload = ['model' => $model, 'messages' => $messages];
        if (isset($options['temperature'])) $payload['temperature'] = (float)$options['temperature'];
        if (isset($options['max_tokens'])) $payload['max_tokens'] = (int)$options['max_tokens'];

        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
        $response = $this->executeCurlRequest($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, 'Cohere');

        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        throw new \RuntimeException('No usable reply from Cohere API.');
    }

    private function getCohereApiKey(): ?string
    {
        return getenv('COHERE_API_KEY') ?: $this->readTokenFromHome(['.cohere_api_key', '.coheretoken']);
    }

    // ============================================================================
    // MISTRAL API PROVIDER
    // ============================================================================
    
    private function sendToMistralApi(string $model, array $messages, array $options): string
    {
        $endpoint = getenv('MISTRAL_ENDPOINT') ?: 'https://api.mistral.ai/v1/chat/completions';
        $apiKey = $this->getMistralApiKey();
        if (empty($apiKey)) {
            throw new \RuntimeException('MISTRAL_API_KEY not found.');
        }

        $payload = ['model' => $model, 'messages' => $messages];
        if (isset($options['temperature'])) $payload['temperature'] = (float)$options['temperature'];
        if (isset($options['max_tokens'])) $payload['max_tokens'] = (int)$options['max_tokens'];

        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
        $response = $this->executeCurlRequest($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, 'Mistral');
        
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        throw new \RuntimeException('No usable reply from Mistral API.');
    }

    private function getMistralApiKey(): ?string
    {
        return getenv('MISTRAL_API_KEY') ?: $this->readTokenFromHome(['.mistral_api_key', '.mistraltoken']);
    }

    // ============================================================================
    // Anthropic Claude
    // ============================================================================
    private function sendToAnthropicApi(string $model, array $messages, array $options): string
    {
        $endpoint = getenv('ANTHROPIC_ENDPOINT') ?: 'https://api.anthropic.com/v1/complete';
        $apiKey = $this->getAnthropicApiKey();
        if (empty($apiKey)) {
            throw new \RuntimeException('ANTHROPIC_API_KEY not found.');
        }

        $promptParts = [];
        foreach ($messages as $m) {
            $role = ucfirst($m['role'] ?? 'user');
            $promptParts[] = "{$role}: {$m['content']}";
        }
        $prompt = trim(implode("\n\n", $promptParts)) . "\n\nAssistant:";

        $payload = ['model' => $model, 'prompt' => $prompt];
        if (isset($options['temperature'])) $payload['temperature'] = (float)$options['temperature'];
        if (isset($options['max_tokens'])) $payload['max_tokens_to_sample'] = (int)$options['max_tokens'];

        $headers = ['Content-Type: application/json', 'x-api-key: ' . $apiKey, 'anthropic-version: 2023-06-01'];
        $response = $this->executeCurlRequest($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, 'Anthropic');

        $data = json_decode($response, true);
        if (isset($data['completion']) && is_string($data['completion'])) {
            return $data['completion'];
        }
        throw new \RuntimeException('No usable reply from Anthropic.');
    }

    private function getAnthropicApiKey(): ?string
    {
        return getenv('ANTHROPIC_API_KEY') ?: $this->readTokenFromHome(['.claude_api_key', '.anthropic_api_key']);
    }

    // ============================================================================
    // GROQ API PROVIDER
    // ============================================================================
    
    private function sendToGroqApi(string $model, array $messages, array $options): string
    {
        $endpoint = getenv('GROQ_ENDPOINT') ?: 'https://api.groq.com/openai/v1/chat/completions';
        $apiKey = $this->getGroqApiKey();
        if (empty($apiKey)) {
            throw new \RuntimeException('GROQ_API_KEY not found.');
        }
        
        $payload = ['model' => $model, 'messages' => $messages];
        if (isset($options['temperature'])) $payload['temperature'] = (float)$options['temperature'];
        if (isset($options['max_tokens'])) $payload['max_tokens'] = (int)$options['max_tokens'];
        
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
        $response = $this->executeCurlRequest($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, 'Groq');
        
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        throw new \RuntimeException('No usable reply from Groq API.');
    }
    
    private function getGroqApiKey(): ?string
    {
        return getenv('GROQ_API_KEY') ?: $this->readTokenFromHome(['.groq_api_key', '.groqtoken']);
    }
    
    // ============================================================================
    // POLLINATIONS API PROVIDER (Default)
    // ============================================================================
    
    private function sendToPollinationsApi(string $model, array $messages, array $options): string
    {
        $isFreeModel = $this->isPollinationsFreeModel($model);
        $endpoint = $isFreeModel
            ? 'https://text.pollinations.ai/v1/chat/completions'
            : 'https://enter.pollinations.ai/api/generate/v1/chat/completions';
        
        $apiKey = $this->getPollinationsApiKey();
        if (empty($apiKey) && !$isFreeModel) {
            throw new \RuntimeException('No Pollinations API key found for seed model.');
        }
        
        $payload = ['model' => $model, 'messages' => $messages];
        
        // ===== MODIFICATION START =====
        // Only add optional parameters for non-free (seed) models, as free models
        // may not support them and could cause errors.
        if (!$isFreeModel) {
            if (isset($options['temperature'])) {
                $payload['temperature'] = (float)$options['temperature'];
            }
            if (isset($options['max_tokens'])) {
                $payload['max_tokens'] = (int)$options['max_tokens'];
            }
        }
        // ===== MODIFICATION END =====
        
        $headers = ['Content-Type: application/json'];
        if (!empty($apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }
        
        $response = $this->executeCurlRequest($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, 'Pollinations');
        
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        throw new \RuntimeException('Pollinations returned no usable content.');
    }
    
    private function getPollinationsApiKey(): ?string
    {
        return getenv('POLLINATIONS_API_KEY') ?: $this->readTokenFromHome(['.pollinationsaitoken']);
    }

    // ============================================================================
    // QWEN / COLAB FASTAPI PROVIDER
    // ============================================================================

    private function getZrokTunnelUrl(): string
    {
        $scriptPath = \App\Core\SpwBase::getInstance()->getProjectPath() . '/bash/zrok_echo.sh';
        return trim(shell_exec('sh ' . escapeshellarg($scriptPath) . ' 2>&1'));
    }

    private function sendToQwenLocal(string $model, array $messages, array $options): string
    {
        $endpoint = $this->getZrokTunnelUrl() . '/v1/chat/completions';
        $apiKey = $this->getQwenApiKey();
        if (empty($apiKey)) {
            throw new \RuntimeException('No Qwen API key found.');
        }

        $payload = ['model' => $model, 'messages' => $messages];
        if (isset($options['temperature'])) $payload['temperature'] = (float)$options['temperature'];
        if (isset($options['max_tokens'])) $payload['max_tokens'] = (int)$options['max_tokens'];

        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
        $response = $this->executeCurlRequest($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, 'Qwen');

        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        throw new \RuntimeException('Qwen FastAPI endpoint returned no usable content.');
    }

    private function getQwenApiKey(): ?string
    {
        return getenv('QWEN_API_KEY') ?: $this->readTokenFromHome(['.qwentoken']);
    }

    // ============================================================================
    // GOOGLE GEMINI (Generative Language) PROVIDER
    // ============================================================================
    
    private function sendToGoogleApi(string $model, array $messages, array $options): string
    {
        $lower = strtolower($model);
        if ($lower === 'google' || $lower === 'gemini') {
            $model = 'gemini-2.5-flash';
        }

        $endpointBase = getenv('GEMINI_ENDPOINT') ?: 'https://generativelanguage.googleapis.com/v1beta/models';
        $endpoint = rtrim($endpointBase, '/') . '/' . $model . ':generateContent';

        $apiKey = getenv('GEMINI_API_KEY') ?: $this->readTokenFromHome(['.gemini_api_key']);
        if (empty($apiKey)) {
            throw new \RuntimeException('GEMINI_API_KEY not found.');
        }

        $contents = [];
        // Gemini API has a specific structure for multi-turn conversations
        foreach ($messages as $m) {
            $role = ($m['role'] === 'assistant') ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $m['content']]]
            ];
        }

        $payload = ['contents' => $contents];

        // ===== MODIFICATION START =====
        // Gemini requires parameters to be nested inside a 'generationConfig' object.
        $generationConfig = [];
        if (isset($options['temperature'])) {
            $generationConfig['temperature'] = (float)$options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            // The parameter name is different for Gemini.
            $generationConfig['maxOutputTokens'] = (int)$options['max_tokens'];
        }
        
        // Only add the generationConfig to the payload if it contains values.
        if (!empty($generationConfig)) {
            $payload['generationConfig'] = $generationConfig;
        }
        // ===== MODIFICATION END =====

        $headers = ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey];
        $response = $this->executeCurlRequest($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, 'GoogleGemini');

        $data = json_decode($response, true);
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return $data['candidates'][0]['content']['parts'][0]['text'];
        }
        throw new \RuntimeException('No usable reply from Google Gemini.');
    }

    // ============================================================================
    // SHARED UTILITIES
    // ============================================================================
    
    private function executeCurlRequest(string $endpoint, string $payload, array $headers, string $providerName): string
    {
        // Add a default User-Agent header if not already present
        if (!in_array('User-Agent', array_map(fn($h) => explode(':', $h, 2)[0], $headers))) {
            $headers[] = 'User-Agent: spw-aiprovider/1.0';
        }

        $this->log('info', "{$providerName} API request", [
            'endpoint' => $endpoint,
            'payload_preview' => mb_substr($payload, 0, 1000)
        ]);
        
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        
        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->log('info', "{$providerName} API response", [
            'http_code' => $httpCode,
            'curl_error' => $curlErr ?: null,
            'response_length' => is_string($response) ? strlen($response) : 0
        ]);
        
        if ($response === false) {
            throw new \RuntimeException("Curl error calling {$providerName}: {$curlErr}");
        }
        
        if ($httpCode < 200 || $httpCode >= 300) {
            $snippet = mb_substr($response, 0, 2000);
            throw new \RuntimeException("{$providerName} returned HTTP {$httpCode}: {$snippet}");
        }
        
        return $response;
    }
    
    private function readTokenFromHome(array $candidateFilenames): ?string
    {
		$home = defined('PROJECT_ROOT') ? PROJECT_ROOT . '/token' : null;
        if (!$home) {
            // Fallback for different execution contexts
            $home = realpath(__DIR__ . '/../../token');
        }

        if (!$home) return null;
        
        foreach ($candidateFilenames as $fname) {
            $path = $home . '/' . $fname;
            if (is_readable($path)) {
                $content = trim(file_get_contents($path));
                if ($content !== '') return $content;
            }
        }
        
        return null;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger === null) return;
        
        $method = strtolower($level);
        if (method_exists($this->logger, $method)) {
            $this->logger->$method([$message => $context]);
        }
    }
}

