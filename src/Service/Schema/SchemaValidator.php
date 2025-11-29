<?php
// src/Service/Schema/SchemaValidator.php

declare(strict_types=1);

namespace App\Service\Schema;

class SchemaValidator
{
    /**
     * Validate data against schema
     */
    public function validate(?array $data, array $schema): ValidationResult
    {
        if ($data === null) {
            return ValidationResult::fail(['No data to validate']);
        }

        // Check for model-signaled schema error
        if (isset($data['error']) && $data['error'] === 'schema_noncompliant') {
            return ValidationResult::fail([
                'Model reported schema_noncompliant: ' . ($data['reason'] ?? 'no reason')
            ]);
        }

        $errors = [];
        $props = $schema['properties'] ?? [];
        $required = $schema['required'] ?? [];

        // Check required fields
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                $errors[] = "Missing required field: {$field}";
            }
        }

        // Type validation
        foreach ($props as $key => $def) {
            if (!isset($data[$key])) {
                continue;
            }

            $expectedType = $def['type'] ?? null;
            $actualType = gettype($data[$key]);

            if ($expectedType === 'array' && !is_array($data[$key])) {
                $errors[] = "Field '{$key}' must be array, got {$actualType}";
            } elseif ($expectedType === 'string' && !is_string($data[$key])) {
                $errors[] = "Field '{$key}' must be string, got {$actualType}";
            } elseif ($expectedType === 'integer' && !is_int($data[$key])) {
                $errors[] = "Field '{$key}' must be integer, got {$actualType}";
            } elseif ($expectedType === 'object' && !is_array($data[$key])) {
                $errors[] = "Field '{$key}' must be object, got {$actualType}";
            }
        }

        return empty($errors)
            ? ValidationResult::success()
            : ValidationResult::fail($errors);
    }
}

class ValidationResult
{
    private function __construct(
        private bool $valid,
        private array $errors = []
    ) {}

    public static function success(): self
    {
        return new self(true);
    }

    public static function fail(array $errors): self
    {
        return new self(false, $errors);
    }

    public function isValid(): bool { return $this->valid; }
    public function getErrors(): array { return $this->errors; }
}


