<?php

namespace App\Core;

/**
 * AIProvider - Centralized AI Communication Module
 * 
 * Provides unified interface for communicating with various AI providers:
 * - Groq API
 * - Pollinations API
 * - Google Gemini (Generative Language API) — minimal integration
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

        // Groq group: reuse existing GROQ_MODELS constant
        $groq = array_map(function ($m) use ($humanize) {
            return ['id' => $m, 'name' => $humanize($m)];
        }, self::GROQ_MODELS);

        // Pollinations groups: mirror the options previously in ChatUI
        $pollinations_main = [
            ['id'=>'deepseek','name'=>'DeepSeek V3.1'],
            ['id'=>'deepseek-reasoning','name'=>'DeepSeek R1 (Reasoning)'],
            ['id'=>'mistral','name'=>'Mistral Small 3.1 24B'],
            ['id'=>'nova-fast','name'=>'Amazon Nova Micro'],
            ['id'=>'openai','name'=>'OpenAI GPT-5 Mini'],
            ['id'=>'openai-fast','name'=>'OpenAI GPT-5 Nano'],
            ['id'=>'openai-large','name'=>'OpenAI GPT-5 Chat'],
            ['id'=>'openai-reasoning','name'=>'OpenAI o4-mini (Reasoning)'],
            ['id'=>'openai-audio','name'=>'OpenAI GPT-4o Audio'],
            ['id'=>'qwen-coder','name'=>'Qwen 2.5 Coder 32B'],
            ['id'=>'roblox-rp','name'=>'Llama 3.1 8B Instruct'],
        ];

        $pollinations_community = [
            ['id'=>'bidara','name'=>'BIDARA (NASA)'],
            ['id'=>'chickytutor','name'=>'ChickyTutor Language'],
            ['id'=>'midijourney','name'=>'MIDIjourney'],
            ['id'=>'rtist','name'=>'Rtist'],
        ];

        // Gemini groups: mirror the options previously in ChatUI
        $gemini_text = [
            ['id'=>'gemini-2.5-pro','name'=>'Gemini 2.5 Pro (Stable)'],
            ['id'=>'gemini-2.5-pro-preview-03-25','name'=>'Gemini 2.5 Pro Preview 03-25'],
            ['id'=>'gemini-2.5-pro-preview-05-06','name'=>'Gemini 2.5 Pro Preview 05-06'],
            ['id'=>'gemini-2.5-pro-preview-06-05','name'=>'Gemini 2.5 Pro Preview 06-05'],
            ['id'=>'gemini-2.5-flash','name'=>'Gemini 2.5 Flash (Stable)'],
            ['id'=>'gemini-2.5-flash-preview-05-20','name'=>'Gemini 2.5 Flash Preview 05-20'],
            ['id'=>'gemini-2.5-flash-lite','name'=>'Gemini 2.5 Flash-Lite (Stable)'],
            ['id'=>'gemini-2.5-flash-lite-preview-06-17','name'=>'Gemini 2.5 Flash-Lite Preview 06-17'],
            ['id'=>'gemini-2.0-flash','name'=>'Gemini 2.0 Flash'],
            ['id'=>'gemini-2.0-flash-001','name'=>'Gemini 2.0 Flash 001'],
            ['id'=>'gemini-2.0-flash-lite','name'=>'Gemini 2.0 Flash-Lite'],
            ['id'=>'gemini-2.0-flash-lite-001','name'=>'Gemini 2.0 Flash-Lite 001'],
            ['id'=>'gemini-2.0-pro-exp','name'=>'Gemini 2.0 Pro Experimental'],
            ['id'=>'gemini-2.0-flash-live-001','name'=>'Gemini 2.0 Flash 001 Live'],
            ['id'=>'gemini-live-2.5-flash-preview','name'=>'Gemini Live 2.5 Flash Preview'],
            ['id'=>'gemini-2.5-flash-live-preview','name'=>'Gemini 2.5 Flash Live Preview'],
            ['id'=>'gemini-2.5-flash-native-audio-latest','name'=>'Gemini 2.5 Flash Native Audio Latest'],
            ['id'=>'gemini-2.5-flash-native-audio-preview-09-2025','name'=>'Gemini 2.5 Flash Native Audio Preview 09-2025'],
        ];

        $gemini_embedding = [
            ['id'=>'gemini-embedding-001','name'=>'Gemini Embedding 001'],
            ['id'=>'gemini-embedding-exp','name'=>'Gemini Embedding Experimental'],
            ['id'=>'gemini-embedding-exp-03-07','name'=>'Gemini Embedding Experimental 03-07'],
            ['id'=>'embedding-gecko-001','name'=>'Embedding Gecko'],
        ];

        return [
            'Groq API' => $groq,
            'Pollinations API - Main' => $pollinations_main,
            'Pollinations API - Community' => $pollinations_community,
            'Gemini Text / Coding Models' => $gemini_text,
            'Gemini Embedding Models' => $gemini_embedding,
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
        if ($this->isGroqModel($model)) {
            return $this->sendToGroqApi($model, $messages, $options);
        } elseif ($this->isGoogleModel($model)) {
            return $this->sendToGoogleApi($model, $messages, $options);
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

    /**
     * Detect Google/Gemini model identifiers or shorthand.
     */
    private function isGoogleModel(string $model): bool
    {
        $m = strtolower($model);
        if (strpos($m, 'gemini') !== false || strpos($m, 'google') !== false || strpos($m, 'generativelanguage') !== false) {
            return true;
        }
        // accept shorthand 'google' or 'gemini' too
        if (in_array($m, ['google', 'gemini'], true)) {
            return true;
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
    // GOOGLE GEMINI (Generative Language) PROVIDER
    // Minimal, dependency-free integration using api key header x-goog-api-key
    // ============================================================================
    
    private function sendToGoogleApi(string $model, array $messages, array $options): string
    {
        // default model if shorthand used
        $lower = strtolower($model);
        if ($lower === 'google' || $lower === 'gemini') {
            $model = 'gemini-2.5-flash';
        }

        $endpointBase = getenv('GEMINI_ENDPOINT') ?: 'https://generativelanguage.googleapis.com/v1beta/models';
        $endpoint = rtrim($endpointBase, '/') . '/' . $model . ':generateContent';

        // API key: try env, $_SERVER, then token files
        $apiKey = getenv('GEMINI_API_KEY') ?: (isset($_SERVER['GEMINI_API_KEY']) ? $_SERVER['GEMINI_API_KEY'] : null);
        if (empty($apiKey)) {
            $apiKey = $this->readTokenFromHome(['.gemini_api_key', '.google_gemini_key', '.gcloud_api_key']);
        }
        if (empty($apiKey)) {
            throw new \RuntimeException('GEMINI_API_KEY not found. Set GEMINI_API_KEY or place token in ~/.gemini_api_key');
        }

        // Convert $messages to Google "contents" structure
        $contents = [];
        if (count($messages) === 1 && isset($messages[0]['content'])) {
            $contents[] = ['parts' => [['text' => $messages[0]['content']]]];
        } else {
            // join messages into a single string with role markers to preserve context
            $joined = '';
            foreach ($messages as $m) {
                $role = isset($m['role']) ? ucfirst($m['role']) : 'User';
                $text = isset($m['content']) ? $m['content'] : '';
                $joined .= "{$role}: {$text}\n\n";
            }
            $contents[] = ['parts' => [['text' => trim($joined)]]];
        }

        $payload = ['contents' => $contents];

        // Map some options conservatively
        if (isset($options['temperature'])) {
            $payload['temperature'] = $options['temperature'];
        }
        if (isset($options['max_tokens'])) {
            // Google naming differs; map conservatively to maxOutputTokens
            $payload['maxOutputTokens'] = (int)$options['max_tokens'];
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: spw-aiprovider/1.0',
            // Google simple API key auth header (matches your curl example)
            'x-goog-api-key: ' . $apiKey,
        ];

        $response = $this->executeCurlRequest($endpoint, $payloadJson, $headers, 'GoogleGemini');

        // Defensive parsing
        $data = json_decode($response, true);
        if (is_array($data)) {
            // Try candidates -> content -> parts -> text
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
                // fallback: recursive search in candidate
                $found = $this->extractTextFromArray($cand);
                if ($found !== null) {
                    return $found;
                }
            }

            // fallback: search whole decoded document
            $found = $this->extractTextFromArray($data);
            if ($found !== null) {
                return $found;
            }
        }

        // fallback: extract first JSON object string from raw response (existing helper)
        $jsonCandidate = $this->extractFirstJsonObject($response);
        if ($jsonCandidate !== null) {
            return $jsonCandidate;
        }

        throw new \RuntimeException('No usable reply from Google Gemini. Raw response start: ' . mb_substr($response, 0, 2000));
    }

    /**
     * Recursively search nested arrays/objects for the first non-empty 'text' string and return it.
     */
    private function extractTextFromArray($value): ?string
    {
        if (is_string($value)) {
            return null;
        }

        if (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            // If this array is associative and has a 'text' key, return it
            if (array_key_exists('text', $value) && is_string($value['text']) && $value['text'] !== '') {
                return $value['text'];
            }
            // Otherwise iterate recursively
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
