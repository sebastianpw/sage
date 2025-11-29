<?php
// src/Service/GeneratorService.php

namespace App\Service;

use App\Entity\GeneratorConfig;
use App\Core\AIProvider;
use App\Core\FileLogger;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;
use App\Oracle\Bloom;

class GeneratorService
{
    private AIProvider $aiProvider;
    private SchemaValidator $validator;
    private ResponseNormalizer $normalizer;
    private ?FileLogger $logger;

    public function __construct(
        AIProvider $aiProvider,
        SchemaValidator $validator,
        ResponseNormalizer $normalizer,
        ?FileLogger $logger = null
    ) {
        $this->aiProvider = $aiProvider;
        $this->validator = $validator;
        $this->normalizer = $normalizer;
        $this->logger = $logger;
    }

    /**
     * Generate content using a generator configuration
     *
     * @param GeneratorConfig $config
     * @param array $params User parameters
     * @param array $aiOptions AI provider options (temperature, max_tokens)
     * @return GeneratorResult
     */
    public function generate(
        GeneratorConfig $config,
        array $params = [],
        array $aiOptions = []
    ): GeneratorResult {
        $startTime = microtime(true);

        // ===== START: BLOOM ORACLE INTEGRATION =====
        $oracleHint = null;
        $oracleConfig = $config->getOracleConfig();

        if ($oracleConfig && !empty($oracleConfig['dictionary_ids'])) {
            try {
                $bloomOracle = new Bloom(); // Assumes default pyapi URL is fine
                
                // Use a random seed from the request if provided, for dynamism
                $seed = $params['random_oracle_seed'] ?? $oracleConfig['seed'] ?? null;
                
                $oracleHint = $bloomOracle->generateHint(
                    $oracleConfig['dictionary_ids'],
                    $oracleConfig['num_words'] ?? 200,
                    $oracleConfig['error_rate'] ?? 0.01,
                    $seed ? (int)$seed : null
                );
            } catch (\Exception $e) {
                // Log the error but don't fail the entire generation
                $this->log('error', 'Bloom Oracle failed', ['error' => $e->getMessage()]);
            }
        }
        // ===== END: BLOOM ORACLE INTEGRATION =====


        // Build system message
        $systemContent = $this->buildSystemMessage($config, $oracleHint);

        // Build user input from params + defaults
        $userInput = $this->buildUserInput($config, $params);

        // Construct messages
        $messages = [
            ['role' => 'system', 'content' => $systemContent],
            ['role' => 'user', 'content' => json_encode(['input' => $userInput], JSON_UNESCAPED_UNICODE)],
        ];

        $this->log('info', 'Sending generation request', [
            'config_id' => $config->getConfigId(),
            'model' => $config->getModel(),
            'params' => $userInput,
        ]);

        // Call AI
        $rawResponse = $this->aiProvider->sendMessage(
            $config->getModel(),
            $messages,
            $aiOptions
        );

        // Parse response
        $decoded = $this->extractJson($rawResponse);

        // Validate and normalize
        $validation = $this->validator->validate($decoded, $config->getOutputSchema());
        $normalized = null;
        $warnings = [];

        if ($validation->isValid()) {
            $normalized = $decoded;
        } else {
            // Attempt normalization
            $normResult = $this->normalizer->normalize($decoded, $config->getOutputSchema(), $userInput);
            $normalized = $normResult->getData();
            $warnings = $normResult->getWarnings();
        }

        $elapsed = microtime(true) - $startTime;

        return new GeneratorResult(
            success: $normalized !== null,
            data: $normalized,
            rawResponse: $rawResponse,
            decoded: $decoded,
            validation: $validation,
            warnings: $warnings,
            elapsedMs: (int)($elapsed * 1000),
            model: $config->getModel(),
            requestMessages: $messages
        );
    }

    /**
     * Build system message from config
     */
    private function buildSystemMessage(GeneratorConfig $config, ?array $oracleHint = null): string
    {
        $parts = [];
        
        if ($role = $config->getSystemRole()) {
            $parts[] = $role;
        }

        if ($instructions = $config->getInstructions()) {
            $parts[] = implode("\n", $instructions);
        }
        
        // ===== START: HINT INJECTION =====
        if ($oracleHint && isset($oracleHint['meta']['sampled_lemmas'])) {
            $wordList = implode(', ', $oracleHint['meta']['sampled_lemmas']);
            // You can choose your injection strategy. This is a direct and effective one.
            $parts[] = "INSPIRATIONAL HINT: To enhance creativity and reduce repetition, draw inspiration from the following set of words. You do not have to use them directly, but let them influence the tone, theme, or subject matter of your response. But after all: Come on we made the effort to provide all these words for a good reason. We do not always want the same! USE the words!!.\nInspirational Words: [{$wordList}]";
        }
        // ===== END: HINT INJECTION =====

        return implode("\n\n", $parts);
    }

    /**
     * Build user input by merging params with parameter defaults
     */
    private function buildUserInput(GeneratorConfig $config, array $params): array
    {
        $input = [];
        $paramDefs = $config->getParameters();

        // Apply defaults and user overrides
        foreach ($paramDefs as $key => $def) {
            if (isset($params[$key])) {
                $input[$key] = $params[$key];
            } elseif (isset($def['default'])) {
                $input[$key] = $def['default'];
            }
        }

        // Allow arbitrary params to pass through
        foreach ($params as $key => $val) {
            if (!isset($input[$key])) {
                $input[$key] = $val;
            }
        }

        return $input;
    }

    /**
     * Extract first JSON object from response
     */
    private function extractJson(string $text): ?array
    {
        $trimmed = trim($text);

        // Try direct decode
        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Try extracting first JSON object with balanced braces
        if (preg_match('/\{(?:[^{}]*|(?R))*\}/s', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger && method_exists($this->logger, $level)) {
            $this->logger->$level([$message => $context]);
        }
    }
}

/**
 * Result object for generator operations
 */
class GeneratorResult
{
    public function __construct(
        private bool $success,
        private ?array $data,
        private string $rawResponse,
        private ?array $decoded,
        private \App\Service\Schema\ValidationResult $validation,
        private array $warnings,
        private int $elapsedMs,
        private string $model,
        private array $requestMessages
    ) {}

    public function isSuccess(): bool { return $this->success; }
    public function getData(): ?array { return $this->data; }
    public function getRawResponse(): string { return $this->rawResponse; }
    public function getDecoded(): ?array { return $this->decoded; }
    public function getValidation(): \App\Service\Schema\ValidationResult { return $this->validation; }
    public function getWarnings(): array { return $this->warnings; }
    public function getElapsedMs(): int { return $this->elapsedMs; }
    public function getModel(): string { return $this->model; }
    public function getRequestMessages(): array { return $this->requestMessages; }

    public function toArray(): array
    {
        return [
            'ok' => $this->success,
            'data' => $this->data,
            'raw_response' => $this->rawResponse,
            'decoded' => $this->decoded,
            'schema_valid' => $this->validation->isValid(),
            'validation_errors' => $this->validation->getErrors(),
            'warnings' => $this->warnings,
            'elapsed_ms' => $this->elapsedMs,
            'model' => $this->model,
            'request_messages' => $this->requestMessages,
        ];
    }
}
