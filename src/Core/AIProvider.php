<?php

namespace App\Core;

/**
 * AIProvider - Centralized AI Communication Module
 * 
 * Provides unified interface for communicating with various AI providers:
 * - Cerebras API (New)
 * - Cohere API
 * - Mistral API
 * - Groq API
 * - OpenRouter API (New)
 * - Pollinations API
 * - Google Gemini (Generative Language API) — minimal integration
 * 
 * Handles authentication, error handling, and response parsing for all providers.
 *
 * NOTE: Local Ollama integration (LOCAL_MODELS) added non-invasively.
 */
class AIProvider
{
    // Add this after the other model constants
    public const DEFAULT_MODEL = 'openai';

    // Local Ollama-backed models (default suggestions / known local names)
    // You can extend this list or manage models dynamically via getLocalModels().
    private const LOCAL_MODELS = [
        'deepseek-r1:1.5b',
        'qwen2.5:0.5b',
        'qwen3:0.6b'
        // add more local model IDs you expect to run under Ollama here
    ];

    // Known Groq model identifiers
    // Restored Groq variants to run in parallel with Cerebras
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

    private const OPENROUTER_MODELS = [
        'openrouter/free',
        'tencent/hy3:free',
        'nvidia/nemotron-3-ultra-550b-a55b:free',
        'poolside/laguna-m.1:free',
        'nvidia/nemotron-3-super-120b-a12b:free',
        'cohere/north-mini-code:free',
        'poolside/laguna-xs-2.1:free',
        'nvidia/nemotron-3-nano-30b-a3b:free',
        'google/gemma-4-31b-it:free',
        'openai/gpt-oss-120b:free',
        'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free',
        'openai/gpt-oss-20b:free',
        'nvidia/nemotron-nano-9b-v2:free',
        'nvidia/nemotron-nano-12b-v2-vl:free',
        'nvidia/llama-nemotron-embed-vl-1b-v2:free',
        'google/gemma-4-26b-a4b-it:free',
    ];

    // New: Cerebras model identifiers
    // These keys differ slightly from Groq (no 'versatile' suffix or 'openai/' prefix)
    private const CEREBRAS_MODELS = [
        'qwen-3-235b-a22b-instruct-2507',
        'zai-glm-4.7',
        'gpt-oss-120b',
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

    // We dynamically fetch Pollinations models instead of hardcoding them
    private static ?array $pollinationsModelsCache = null;
    private static ?array $pollinationsFreeModelsCache = ['openai', 'openai-fast'];

    private static function getPollinationsModels(): array
    {
        if (self::$pollinationsModelsCache !== null) {
            return self::$pollinationsModelsCache;
        }

        self::$pollinationsModelsCache = [];
        
        try {
            // Updated to the current unified API endpoint for text models
            $ch = curl_init('https://gen.pollinations.ai/text/models');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
                $data = json_decode($response, true);
                if (is_array($data)) {
                    // Support standard array or OpenAI-compatible { "data": [...] }
                    $items = isset($data['data']) ? $data['data'] : $data;
                    foreach ($items as $item) {
                        if (is_array($item)) {
                            // The new catalog uses 'id' (e.g., publisher/model), fallback to 'name'
                            if (isset($item['id'])) {
                                self::$pollinationsModelsCache[] = $item['id'];
                            } elseif (isset($item['name'])) {
                                self::$pollinationsModelsCache[] = $item['name'];
                            }
                        } elseif (is_string($item)) {
                            self::$pollinationsModelsCache[] = $item;
                        }
                    }
                }
            }
        } catch (\Throwable $t) {
            // Fail silently and use fallback
        }

        if (empty(self::$pollinationsModelsCache)) {
            // Fallback to preserve existing functionality if API is unreachable
            self::$pollinationsModelsCache = [
                'nova-fast', 'claude-fable-5', 'claude-large', 'claude-opus-4.7', 'gemini-large',
                'gpt-5.5', 'gpt-5.4-mini', 'openai-large', 'openai-reasoning', 'minimax',
                'gemini', 'gemini-search', 'grok', 'qwen-coder', 'qwen-large', 'claude-fast',
                'claude', 'glm', 'deepseek'
            ];
        }

        // Deduplicate and remove explicitly free models from the dynamic list
        self::$pollinationsModelsCache = array_values(array_diff(
            array_unique(self::$pollinationsModelsCache), 
            self::$pollinationsFreeModelsCache
        ));

        return self::$pollinationsModelsCache;
    }

    private static function getPollinationsFreeModels(): array
    {
        return self::$pollinationsFreeModelsCache;
    }

    private const GEMINI_MODELS = [
        'gemini-2.5-flash-lite',
        'gemini-2.0-flash-lite',
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
            self::getPollinationsFreeModels(),
            self::getPollinationsModels(),
            self::CEREBRAS_MODELS,
            self::GEMINI_MODELS,
            self::MISTRAL_MODELS,
            self::GROQ_MODELS,
            self::COHERE_MODELS,
            self::OPENROUTER_MODELS,
            self::QWEN_MODELS,
            self::LOCAL_MODELS // include local models
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
        
        $cerebras = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::CEREBRAS_MODELS);
        $cohere = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::COHERE_MODELS);
        $mistral = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::MISTRAL_MODELS);
        $anthropic = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::ANTHROPIC_MODELS);
        $groq = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::GROQ_MODELS);
        $openrouter = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::OPENROUTER_MODELS);
        $qwen_local = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::QWEN_MODELS);
        $pollinations_seed = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::getPollinationsModels());
        $pollinations_free = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::getPollinationsFreeModels());
        $gemini_text = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::GEMINI_MODELS);
        $local = array_map(fn($m) => ['id' => $m, 'name' => $humanize($m)], self::LOCAL_MODELS);

        return [
            'Pollinations API - Free' => $pollinations_free,
            'Pollinations API - Seed' => $pollinations_seed,
            'Cerebras API' => $cerebras,
            'Gemini Text / Coding Models' => $gemini_text,
            'Mistral API' => $mistral,
            'Groq API' => $groq,
            'OpenRouter Free Models' => $openrouter,
            'Cohere API' => $cohere,
            'Qwen / InternLM (Local via zrok)' => $qwen_local,
            'Local (Ollama)' => $local
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

        if ($this->isOpenRouterModel($model)) {
            return $this->sendToOpenRouterApi($model, $messages, $options);
        }

        if ($this->isPollinationsModel($model)) {
            return $this->sendToPollinationsApi($model, $messages, $options);
        }

        if ($this->isCerebrasModel($model)) {
            return $this->sendToCerebrasApi($model, $messages, $options);
        } elseif ($this->isCohereModel($model)) {
            return $this->sendToCohereApi($model, $messages, $options);
        } elseif ($this->isMistralModel($model)) {
            return $this->sendToMistralApi($model, $messages, $options);
        } elseif ($this->isGroqModel($model)) {
            return $this->sendToGroqApi($model, $messages, $options);
        } elseif ($this->isAnthropicModel($model)) {
            return $this->sendToAnthropicApi($model, $messages, $options);
        } elseif ($this->isGoogleModel($model)) {
            return $this->sendToGoogleApi($model, $messages, $options);
        } elseif ($this->isLocalOllamaModel($model)) {
            return $this->sendToLocalOllama($model, $messages, $options);
        } elseif ($this->isQwenLocalModel($model)) {
            return $this->sendToQwenLocal($model, $messages, $options);
        } else {
            return $this->sendToPollinationsApi($model, $messages, $options);
        }
    }
        
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

    private function isOpenRouterModel(string $model): bool
    {
        $lower = strtolower(trim($model));
        if ($lower === 'openrouter/free') return true;
        if (strpos($lower, 'openrouter/') === 0) return true;
        if (substr($lower, -5) === ':free') return true;
        foreach (self::OPENROUTER_MODELS as $m) {
            if (strcasecmp($m, $model) === 0) return true;
        }
        return false;
    }

    private function isPollinationsModel(string $model): bool
    {
        if ($this->isOpenRouterModel($model)) return false;
        $lower = strtolower($model);
        $pollAll = array_merge(self::getPollinationsModels(), self::getPollinationsFreeModels());
        $lowerPoll = array_map('strtolower', $pollAll);
        return in_array($lower, $lowerPoll, true);
    }

    private function isCerebrasModel(string $model): bool
    {
        if ($this->isOpenRouterModel($model)) return false;
        foreach (self::CEREBRAS_MODELS as $m) {
            if (strcasecmp($m, $model) === 0) return true;
        }
        return false;
    }
    
    private function isGroqModel(string $model): bool
    {
        if ($this->isOpenRouterModel($model)) return false;
        if (stripos($model, 'groq') !== false) return true;
        foreach (self::GROQ_MODELS as $m) {
            if (strcasecmp($m, $model) === 0) return true;
        }
        return false;
    }

    private function isGoogleModel(string $model): bool
    {
        if ($this->isOpenRouterModel($model)) return false;
        if ($this->isPollinationsModel($model)) return false;
        $m = strtolower($model);
        if (strpos($m, 'gemini') !== false || strpos($m, 'google') !== false) return true;
        return in_array($m, ['google', 'gemini'], true);
    }

    private function isAnthropicModel(string $model): bool
    {
        if ($this->isOpenRouterModel($model)) return false;
        if ($this->isPollinationsModel($model)) return false;
        foreach (self::ANTHROPIC_MODELS as $m) {
            if (strcasecmp($m, $model) === 0) return true;
        }
        $lower = strtolower($model);
        return (strpos($lower, 'claude') !== false || strpos($lower, 'anthropic') !== false);
    }

    private function isQwenLocalModel(string $model): bool
    {
        if ($this->isOpenRouterModel($model)) return false;
        $m = strtolower($model);
        if ((strpos($m, 'qwen') !== false && strpos($m, 'awq') !== false) || strpos($m, 'internlm') !== false) return true;
        foreach (self::QWEN_MODELS as $qm) {
            if (strcasecmp($qm, $model) === 0) return true;
        }
        return false;
    }

    private function isMistralModel(string $model): bool
    {
        if ($this->isOpenRouterModel($model)) return false;
        foreach (self::MISTRAL_MODELS as $m) {
            if (strcasecmp($m, $model) === 0) return true;
        }
        $lowerModel = strtolower($model);
        return (strpos($lowerModel, 'mistral-') === 0 || strpos($lowerModel, 'codestral-') === 0);
    }

    private function isCohereModel(string $model): bool
    {
        if ($this->isOpenRouterModel($model)) return false;
        foreach (self::COHERE_MODELS as $m) {
            if (strcasecmp($m, $model) === 0) return true;
        }
        return (stripos($model, 'command') !== false);
    }

    private function isPollinationsFreeModel(string $model): bool
    {
        return in_array(strtolower($model), array_map('strtolower', self::getPollinationsFreeModels()), true);
    }

    private function isLocalOllamaModel(string $model): bool
    {
        if ($this->isOpenRouterModel($model)) return false;
        foreach (self::LOCAL_MODELS as $m) {
            if (strcasecmp($m, $model) === 0) return true;
        }
        $lower = strtolower($model);
        // Only route to local ollama if it literally contains 'ollama' or explicitly matches the local models array.
        // We do NOT blindly match 'deepseek' anymore, since Pollinations provides deepseek models!
        return (strpos($lower, 'ollama') !== false);
    }

    // ============================================================================
    // API PROVIDERS
    // ============================================================================

    private function sendToOpenRouterApi(string $model, array $messages, array $options): string
    {
        $endpoint = getenv('OPENROUTER_ENDPOINT') ?: 'https://openrouter.ai/api/v1/chat/completions';
        $apiKey = $this->getOpenRouterApiKey();
        if (empty($apiKey)) throw new \RuntimeException('OPENROUTER_API_KEY not found.');

        $payload = ['model' => $model, 'messages' => $messages];
        if (isset($options['temperature'])) $payload['temperature'] = (float)$options['temperature'];
        $payload['max_tokens'] = isset($options['max_tokens']) ? (int)$options['max_tokens'] : /*409600*/ 128000;

        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'X-OpenRouter-Title: ' . (getenv('OPENROUTER_APP_TITLE') ?: 'SPWBase')
        ];

        $response = $this->executeCurlRequest($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, 'OpenRouter');
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) return $data['choices'][0]['message']['content'];
        throw new \RuntimeException('No usable reply from OpenRouter API.');
    }

    private function getOpenRouterApiKey(): ?string { return getenv('OPENROUTER_API_KEY') ?: $this->readTokenFromHome(['.openrouter_token', '.openrouter_api_key']); }

    private function sendToCerebrasApi(string $model, array $messages, array $options): string
    {
        $endpoint = getenv('CEREBRAS_ENDPOINT') ?: 'https://api.cerebras.ai/v1/chat/completions';
        $apiKey = $this->getCerebrasApiKey();
        if (empty($apiKey)) throw new \RuntimeException('CEREBRAS_API_KEY not found.');

        $payload = ['model' => $model, 'messages' => $messages];
        if (isset($options['temperature'])) $payload['temperature'] = (float)$options['temperature'];
        $payload['max_tokens'] = isset($options['max_tokens']) ? (int)$options['max_tokens'] : /*409600*/ 128000;

        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
        $response = $this->executeCurlRequest($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, 'Cerebras');
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) return $data['choices'][0]['message']['content'];
        throw new \RuntimeException('No usable reply from Cerebras API.');
    }

    private function getCerebrasApiKey(): ?string { return getenv('CEREBRAS_API_KEY') ?: $this->readTokenFromHome(['.cerebras_api_key']); }
    
    private function sendToCohereApi(string $model, array $messages, array $options): string
    {
        $endpoint = getenv('COHERE_ENDPOINT') ?: 'https://api.cohere.ai/compatibility/v1/chat/completions';
        $apiKey = $this->getCohereApiKey();
        if (empty($apiKey)) throw new \RuntimeException('COHERE_API_KEY not found.');

        $payload = ['model' => $model, 'messages' => $messages];
        if (isset($options['temperature'])) $payload['temperature'] = (float)$options['temperature'];
        $payload['max_tokens'] = isset($options['max_tokens']) ? (int)$options['max_tokens'] : 131072;

        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
        $response = $this->executeCurlRequest($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, 'Cohere');
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) return $data['choices'][0]['message']['content'];
        throw new \RuntimeException('No usable reply from Cohere API.');
    }

    private function getCohereApiKey(): ?string { return getenv('COHERE_API_KEY') ?: $this->readTokenFromHome(['.cohere_api_key', '.coheretoken']); }
    
    private function sendToMistralApi(string $model, array $messages, array $options): string
    {
        $endpoint = getenv('MISTRAL_ENDPOINT') ?: 'https://api.mistral.ai/v1/chat/completions';
        $apiKey = $this->getMistralApiKey();
        if (empty($apiKey)) throw new \RuntimeException('MISTRAL_API_KEY not found.');

        $payload = ['model' => $model, 'messages' => $messages];
        if (isset($options['temperature'])) $payload['temperature'] = (float)$options['temperature'];
        $payload['max_tokens'] = isset($options['max_tokens']) ? (int)$options['max_tokens'] : 131072;

        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
        $response = $this->executeCurlRequest($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, 'Mistral');
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) return $data['choices'][0]['message']['content'];
        throw new \RuntimeException('No usable reply from Mistral API.');
    }

    private function getMistralApiKey(): ?string { return getenv('MISTRAL_API_KEY') ?: $this->readTokenFromHome(['.mistral_api_key', '.mistraltoken']); }

    private function sendToAnthropicApi(string $model, array $messages, array $options): string
    {
        $endpoint = getenv('ANTHROPIC_ENDPOINT') ?: 'https://api.anthropic.com/v1/complete';
        $apiKey = $this->getAnthropicApiKey();
        if (empty($apiKey)) throw new \RuntimeException('ANTHROPIC_API_KEY not found.');

        $promptParts = [];
        foreach ($messages as $m) {
            $role = ucfirst($m['role'] ?? 'user');
            $promptParts[] = "{$role}: {$m['content']}";
        }
        $prompt = trim(implode("\n\n", $promptParts)) . "\n\nAssistant:";

        $payload = ['model' => $model, 'prompt' => $prompt];
        if (isset($options['temperature'])) $payload['temperature'] = (float)$options['temperature'];
        $payload['max_tokens_to_sample'] = isset($options['max_tokens']) ? min((int)$options['max_tokens'], 8192) : 8192;

        $headers = ['Content-Type: application/json', 'x-api-key: ' . $apiKey, 'anthropic-version: 2023-06-01'];
        $response = $this->executeCurlRequest($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, 'Anthropic');
        $data = json_decode($response, true);
        if (isset($data['completion']) && is_string($data['completion'])) return $data['completion'];
        throw new \RuntimeException('No usable reply from Anthropic.');
    }

    private function getAnthropicApiKey(): ?string { return getenv('ANTHROPIC_API_KEY') ?: $this->readTokenFromHome(['.claude_api_key', '.anthropic_api_key']); }
    
    private function sendToGroqApi(string $model, array $messages, array $options): string
    {
        $endpoint = getenv('GROQ_ENDPOINT') ?: 'https://api.groq.com/openai/v1/chat/completions';
        $apiKey = $this->getGroqApiKey();
        if (empty($apiKey)) throw new \RuntimeException('GROQ_API_KEY not found.');
        
        $payload = ['model' => $model, 'messages' => $messages];
        if (isset($options['temperature'])) $payload['temperature'] = (float)$options['temperature'];
        $payload['max_tokens'] = isset($options['max_tokens']) ? (int)$options['max_tokens'] : 131072;
        
        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
        $response = $this->executeCurlRequest($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, 'Groq');
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) return $data['choices'][0]['message']['content'];
        throw new \RuntimeException('No usable reply from Groq API.');
    }
    
    private function getGroqApiKey(): ?string { return getenv('GROQ_API_KEY') ?: $this->readTokenFromHome(['.groq_api_key', '.groqtoken']); }
    
   private function sendToPollinationsApi(string $model, array $messages, array $options): string
    {
        $isFreeModel = $this->isPollinationsFreeModel($model);
        $endpoint = 'https://gen.pollinations.ai/v1/chat/completions';
        $apiKey = $this->getPollinationsApiKey();
    
        if (empty($apiKey) && !$isFreeModel) {
            throw new \RuntimeException('No Pollinations API key found for seed model.');
        }
        
        $payload = ['model' => $model, 'messages' => $messages];
        
        if (isset($options['temperature'])) {
            $payload['temperature'] = (float)$options['temperature'];
        }

        // ==========================================
        // MASSIVE TOKEN CONTEXT (50x larger limits)
        // ==========================================
        if (isset($options['max_tokens'])) {
            $payload['max_tokens'] = (int)$options['max_tokens'];
        } else {
            // Anthropic models strictly fail if this exceeds their cap (typically 8192).
            // DeepSeek, Llama, and others will now get an incredible ~400,000 output token cap.
            if (strpos(strtolower($model), 'claude') !== false) {
                $payload['max_tokens'] = 64000;
            } else {
                $payload['max_tokens'] = /*409600*/ 128000; 
            }
        }
        
        $headers = ['Content-Type: application/json'];
        if (!empty($apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }
        
        $response = $this->executeCurlRequest($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, 'Pollinations');
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
             $errMsg = is_array($data['error']) ? ($data['error']['message'] ?? json_encode($data['error'])) : $data['error'];
             throw new \RuntimeException("Pollinations API Error: " . $errMsg);
        }

        if (isset($data['choices'][0]['message']['content'])) {
            return $data['choices'][0]['message']['content'];
        }
        
        throw new \RuntimeException('Pollinations returned no usable content.');
    }
    
    private function getPollinationsApiKey(): ?string { return getenv('POLLINATIONS_API_KEY') ?: $this->readTokenFromHome(['.pollinationsaitoken']); }

    private function getZrokTunnelUrl(): string
    {
        $scriptPath = \App\Core\SpwBase::getInstance()->getProjectPath() . '/bash/zrok_echo.sh';
        return trim(shell_exec('sh ' . escapeshellarg($scriptPath) . ' 2>&1'));
    }

    private function sendToQwenLocal(string $model, array $messages, array $options): string
    {
        $endpoint = $this->getZrokTunnelUrl() . '/v1/chat/completions';
        $apiKey = $this->getQwenApiKey();
        if (empty($apiKey)) throw new \RuntimeException('No Qwen API key found.');

        $payload = ['model' => $model, 'messages' => $messages];
        if (isset($options['temperature'])) $payload['temperature'] = (float)$options['temperature'];
        $payload['max_tokens'] = isset($options['max_tokens']) ? (int)$options['max_tokens'] : 131072;

        $headers = ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey];
        $response = $this->executeCurlRequest($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, 'Qwen');
        $data = json_decode($response, true);
        if (isset($data['choices'][0]['message']['content'])) return $data['choices'][0]['message']['content'];
        throw new \RuntimeException('Qwen FastAPI endpoint returned no usable content.');
    }

    private function getQwenApiKey(): ?string { return getenv('QWEN_API_KEY') ?: $this->readTokenFromHome(['.qwentoken']); }
    
    private function sendToGoogleApi(string $model, array $messages, array $options): string
    {
        $lower = strtolower($model);
        if ($lower === 'google' || $lower === 'gemini') {
            $model = 'gemini-2.5-flash';
        }

        $endpointBase = getenv('GEMINI_ENDPOINT') ?: 'https://generativelanguage.googleapis.com/v1beta/models';
        $endpoint = rtrim($endpointBase, '/') . '/' . $model . ':generateContent';

        $apiKey = getenv('GEMINI_API_KEY') ?: $this->readTokenFromHome(['.gemini_api_key']);
        if (empty($apiKey)) throw new \RuntimeException('GEMINI_API_KEY not found.');

        $contents = [];
        foreach ($messages as $m) {
            $role = ($m['role'] === 'assistant') ? 'model' : 'user';
            $contents[] = ['role' => $role, 'parts' => [['text' => $m['content']]]];
        }

        $payload = ['contents' => $contents];
        $generationConfig = [];
        if (isset($options['temperature'])) $generationConfig['temperature'] = (float)$options['temperature'];
        $generationConfig['maxOutputTokens'] = isset($options['max_tokens']) ? (int)$options['max_tokens'] : 131072;
        
        if (!empty($generationConfig)) $payload['generationConfig'] = $generationConfig;

        $headers = ['Content-Type: application/json', 'x-goog-api-key: ' . $apiKey];
        $response = $this->executeCurlRequest($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE), $headers, 'GoogleGemini');
        $data = json_decode($response, true);
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) return $data['candidates'][0]['content']['parts'][0]['text'];
        throw new \RuntimeException('No usable reply from Google Gemini.');
    }

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
        curl_setopt($ch, CURLOPT_TIMEOUT, 1800);
        
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

    private function getLocalOllamaBaseUrl(): string
    {
        $env = getenv('LOCAL_OLLAMA_API_URL');
        if ($env) return rtrim($env, '/');
        
        $spw = \App\Core\SpwBase::getInstance();
        $script = $spw->getProjectPath() . '/bash/pyapi_echo.sh';
        $apiUrl = trim(shell_exec('sh ' . escapeshellarg($script)));
    
        return $apiUrl !== '' ? trim($apiUrl . '/local_ollama') : 'http://127.0.0.1:8009/local_ollama';
    }

    private function getLocalOllamaHeaders(): array
    {
        $headers = ['Content-Type: application/json'];
        $apiKey = getenv('LOCAL_LM_API_KEY') ?: $this->readTokenFromHome(['.local_lm_key', '.ollama_key']);
        if (!empty($apiKey)) {
            $headers[] = 'Authorization: Bearer ' . $apiKey;
        }
        return $headers;
    }

    public function getLocalModels(): array
    {
        $base = $this->getLocalOllamaBaseUrl();
        $endpoint = $base . '/v1/models';
        try {
            $resp = $this->executeCurlRequest($endpoint, '', $this->getLocalOllamaHeaders(), 'LocalOllama');
            $json = json_decode($resp, true);
            return $json['data'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function sendToLocalOllama(string $model, array $messages, array $options = []): string
    {
        $base = $this->getLocalOllamaBaseUrl();
        $endpoint = $base . '/v1/chat/completions';
        $payload = ['model' => $model, 'messages' => $messages];

        if (isset($options['temperature'])) $payload['temperature'] = (float)$options['temperature'];
        $payload['max_tokens'] = isset($options['max_tokens']) ? (int)$options['max_tokens'] : 131072;

        try {
            $response = $this->executeCurlRequest($endpoint, json_encode($payload, JSON_UNESCAPED_UNICODE), $this->getLocalOllamaHeaders(), 'LocalOllama');
            $data = json_decode($response, true);
            if (isset($data['choices'][0]['message']['content'])) return $data['choices'][0]['message']['content'];
            if (isset($data['response']) && is_string($data['response'])) return $data['response'];
            throw new \RuntimeException('Local Ollama returned no usable content.');
        } catch (\Exception $e) {
            throw new \RuntimeException('Local Ollama request failed: ' . $e->getMessage());
        }
    }

    public function pullLocalModel(string $model): array
    {
        $base = $this->getLocalOllamaBaseUrl();
        $endpoint = $base . '/v1/models/pull';
        try {
            $resp = $this->executeCurlRequest($endpoint, json_encode(['model' => $model], JSON_UNESCAPED_UNICODE), $this->getLocalOllamaHeaders(), 'LocalOllamaPull');
            return json_decode($resp, true);
        } catch (\Exception $e) {
            throw new \RuntimeException("Local model pull failed: " . $e->getMessage());
        }
    }

    public function getLocalEmbeddings(string $model, array $inputs): array
    {
        $base = $this->getLocalOllamaBaseUrl();
        $endpoint = $base . '/v1/embeddings';
        try {
            $resp = $this->executeCurlRequest($endpoint, json_encode(['model' => $model, 'input' => $inputs], JSON_UNESCAPED_UNICODE), $this->getLocalOllamaHeaders(), 'LocalOllamaEmb');
            $json = json_decode($resp, true);
            $out = [];
            foreach ($json['data'] ?? [] as $item) $out[] = $item['embedding'] ?? [];
            return $out;
        } catch (\Exception $e) {
            throw new \RuntimeException("Local embeddings failed: " . $e->getMessage());
        }
    }
}
