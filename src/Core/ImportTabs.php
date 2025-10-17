<?php
namespace App\Core;

/**
 * ImportTabs
 *
 * Parse newline lists of links (Chrome export style) using an LLM (JSON-only response)
 * with robust fallback to local heuristics. Also provides applyToDatabase() to insert
 * parsed items into your pages_dashboard table (or another table name).
 *
 * Required table columns (minimal assumptions):
 *  - id (auto increment)
 *  - name (varchar)
 *  - href (varchar)
 *  - level (int)
 *  - parent_id (nullable int)
 *  - position (int)
 */
class ImportTabs
{
    private $endpoint;
    private $model;
    private $apiKey;
    private $fileLogger;

    public function __construct($fileLogger = null, array $opts = [])
    {
        $this->fileLogger = $fileLogger;
        $this->endpoint = $opts['endpoint'] ?? getenv('POLLINATIONS_ENDPOINT') ?: 'https://text.pollinations.ai/v1/chat/completions';
        $this->model = $opts['model'] ?? getenv('POLLINATIONS_MODEL') ?: 'qwen2.5-coder-32b-instruct';
        $this->apiKey = $opts['api_key'] ?? getenv('POLLINATIONS_API_KEY') ?? null;

        if (empty($this->apiKey)) {
            $home = getenv('HOME') ?: (isset($_SERVER['HOME']) ? $_SERVER['HOME'] : null);
            if ($home) {
                $tokenPath = $home . '/.pollinationsaitoken';
                if (is_readable($tokenPath)) {
                    $this->apiKey = trim(@file_get_contents($tokenPath));
                }
            }
        }
    }

    /**
     * Parse raw pasted text (newline-separated links / Chrome export fragments).
     * Returns array: ['ok'=>bool, 'items'=>[], 'skipped'=>[], 'raw_llm'=>string|null, 'error'=>null|string]
     *
     * @param string $rawText
     * @param int|null $parentId parent for imported items (default 999)
     * @param array $opts (optional) - 'force_local_parse' => true to skip LLM
     */
    public function parseRawImport(string $rawText, ?int $parentId = 999, array $opts = []): array
    {
        $rawText = trim((string)$rawText);
        if ($rawText === '') {
            return ['ok' => false, 'items' => [], 'skipped' => [], 'raw_llm' => null, 'error' => 'empty input'];
        }

        $prompt = $this->buildPromptForLinks($rawText, $parentId);
        $rawLlm = null;
        $parsed = null;

        if (!($opts['force_local_parse'] ?? false)) {
            try {
                $rawLlm = $this->callLLM($prompt);
                // primary attempt: json decode whole response
                $maybe = json_decode($rawLlm, true);
                if (is_array($maybe) && isset($maybe['items'])) {
                    $parsed = $maybe;
                } else {
                    // try to extract first JSON object
                    $candidate = $this->extractFirstJsonObject($rawLlm);
                    if ($candidate !== null) {
                        $maybe2 = json_decode($candidate, true);
                        if (is_array($maybe2) && isset($maybe2['items'])) {
                            $parsed = $maybe2;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $this->log('error', 'LLM call failed, falling back to local parse', ['error' => $e->getMessage()]);
            }
        }

        // If the LLM didn't produce a structured result, fallback to local heuristics
        if (!is_array($parsed)) {
            $this->log('info', 'Falling back to local parse', []);
            $items = $this->localParseLinesToItems($rawText, $parentId);
            return ['ok' => true, 'items' => $items, 'skipped' => [], 'raw_llm' => $rawLlm, 'error' => null];
        }

        $validated = $this->normalizeParsedItems($parsed['items'] ?? [], $parentId);
        $skipped = $this->normalizeSkipped($parsed['skipped'] ?? []);
        return ['ok' => true, 'items' => $validated, 'skipped' => $skipped, 'raw_llm' => $rawLlm, 'error' => null];
    }

    /**
     * Insert items into DB table. Items array should be objects with fields:
     *  - name, href, level, parent_id, suggested_position (optional)
     *
     * Returns: ['ok'=>true, 'inserted'=>int, 'inserted_ids'=>[], 'skipped'=>[], 'error'=>null]
     */
    public function applyToDatabase(\PDO $pdo, string $table = 'pages_dashboard', array $items = [], array $opts = []): array
    {
        if (empty($items)) {
            return ['ok' => true, 'inserted' => 0, 'inserted_ids' => [], 'skipped' => [], 'error' => null];
        }

        // Normalize and sort by suggested_position (if present) to respect ordering
        usort($items, function($a, $b){
            $aa = isset($a['suggested_position']) ? (int)$a['suggested_position'] : 0;
            $bb = isset($b['suggested_position']) ? (int)$b['suggested_position'] : 0;
            return $aa <=> $bb;
        });

        $dedupe = $opts['dedupe'] ?? true;
        $inserted = 0;
        $inserted_ids = [];
        $skipped = [];

        try {
            $pdo->beginTransaction();

            // Cache current max position per parent so we append in order
            $maxPosStmt = $pdo->prepare("SELECT COALESCE(MAX(position), -1) as mx FROM {$table} WHERE parent_id " . (is_null($items[0]['parent_id']) ? "IS NULL" : "= :parent"));
            $insStmt = $pdo->prepare("INSERT INTO {$table} (name, href, level, parent_id, position) VALUES (:name, :href, :level, :parent_id, :position)");
            $checkHrefStmt = $pdo->prepare("SELECT id FROM {$table} WHERE href = :href LIMIT 1");

            // We'll maintain an in-memory map for current max per parent id encountered
            $maxMap = [];

            foreach ($items as $it) {
                $name = trim((string)($it['name'] ?? ''));
                $href = trim((string)($it['href'] ?? ''));
                $level = isset($it['level']) ? (int)$it['level'] : 2;
                $parent = array_key_exists('parent_id', $it) ? ($it['parent_id'] === '' ? null : (int)$it['parent_id']) : null;

                if ($href === '' || !filter_var($href, FILTER_VALIDATE_URL)) {
                    $skipped[] = ['href' => $href, 'reason' => 'invalid or empty href'];
                    continue;
                }

                if ($dedupe) {
                    $checkHrefStmt->execute([':href' => $href]);
                    $found = $checkHrefStmt->fetchColumn();
                    if ($found) {
                        $skipped[] = ['href' => $href, 'reason' => 'duplicate href (already exists)'];
                        continue;
                    }
                }

                // determine next position for this parent
                $pkey = is_null($parent) ? 'NULL' : (string)$parent;
                if (!isset($maxMap[$pkey])) {
                    // compute max position for this parent
                    if (is_null($parent)) {
                        // parent_id IS NULL
                        $stmt = $pdo->prepare("SELECT COALESCE(MAX(position), -1) as mx FROM {$table} WHERE parent_id IS NULL");
                        $stmt->execute();
                    } else {
                        $stmt = $pdo->prepare("SELECT COALESCE(MAX(position), -1) as mx FROM {$table} WHERE parent_id = :parent");
                        $stmt->execute([':parent' => $parent]);
                    }
                    $mx = (int)$stmt->fetchColumn();
                    $maxMap[$pkey] = $mx;
                }
                $nextPos = $maxMap[$pkey] + 1;
                // Insert
                $insStmt->execute([
                    ':name' => mb_substr($name ?: $href, 0, 255),
                    ':href' => $href,
                    ':level' => $level,
                    ':parent_id' => $parent,
                    ':position' => $nextPos
                ]);
                $newId = (int)$pdo->lastInsertId();
                $inserted++;
                $inserted_ids[] = $newId;
                // bump map
                $maxMap[$pkey] = $nextPos;
            }

            $pdo->commit();
            return ['ok' => true, 'inserted' => $inserted, 'inserted_ids' => $inserted_ids, 'skipped' => $skipped, 'error' => null];
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $this->log('error', 'applyToDatabase failed', ['error' => $e->getMessage()]);
            return ['ok' => false, 'inserted' => $inserted, 'inserted_ids' => $inserted_ids, 'skipped' => $skipped, 'error' => $e->getMessage()];
        }
    }

    /* ----------------- Helpers ----------------- */

    private function buildPromptForLinks(string $rawText, ?int $parentId = 999): string
    {
        // Use the user's exact instruction template (JSON-only) adapted here:
        $instructions = <<<TXT
You are a JSON-only assistant. INPUT_TEXT is a newline-separated list of URLs and/or lines copied from a Chrome bookmark export.
Task: For each link produce a JSON object with:
- name: a short title (do your best from the URL; prefer site title if obvious; avoid punctuation at ends)
- href: absolute URL (validate and normalize)
- level: integer 2 (we will import these as children)
- parent_id: PARENT_ID
- suggested_position: integer relative ordering starting at 0 (first item => 0)

Output must be valid JSON and the top-level must be:
{ "ok": true, "items": [ { "name": "...", "href": "...", "level": 2, "parent_id": PARENT_ID, "suggested_position": 0 }, ... ] }

If any URL seems invalid/unparseable, omit it and include it in a top-level "skipped": [ { "line": "...", "reason": "invalid url or couldn't find title" } ].

Do NOT produce any explanatory text — only the JSON.

INPUT_TEXT:
TXT;
        $payload = $instructions . "\nPARENT_ID: " . (int)$parentId . "\n\n" . $rawText;
        return $payload;
    }

    private function callLLM(string $prompt): string
    {
        if (empty($this->apiKey)) {
            throw new \RuntimeException('No Pollinations API key found. Set POLLINATIONS_API_KEY or place token in ~/.pollinationsaitoken');
        }

        $payloadArr = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a JSON-only importer assistant. Return only JSON.'],
                ['role' => 'user', 'content' => $prompt]
            ]
        ];
        $payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);

        $this->log('info', 'LLM request', ['endpoint' => $this->endpoint, 'model' => $this->model, 'preview' => mb_substr($payload, 0, 800)]);
        $ch = curl_init($this->endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: spw-importtabs/1.0',
            'Authorization: Bearer ' . $this->apiKey
        ];
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);

        $response = curl_exec($ch);
        $curlErr = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->log('info', 'LLM response', ['http_code' => $httpCode, 'curl_err' => $curlErr ?: null, 'snippet' => is_string($response) ? mb_substr($response, 0, 1500) : null]);

        if ($response === false || $curlErr) {
            throw new \RuntimeException('Curl error when calling LLM: ' . ($curlErr ?: 'unknown'));
        }
        if ($httpCode < 200 || $httpCode >= 300) {
            $snippet = is_string($response) ? mb_substr($response, 0, 2000) : '';
            throw new \RuntimeException("LLM endpoint returned HTTP {$httpCode}: {$snippet}");
        }

        // Try to unwrap typical response shapes like OpenAI-style
        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded['choices'][0]['message']['content'])) {
            return $decoded['choices'][0]['message']['content'];
        }
        // Otherwise return raw response for extraction by caller
        return (string)$response;
    }

    private function extractFirstJsonObject(string $text): ?string
    {
        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $text, $m)) {
            $candidate = $m[0];
            json_decode($candidate);
            if (json_last_error() === JSON_ERROR_NONE) return $candidate;
        }
        return null;
    }

    private function normalizeParsedItems(array $items, ?int $parentId = 999): array
    {
        $out = []; $pos = 0;
        foreach ($items as $it) {
            $href = trim((string)($it['href'] ?? ''));
            if ($href === '') continue;
            if (!filter_var($href, FILTER_VALIDATE_URL)) {
                // try to add scheme if plausible
                if (preg_match('#^[\w\.-]+\.[a-z]{2,}(/.*)?$#i', $href)) {
                    $href = 'http://' . $href;
                    if (!filter_var($href, FILTER_VALIDATE_URL)) continue;
                } else continue;
            }
            $name = trim((string)($it['name'] ?? '')) ?: $href;
            $level = isset($it['level']) ? (int)$it['level'] : 2;
            $pid = isset($it['parent_id']) ? (int)$it['parent_id'] : $parentId;
            $suggested = isset($it['suggested_position']) ? (int)$it['suggested_position'] : $pos;
            $out[] = [
                'name' => mb_substr($name, 0, 255),
                'href' => $href,
                'level' => $level,
                'parent_id' => $pid,
                'suggested_position' => $suggested
            ];
            $pos++;
        }
        return $out;
    }

    private function normalizeSkipped($skipped): array
    {
        if (!is_array($skipped)) return [];
        $out = [];
        foreach ($skipped as $s) {
            $out[] = [
                'line' => isset($s['line']) ? (string)$s['line'] : '',
                'reason' => isset($s['reason']) ? (string)$s['reason'] : 'skipped'
            ];
        }
        return $out;
    }

    private function localParseLinesToItems(string $rawText, ?int $parentId = 999): array
    {
        $lines = preg_split('/\r?\n/', $rawText);
        $pos = 0; $out = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (preg_match('#https?://[^\s\'"<]+#i', $line, $m)) {
                $url = rtrim($m[0], '.,;');
            } elseif (filter_var($line, FILTER_VALIDATE_URL)) {
                $url = $line;
            } else {
                if (preg_match('#^[\w\.-]+\.[a-z]{2,}(/.*)?$#i', $line)) {
                    $url = 'http://' . $line;
                } else {
                    // maybe it's "Title - https://..." etc.; attempt to find last token
                    if (preg_match_all('#https?://[^\s\'"<]+#i', $line, $ms)) {
                        $url = end($ms[0]);
                        $url = rtrim($url, '.,;');
                    } else {
                        continue;
                    }
                }
            }
            if (!filter_var($url, FILTER_VALIDATE_URL)) continue;
            $host = parse_url($url, PHP_URL_HOST) ?: '';
            $path = parse_url($url, PHP_URL_PATH) ?: '';
            $name = $host . ($path !== '/' ? $path : '');
            $out[] = [
                'name' => mb_substr($name ?: $url, 0, 255),
                'href' => $url,
                'level' => 2,
                'parent_id' => $parentId,
                'suggested_position' => $pos++
            ];
        }
        return $out;
    }

    private function log(string $level, string $message, array $context = [])
    {
        if ($this->fileLogger && is_object($this->fileLogger)) {
            try {
                if ($level === 'info' && method_exists($this->fileLogger, 'info')) {
                    $this->fileLogger->info([$message => $context]);
                } elseif ($level === 'error' && method_exists($this->fileLogger, 'error')) {
                    $this->fileLogger->error([$message => $context]);
                } else {
                    if (method_exists($this->fileLogger, 'info')) $this->fileLogger->info([$message => $context]);
                }
            } catch (\Throwable $e) { /* ignore logger errors */ }
        }
    }
}
