<?php
namespace App\Service\Schema;

class ResponseNormalizer
{
    /**
     * Attempt to normalize non-compliant response to schema
     */
    public function normalize(?array $data, array $schema, array $userInput): NormalizationResult
    {
        if ($data === null) {
            return NormalizationResult::fail(null, ['No data to normalize']);
        }

        $props = $schema['properties'] ?? [];
        $warnings = [];
        $normalized = [];

        // Handle known patterns for different generator types
        if ($this->looksLikeTonguetwister($props)) {
            return $this->normalizeTonguetwister($data, $props, $userInput);
        }

        if ($this->looksLikeScene($props)) {
            return $this->normalizeScene($data, $props, $userInput);
        }

        // Generic normalization
        foreach ($props as $key => $def) {
            if (isset($data[$key])) {
                $normalized[$key] = $data[$key];
            } else {
                // Try fallbacks
                $normalized[$key] = $this->findFallbackValue($key, $def, $data, $userInput);
                if ($normalized[$key] === null) {
                    $warnings[] = "Could not find value for '{$key}'";
                }
            }
        }

        return NormalizationResult::success($normalized, $warnings);
    }

    private function looksLikeTonguetwister(array $props): bool
    {
        return isset($props['twister'], $props['mode'], $props['language']);
    }

    private function looksLikeScene(array $props): bool
    {
        return isset($props['scene'], $props['beats'], $props['theme']);
    }

    private function normalizeTonguetwister(array $data, array $props, array $userInput): NormalizationResult
    {
        $warnings = [];
        $normalized = [
            'mode' => $this->normalizeString($data['mode'] ?? $userInput['mode'] ?? 'medium'),
            'language' => $this->normalizeString($data['language'] ?? $userInput['language'] ?? 'german'),
            'twister' => '',
            'metadata' => [],
        ];

        // Find primary twister
        if (isset($data['twister']) && is_string($data['twister'])) {
            $normalized['twister'] = $data['twister'];
        } else {
            // Look for alternatives
            $candidates = $this->findStringCandidates($data, ['twisters', 'twists', 'variants', 'results']);
            if (!empty($candidates)) {
                $normalized['twister'] = $candidates[0];
                $normalized['metadata']['alternatives'] = array_slice($candidates, 1);
            } else {
                $warnings[] = 'No twister found in response';
            }
        }

        // Compute metadata
        if ($normalized['twister']) {
            $normalized['metadata']['wordCount'] = $this->countWords($this->normalizeString($normalized['twister']));
            $normalized['metadata']['firstLetter'] = $this->getFirstLetter($this->normalizeString($normalized['twister']));
        }

        return NormalizationResult::success($normalized, $warnings);
    }

    private function normalizeScene(array $data, array $props, array $userInput): NormalizationResult
    {
        $warnings = [];

        $normalized = [
            'theme' => $this->normalizeString($data['theme'] ?? $userInput['theme'] ?? 'action'),
            'style' => $this->normalizeString($data['style'] ?? $userInput['style'] ?? 'anime'),
            'scene' => $data['scene'] ?? '',
            'beats' => $data['beats'] ?? [],
            'metadata' => $data['metadata'] ?? [],
        ];

        // Normalize types strictly
        $normalized['scene'] = $this->normalizeString($normalized['scene']);

        if (!is_array($normalized['beats'])) {
            // attempt to decode JSON lists provided as strings
            if (is_string($normalized['beats'])) {
                $decoded = json_decode($normalized['beats'], true);
                $normalized['beats'] = is_array($decoded) ? $decoded : [];
            } else {
                $normalized['beats'] = [];
            }
        }

        if (!is_array($normalized['metadata'])) {
            $normalized['metadata'] = [];
        }

        if ($normalized['scene'] === '') {
            $warnings[] = 'No scene text found';
        }

        if (empty($normalized['beats'])) {
            $warnings[] = 'No beats found';
        }

        // If scene is empty but beats exist, synthesize a short scene prose from beats
        if ($normalized['scene'] === '' && !empty($normalized['beats'])) {
            // concat beat descriptions (if available) into a readable paragraph
            $parts = [];
            foreach ($normalized['beats'] as $b) {
                if (is_array($b) && isset($b['description']) && is_string($b['description'])) {
                    $parts[] = trim($b['description']);
                } elseif (is_string($b) && strlen(trim($b)) > 0) {
                    $parts[] = trim($b);
                }
            }
            if (!empty($parts)) {
                $normalized['scene'] = implode(' ', $parts);
                $warnings[] = 'Scene text auto-generated from beats';
            }
        }

        // Compute metadata
        if ($normalized['scene'] !== '' && empty($normalized['metadata']['sentenceCount'])) {
            try {
                $normalized['metadata']['sentenceCount'] = $this->countSentences($normalized['scene']);
            } catch (\Throwable $e) {
                $warnings[] = 'Failed to compute sentence count: ' . $e->getMessage();
            }
        }

        if ($normalized['scene'] !== '' && empty($normalized['metadata']['wordCount'])) {
            try {
                $normalized['metadata']['wordCount'] = $this->countWords($normalized['scene']);
            } catch (\Throwable $e) {
                $warnings[] = 'Failed to compute word count: ' . $e->getMessage();
            }
        }

        // Always return a NormalizationResult
        return NormalizationResult::success($normalized, $warnings);
    }

    private function findStringCandidates(array $data, array $keys): array
    {
        $candidates = [];
        foreach ($keys as $k) {
            if (isset($data[$k]) && is_array($data[$k])) {
                foreach ($data[$k] as $item) {
                    if (is_string($item) && strlen($item) > 10) {
                        $candidates[] = $item;
                    }
                }
            } elseif (isset($data[$k]) && is_string($data[$k]) && strlen($data[$k]) > 10) {
                // sometimes returned directly as a single string
                $candidates[] = $data[$k];
            }
        }
        return $candidates;
    }

    private function findFallbackValue(string $key, array $def, array $data, array $userInput)
    {
        // Check user input
        if (isset($userInput[$key])) {
            return $userInput[$key];
        }

        // Check default in schema
        if (isset($def['default'])) {
            return $def['default'];
        }

        // Type-based defaults
        $type = $def['type'] ?? null;
        return match($type) {
            'string' => '',
            'array' => [],
            'object' => [],
            'integer' => 0,
            'boolean' => false,
            default => null,
        };
    }

    private function countWords(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }

        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($words === false) {
            return 0;
        }

        $count = 0;
        foreach ($words as $w) {
            if (trim($w, " \t\n\r\0\x0B.,;:!?()[]\"'—–-") !== '') {
                $count++;
            }
        }
        return $count;
    }

    private function countSentences(string $text): int
    {
        $text = trim($text);
        if ($text === '') {
            return 0;
        }
        $matches = [];
        $res = preg_match_all('/[.!?]+/', $text, $matches);
        return is_int($res) ? $res : 0;
    }

    private function getFirstLetter(string $text): ?string
    {
        if (preg_match('/\p{L}/u', $text, $m)) {
            return mb_strtoupper($m[0], 'UTF-8');
        }
        return null;
    }

    /**
     * Normalize various input shapes into a string safely
     */
    private function normalizeString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        if (is_array($value)) {
            // join string elements, ignore non-strings
            $parts = [];
            foreach ($value as $v) {
                if (is_string($v)) {
                    $parts[] = $v;
                } elseif (is_scalar($v)) {
                    $parts[] = (string)$v;
                }
            }
            return implode(' ', $parts);
        }
        if (is_scalar($value)) {
            return (string)$value;
        }
        return '';
    }
}

class NormalizationResult
{
    private function __construct(
        private bool $success,
        private ?array $data,
        private array $warnings
    ) {}

    public static function success(array $data, array $warnings = []): self
    {
        return new self(true, $data, $warnings);
    }

    public static function fail(?array $data, array $warnings): self
    {
        return new self(false, $data, $warnings);
    }

    public function isSuccess(): bool { return $this->success; }
    public function getData(): ?array { return $this->data; }
    public function getWarnings(): array { return $this->warnings; }
}
