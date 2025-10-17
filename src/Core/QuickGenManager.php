<?php

namespace App\Core;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ChatSession;
use App\Entity\ChatMessage;
use RuntimeException;

class QuickGenManager
{
    private EntityManagerInterface $em;
    private AIProvider $aiProvider;
    private ?FileLogger $logger = null;

    /**
     * QuickGenManager constructor.
     *
     * @param EntityManagerInterface $em
     * @param AIProvider|null $aiProvider
     * @param FileLogger|null $logger
     */
    public function __construct(EntityManagerInterface $em, ?AIProvider $aiProvider = null, ?FileLogger $logger = null)
    {
        $this->em = $em;
        $this->aiProvider = $aiProvider ?? new AIProvider($logger);
        $this->logger = $logger;
    }

    /**
     * Generate output for a special instruction session.
     * This method does NOT persist any ChatMessage or ChatSession changes.
     *
     * @param string $sessionId  Session ID of the generator session (string session_id)
     * @param array $overrides   Optional overrides (mode, firstLetter, language, punctuation, etc.)
     * @param string|null $model Optional model override (falls back to session.model or default)
     * @param array $options     Optional AI provider options (temperature, max_tokens, etc.)
     *
     * @return array
     * @throws RuntimeException
     */
    public function generateFromSessionId(string $sessionId, array $overrides = [], ?string $model = null, array $options = []): array
    {
        // find session without creating one
        $sessionRepo = $this->em->getRepository(ChatSession::class);
        $session = $sessionRepo->findOneBy(['sessionId' => $sessionId]);

        if (!$session) {
            throw new RuntimeException("Generator session '{$sessionId}' not found.");
        }

        // fetch messages (expect first message to contain JSON instruction)
        $msgRepo = $this->em->getRepository(ChatMessage::class);
        $messages = $msgRepo->findBy(['session' => $session], ['createdAt' => 'ASC']);

        if (count($messages) === 0) {
            throw new RuntimeException("Generator session '{$sessionId}' contains no messages/instructions.");
        }

        // instruction content (raw)
        $instructionRaw = $messages[0]->getContent();

        // decode instruction JSON (or extract first JSON object)
        $config = json_decode($instructionRaw, true);
        if ($config === null && json_last_error() !== JSON_ERROR_NONE) {
            $this->log('info', "Instruction not valid JSON, attempting extraction", ['snippet' => mb_substr($instructionRaw, 0, 500)]);
            $extracted = $this->extractFirstJsonObject($instructionRaw);
            if ($extracted === null) {
                throw new RuntimeException("Instruction JSON could not be parsed or extracted for session '{$sessionId}'.");
            }
            $config = json_decode($extracted, true);
            if ($config === null) {
                throw new RuntimeException("Instruction JSON extraction failed to decode for session '{$sessionId}'.");
            }
        }

        // build system content from config.system
        $systemContent = '';
        if (!empty($config['system']['role'])) {
            $systemContent .= $config['system']['role'] . "\n";
        }
        if (!empty($config['system']['instructions']) && is_array($config['system']['instructions'])) {
            $systemContent .= implode("\n", $config['system']['instructions']);
        }

        // build user input payload from parameters + overrides
        $userInput = [];
        if (!empty($config['parameters']) && is_array($config['parameters'])) {
            foreach ($config['parameters'] as $paramName => $paramDef) {
                if (array_key_exists($paramName, $overrides)) {
                    $userInput[$paramName] = $overrides[$paramName];
                } elseif (isset($paramDef['default'])) {
                    $userInput[$paramName] = $paramDef['default'];
                }
            }
        }

        // allow arbitraries in overrides to pass through
        foreach ($overrides as $k => $v) {
            $userInput[$k] = $v;
        }

        // construct messages for AI
        $messagesToSend = [];
        if (!empty($systemContent)) {
            $messagesToSend[] = [
                'role' => 'system',
                'content' => $systemContent,
            ];
        }
        $messagesToSend[] = [
            'role' => 'user',
            'content' => json_encode(['input' => $userInput], JSON_UNESCAPED_UNICODE),
        ];

        // choose model
        $selectedModel = $model ?: ($session->getModel() ?: 'qwen/qwen3-32b');

        $this->log('info', 'QuickGen: sending request to model', [
            'sessionId' => $sessionId,
            'model' => $selectedModel,
            'messages_count' => count($messagesToSend),
            'user_input_preview' => mb_substr(json_encode($userInput, JSON_UNESCAPED_UNICODE), 0, 500),
            'options' => $options
        ]);

        // send to AIProvider (pass $options like temperature, max_tokens)
        $rawResponse = $this->aiProvider->sendMessage($selectedModel, $messagesToSend, $options);

        // try to decode first JSON object
        $decoded = null;
        if (is_string($rawResponse)) {
            $decoded = $this->attemptDecodeFirstJson($rawResponse);
        }

        // Normalizer / Validator
        $normalized = null;
        $schemaValid = false;
        $warnings = [];

        // model-signalled schema noncompliance handling
        if (is_array($decoded) && isset($decoded['error']) && $decoded['error'] === 'schema_noncompliant') {
            $this->log('warning', 'Model reported schema_noncompliant', ['reason' => $decoded['reason'] ?? null]);
            throw new RuntimeException('Model reported schema_noncompliant: ' . ($decoded['reason'] ?? 'no reason'));
        }

        if (is_array($decoded)) {
            // strict acceptance if it matches expected schema exactly
            $hasStrict = isset($decoded['mode'], $decoded['language'], $decoded['twister'], $decoded['metadata']) && is_array($decoded['metadata']);
            if ($hasStrict) {
                $normalized = $decoded;
                $schemaValid = true;
            } else {
                // collect common alternative candidate lists (twisters / twists / variants etc.)
                $alts = [];
                foreach (['twisters', 'twists', 'variants', 'results', 'items'] as $k) {
                    if (!empty($decoded[$k]) && is_array($decoded[$k])) {
                        foreach ($decoded[$k] as $c) {
                            if (is_string($c)) $alts[] = $c;
                        }
                    }
                }

                // build normalized skeleton
                $normalized = [
                    'mode' => $decoded['mode'] ?? ($userInput['mode'] ?? ($config['parameters']['mode']['default'] ?? null)),
                    'language' => $decoded['language'] ?? ($userInput['language'] ?? ($config['parameters']['language']['default'] ?? null)),
                    'twister' => '',
                    'metadata' => [],
                ];

                // primary twister selection heuristics
                if (!empty($decoded['twister']) && is_string($decoded['twister'])) {
                    $normalized['twister'] = $decoded['twister'];
                } elseif (!empty($alts)) {
                    $normalized['twister'] = $alts[0];
                } elseif (!empty($decoded['metadata']['alternatives']) && is_array($decoded['metadata']['alternatives'])) {
                    $normalized['twister'] = $decoded['metadata']['alternatives'][0] ?? '';
                } else {
                    // fallback: find first long string in top-level fields (heuristic)
                    foreach ($decoded as $v) {
                        if (is_string($v) && mb_strlen($v) > 10) {
                            $normalized['twister'] = $v;
                            break;
                        }
                    }
                }

                // merge decoded.metadata if available
                if (!empty($decoded['metadata']) && is_array($decoded['metadata'])) {
                    $normalized['metadata'] = $decoded['metadata'];
                }

                // attach alternatives array into metadata
                if (!isset($normalized['metadata']['alternatives'])) {
                    $normalized['metadata']['alternatives'] = $alts;
                } else {
                    $normalized['metadata']['alternatives'] = array_values(array_unique(array_merge((array)$normalized['metadata']['alternatives'], $alts)));
                }

                // compute metadata.wordCount if missing
                if (empty($normalized['metadata']['wordCount']) && $normalized['twister']) {
                    $words = preg_split('/\s+/u', trim($normalized['twister']));
                    $count = 0;
                    foreach ($words as $w) {
                        $w = trim($w, " \t\n\r\0\x0B.,;:!?()[]\"'“”„—-");
                        if ($w !== '') $count++;
                    }
                    $normalized['metadata']['wordCount'] = $count;
                }

                // compute metadata.firstLetter if missing
                if (empty($normalized['metadata']['firstLetter']) && $normalized['twister']) {
                    if (preg_match('/\p{L}/u', $normalized['twister'], $m)) {
                        $normalized['metadata']['firstLetter'] = mb_strtoupper($m[0], 'UTF-8');
                    } else {
                        $normalized['metadata']['firstLetter'] = null;
                    }
                }

                // copy some helpful fields into metadata if present
                if (!isset($normalized['metadata']['language']) && isset($decoded['language'])) {
                    $normalized['metadata']['language'] = $decoded['language'];
                }
                if (!isset($normalized['metadata']['mode']) && isset($decoded['mode'])) {
                    $normalized['metadata']['mode'] = $decoded['mode'];
                }
                if (!isset($normalized['metadata']['punctuation']) && isset($decoded['punctuation'])) {
                    $normalized['metadata']['punctuation'] = $decoded['punctuation'];
                }

                // basic strict validation
                $schemaValid = is_string($normalized['mode']) && is_string($normalized['language'])
                    && is_string($normalized['twister']) && is_array($normalized['metadata'])
                    && isset($normalized['metadata']['wordCount']);

                if (!$schemaValid) {
                    $warnings[] = 'Normalized output did not pass strict validation.';
                    $this->log('warning', 'Normalization produced non-strict result', ['sessionId' => $sessionId, 'normalized_preview' => mb_substr(json_encode($normalized, JSON_UNESCAPED_UNICODE), 0, 800)]);
                }
            }
        } else {
            $warnings[] = 'No JSON could be decoded from model response.';
            $this->log('warning', 'No JSON decoded from model response', ['sessionId' => $sessionId, 'raw_preview' => mb_substr((string)$rawResponse, 0, 800)]);
        }

        // prepare result
        $result = [
            'ok' => true,
            'model' => $selectedModel,
            'request_messages' => $messagesToSend,
            'raw_response' => $rawResponse,
            'decoded_json' => $decoded,
            'normalized' => $normalized,
            'schema_valid' => $schemaValid,
            'warnings' => $warnings,
            'error' => null,
        ];

        return $result;
    }

    /**
     * Extract first JSON object from arbitrary text (recursive balanced braces)
     */
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

    /**
     * Attempt to decode full text or extract first JSON object and decode it.
     */
    private function attemptDecodeFirstJson(string $text): ?array
    {
        $trimmed = trim($text);
        // direct decode attempt
        $decoded = json_decode($trimmed, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // attempt extraction of first JSON object
        $jsonStr = $this->extractFirstJsonObject($text);
        if ($jsonStr !== null) {
            $decoded2 = json_decode($jsonStr, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded2;
            }
        }

        return null;
    }

    /**
     * Logging wrapper for FileLogger
     */
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
