<?php
// src/Service/GeneratorService.php

namespace App\Service;

use App\Entity\GeneratorConfig;
use App\Core\AIProvider;
use App\Core\FileLogger;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;

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

        // Build system message
        $systemContent = $this->buildSystemMessage($config);

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
    private function buildSystemMessage(GeneratorConfig $config): string
    {
        $parts = [];
        
        if ($role = $config->getSystemRole()) {
            $parts[] = $role;
        }

        if ($instructions = $config->getInstructions()) {
            $parts[] = implode("\n", $instructions);
        }

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
    public function getValidation(): ValidationResult { return $this->validation; }
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
