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
        'gemini-2.5-pro',
        'gemini-2.5-flash',
        'gemini-2.5-flash-lite',
        'gemini-2.0-flash',
        'gemini-2.0-flash-lite',
        'gemini-2.0-pro-exp',
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
     *
     * Structure:
     * [
     *   "Group Label" => [
     *       ['id' => 'model-id', 'name' => 'Human readable name'],
     *       ...
     *   ],
     *   ...
     * ]
     */
    public static function getModelCatalog(): array
    {
        // Humanize short name helper
        $humanize = function (string $id): string {
            // prefer the part after last slash as base name
            $base = preg_replace('#^.*/#', '', $id);
            $base = str_replace(['-', '_'], ' ', $base);
            // prefix with provider if slash present (e.g. groq/compound -> Groq Compound)
            $prefix = strstr($id, '/', true);
            $label = trim(($prefix ? ucfirst($prefix) . ' ' : '') . $base);
            // collapse spaces and title-case
            $label = preg_replace('/\s+/', ' ', $label);
            return mb_convert_case($label, MB_CASE_TITLE);
        };
        
        $cohere = array_map(function ($m) use ($humanize) {
            return ['id' => $m, 'name' => $humanize($m)];
        }, self::COHERE_MODELS);
        
        $mistral = array_map(function ($m) use ($humanize) {
            return ['id' => $m, 'name' => $humanize($m)];
        }, self::MISTRAL_MODELS);

        $groq = array_map(function ($m) use ($humanize) {
            return ['id' => $m, 'name' => $humanize($m)];
        }, self::GROQ_MODELS);

        $qwen_local = array_map(function ($m) use ($humanize) {
            return ['id' => $m, 'name' => $humanize($m)];
        }, self::QWEN_MODELS);

        $pollinations_seed = array_map(function ($m) use ($humanize) {
            return ['id' => $m, 'name' => $humanize($m)];
        }, self::POLLINATIONS_MODELS);

        $pollinations_free = array_map(function ($m) use ($humanize) {
            return ['id' => $m, 'name' => $humanize($m)];
        }, self::POLLINATIONS_FREE_MODELS);

        $gemini_text = array_map(function ($m) use ($humanize) {
            return ['id' => $m, 'name' => $humanize($m)];
        }, self::GEMINI_MODELS);

        return [
            'Pollinations API - Free' => $pollinations_free,
            'Gemini Text / Coding Models' => $gemini_text,
            'Mistral API' => $mistral,
            'Groq API' => $groq,
            'Cohere API' => $cohere,
            'Pollinations API - Seed' => $pollinations_seed,
            'Qwen / InternLM (Local via zrok)' => $qwen_local
        ];
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
        if ($this->isCohereModel($model)) {
            return $this->sendToCohereApi($model, $messages, $options);
        } elseif ($this->isMistralModel($model)) {
            return $this->sendToMistralApi($model, $messages, $options);
        } elseif ($this->isGroqModel($model)) {
            return $this->sendToGroqApi($model, $messages, $options);
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

    private function isGoogleModel(string $model): bool
    {
        $m = strtolower($model);
        if (strpos($m, 'gemini') !== false || strpos($m, 'google') !== false || strpos($m, 'generativelanguage') !== false) {
            return true;
        }
        if (in_array($m, ['google', 'gemini'], true)) {
            return true;
        }
        return false;
    }

    private function isQwenLocalModel(string $model): bool
    {
        $m = strtolower($model);

        if ((strpos($m, 'qwen') !== false && strpos($m, 'awq') !== false)
            || strpos($m, 'internlm') !== false
        ) {
            return true;
        }

        foreach (self::QWEN_MODELS as $qm) {
            if (strcasecmp($qm, $model) === 0) {
                return true;
            }
        }

        return false;
    }

    private function isMistralModel(string $model): bool
    {
        foreach (self::MISTRAL_MODELS as $m) {
            if (strcasecmp($m, $model) === 0) {
                return true;
            }
        }
    
        $lowerModel = strtolower($model);
        if (strpos($lowerModel, 'mistral-') === 0 || strpos($lowerModel, 'codestral-') === 0) {
            return true;
        }
    
        return false;
    }

    /**
     * New: Detect Cohere AI model identifiers.
     */
    private function isCohereModel(string $model): bool
    {
        // Check against the specific list of known models first
        foreach (self::COHERE_MODELS as $m) {
            if (strcasecmp($m, $model) === 0) {
                return true;
            }
        }
        
        // Additionally, check for the common "command" prefix
        if (stripos($model, 'command') !== false) {
            return true;
        }
        
        return false;
    }

    /**
     * Detect whether a Pollinations model is in the free/no-auth list.
     */
    private function isPollinationsFreeModel(string $model): bool
    {
        $m = strtolower($model);
        $free = array_map('strtolower', self::POLLINATIONS_FREE_MODELS);
        return in_array($m, $free, true);
    }


    // ============================================================================
    // COHERE API PROVIDER
    // ============================================================================
    
    private function sendToCohereApi(string $model, array $messages, array $options): string
    {
        $endpoint = getenv('COHERE_ENDPOINT') ?: 'https://api.cohere.ai/compatibility/v1/chat/completions';
        $apiKey = $this->getCohereApiKey();

        if (empty($apiKey)) {
            throw new \RuntimeException('COHERE_API_KEY not found. Set COHERE_API_KEY or place token in ~/.cohere_api_key');
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

        $response = $this->executeCurlRequest($endpoint, $payloadJson, $headers, 'Cohere');

        $data = json_decode($response, true);
        if (is_array($data) && isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }

        $jsonCandidate = $this->extractFirstJsonObject($response);
        if ($jsonCandidate !== null) {
            return $jsonCandidate;
        }

        throw new \RuntimeException('No usable reply from Cohere API. Raw response start: ' . mb_substr($response, 0, 2000));
    }

    private function getCohereApiKey(): ?string
    {
        $envKey = getenv('COHERE_API_KEY') ?: (isset($_SERVER['COHERE_API_KEY']) ? $_SERVER['COHERE_API_KEY'] : null);

        if ($envKey) {
            return $envKey;
        }

        return $this->readTokenFromHome(['.cohere_api_key', '.coheretoken']);
    }

    // ============================================================================
    // MISTRAL API PROVIDER
    // ============================================================================
    
    private function sendToMistralApi(string $model, array $messages, array $options): string
    {
        $endpoint = getenv('MISTRAL_ENDPOINT') ?: 'https://api.mistral.ai/v1/chat/completions';
        $apiKey = $this->getMistralApiKey();

        if (empty($apiKey)) {
            throw new \RuntimeException('MISTRAL_API_KEY not found. Set MISTRAL_API_KEY or place token in ~/.mistral_api_key');
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

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

        $response = $this->executeCurlRequest($endpoint, $payloadJson, $headers, 'Mistral');

        $data = json_decode($response, true);
        if (is_array($data) && isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }

        $jsonCandidate = $this->extractFirstJsonObject($response);
        if ($jsonCandidate !== null) {
            return $jsonCandidate;
        }

        throw new \RuntimeException('No usable reply from Mistral API. Raw response start: ' . mb_substr($response, 0, 2000));
    }

    private function getMistralApiKey(): ?string
    {
        $envKey = getenv('MISTRAL_API_KEY') ?: (isset($_SERVER['MISTRAL_API_KEY']) ? $_SERVER['MISTRAL_API_KEY'] : null);

        if ($envKey) {
            return $envKey;
        }

        return $this->readTokenFromHome(['.mistral_api_key', '.mistraltoken']);
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

        $isFreeModel = $this->isPollinationsFreeModel($model);
        
        if (empty($apiKey) && !$isFreeModel) {
            throw new \RuntimeException('No Pollinations API key found. Set POLLINATIONS_API_KEY or place token in ~/.pollinationsaitoken');
        }
        
        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];
        
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
        ];

        // Only include Authorization header if we have an API key.
        if (!empty($apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }
        
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
    // QWEN / COLAB FASTAPI PROVIDER
    // ============================================================================

    private function getZrokTunnelUrl(): string
    {
        $scriptPath = \App\Core\SpwBase::getInstance()->getProjectPath() . '/bash/zrok_echo.sh';
        $output = trim(shell_exec('sh ' . escapeshellarg($scriptPath) . ' 2>&1'));
        return $output;
    }

    private function sendToQwenLocal(string $model, array $messages, array $options): string
    {
        $endpoint = $this->getZrokTunnelUrl() . '/v1/chat/completions'; // append route for your FastAPI endpoint
        $apiKey = $this->getQwenApiKey();

        if (empty($apiKey)) {
            throw new \RuntimeException('No Qwen API key found. Set QWEN_API_KEY or place token in ~/.qwentoken');
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

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

        $response = $this->executeCurlRequest($endpoint, $payloadJson, $headers, 'Qwen');

        $data = json_decode($response, true);
        if (is_array($data) && isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }

        $jsonCandidate = $this->extractFirstJsonObject($response);
        if ($jsonCandidate !== null) {
            return $jsonCandidate;
        }

        throw new \RuntimeException('Qwen FastAPI endpoint returned a response but no usable content found. Response start: ' . mb_substr($response, 0, 2000));
    }

    private function getQwenApiKey(): ?string
    {
        $envKey = getenv('QWEN_API_KEY') ?: (isset($_SERVER['QWEN_API_KEY']) ? $_SERVER['QWEN_API_KEY'] : null);

        if ($envKey) {
            return $envKey;
        }

        return $this->readTokenFromHome(['.qwentoken']);
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

        $apiKey = getenv('GEMINI_API_KEY') ?: (isset($_SERVER['GEMINI_API_KEY']) ? $_SERVER['GEMINI_API_KEY'] : null);
        if (empty($apiKey)) {
            $apiKey = $this->readTokenFromHome(['.gemini_api_key', '.google_gemini_key', '.gcloud_api_key']);
        }
        if (empty($apiKey)) {
            throw new \RuntimeException('GEMINI_API_KEY not found. Set GEMINI_API_KEY or place token in ~/.gemini_api_key');
        }

        $contents = [];
        if (count($messages) === 1 && isset($messages[0]['content'])) {
            $contents[] = ['parts' => [['text' => $messages[0]['content']]]];
        } else {
            $joined = '';
            foreach ($messages as $m) {
                $role = isset($m['role']) ? ucfirst($m['role']) : 'User';
                $text = isset($m['content']) ? $m['content'] : '';
                $joined .= "{$role}: {$text}\n\n";
            }
            $contents[] = ['parts' => [['text' => trim($joined)]]];
        }

        $payload = ['contents' => $contents];

        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            $payload['maxOutputTokens'] = (int)$options['max_tokens'];
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: spw-aiprovider/1.0',
            'x-goog-api-key: ' . $apiKey,
        ];

        $response = $this->executeCurlRequest($endpoint, $payloadJson, $headers, 'GoogleGemini');

        $data = json_decode($response, true);
        if (is_array($data)) {
            if (isset($data['candidates']) && is_array($data['candidates']) && count($data['candidates']) > 0) {
                $cand = $data['candidates'][0];
                if (isset($cand['content']['parts']) && is_array($cand['content']['parts'])) {
                    $parts = $cand['content']['parts'];
                    $texts = [];
                    foreach ($parts as $p) {
                        if (isset($p['text']) && is_string($p['text'])) {
                            $texts[] = $p['text'];
                        }
                    }
                    if (!empty($texts)) {
                        return implode("\n", $texts);
                    }
                }
                $found = $this->extractTextFromArray($cand);
                if ($found !== null) {
                    return $found;
                }
            }

            $found = $this->extractTextFromArray($data);
            if ($found !== null) {
                return $found;
            }
        }

        $jsonCandidate = $this->extractFirstJsonObject($response);
        if ($jsonCandidate !== null) {
            return $jsonCandidate;
        }

        throw new \RuntimeException('No usable reply from Google Gemini. Raw response start: ' . mb_substr($response, 0, 2000));
    }

    private function extractTextFromArray($value): ?string
    {
        if (is_string($value)) {
            return null;
        }

        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            if (array_key_exists('text', $value) && is_string($value['text']) && $value['text'] !== '') {
                return $value['text'];
            }
            foreach ($value as $k => $v) {
                if ($k === 'text' && is_string($v) && $v !== '') {
                    return $v;
                }
                $found = $this->extractTextFromArray($v);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
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
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
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