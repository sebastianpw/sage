<?php
// public/api/generator_forge_api.php
// ─────────────────────────────────────────────────────────────────────────────
// NEW parallel API — PDO-only, no Doctrine.
// Used exclusively by generator_forge.php.
// The existing generate.php / generator_actions.php are UNTOUCHED.
//
// Routes (POST JSON body with "action" key):
//   list          → list generators for current user
//   get           → get one generator (by id)
//   create        → create new generator
//   update        → update existing generator
//   delete        → delete generator (owner only)
//   toggle        → toggle active flag
//   copy          → duplicate generator
//   update_order  → save drag-drop order
//   get_areas     → list all display areas
//   get_models    → list available AI models
//   generate      → call AI and return result
//   get_info      → return config info (param schema, no AI call)
// ─────────────────────────────────────────────────────────────────────────────

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../env_locals.php';

// Load our new PDO repository (no autoloader needed — direct require)
require_once dirname(__DIR__, 2) . '/src/Service/GeneratorRepository.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Auth ──────────────────────────────────────────────────────────────────────
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(401);
    exit(json_encode(['ok' => false, 'error' => 'Not authenticated']));
}
$userId = (int)$userId;

// ── Input ─────────────────────────────────────────────────────────────────────
$raw    = file_get_contents('php://input');
$input  = json_decode($raw ?: '{}', true) ?? [];
// Also accept form-posted data
foreach ($_POST as $k => $v) {
    if (!isset($input[$k])) {
        $input[$k] = $v;
    }
}

$action = trim((string)($input['action'] ?? $_GET['action'] ?? ''));

// ── Repository ────────────────────────────────────────────────────────────────
/** @var \PDO $pdo */
$repo = new \App\Service\GeneratorRepository($pdo);

// ── Admin check ───────────────────────────────────────────────────────────────
function isAdmin(int $userId, \PDO $pdo): bool
{
    try {
        $stmt = $pdo->prepare('SELECT is_admin FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return !empty($row['is_admin']);
    } catch (\Throwable $e) {
        return false;
    }
}

$userIsAdmin = isAdmin($userId, $pdo);

// ── Helpers ───────────────────────────────────────────────────────────────────
function ok(mixed $data = null, string $message = ''): string
{
    $r = ['ok' => true];
    if ($data !== null)    $r['data']    = $data;
    if ($message !== '')   $r['message'] = $message;
    return json_encode($r, JSON_UNESCAPED_UNICODE);
}

function fail(string $error, int $code = 400): string
{
    http_response_code($code);
    return json_encode(['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
}

function intInput(array $input, string $key, int $default = 0): int
{
    return isset($input[$key]) ? (int)$input[$key] : $default;
}

function strInput(array $input, string $key, string $default = ''): string
{
    return isset($input[$key]) ? trim((string)$input[$key]) : $default;
}

// ── Available AI models (with optgroup catalog) ───────────────────────────────
/**
 * Returns the full grouped model catalog from AIProvider.
 * Shape: [ 'Group Label' => [ ['id'=>'...','name'=>'...'], … ], … ]
 * Falls back to a minimal hardcoded catalog if the class/method is unavailable.
 */
function getAvailableModelsCatalog(): array
{
    if (class_exists(\App\Core\AIProvider::class) &&
        method_exists(\App\Core\AIProvider::class, 'getModelCatalog')) {
        $catalog = \App\Core\AIProvider::getModelCatalog();
        if (!empty($catalog)) {
            return $catalog;
        }
    }

    // Fallback flat catalog grouped by provider
    return [
        'OpenAI' => [
            ['id' => 'gpt-4o',        'name' => 'GPT-4o'],
            ['id' => 'gpt-4o-mini',   'name' => 'GPT-4o Mini'],
            ['id' => 'gpt-4-turbo',   'name' => 'GPT-4 Turbo'],
            ['id' => 'gpt-3.5-turbo', 'name' => 'GPT-3.5 Turbo'],
        ],
        'Anthropic' => [
            ['id' => 'claude-opus-4-6',            'name' => 'Claude Opus 4.6'],
            ['id' => 'claude-sonnet-4-6',           'name' => 'Claude Sonnet 4.6'],
            ['id' => 'claude-haiku-4-5-20251001',   'name' => 'Claude Haiku 4.5'],
        ],
    ];
}

// ── Bulletproof JSON parser ───────────────────────────────────────────────────
/**
 * Attempt to decode JSON from AI response with progressively more lenient strategies.
 * Returns ['parsed' => array|null, 'strategy' => string, 'error' => string|null]
 */
function robustJsonParse(string $raw): array
{
    $text = trim($raw);

    // 1. Direct decode
    $decoded = json_decode($text, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return ['parsed' => $decoded, 'strategy' => 'direct', 'error' => null];
    }

    // 2. Strip markdown code fences  ```json … ```
    $stripped = preg_replace('/^```(?:json)?\s*\n?(.*?)\n?```$/s', '$1', $text);
    if ($stripped !== $text) {
        $decoded = json_decode(trim($stripped), true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return ['parsed' => $decoded, 'strategy' => 'stripped_fences', 'error' => null];
        }
    }

    // 3. Extract first balanced JSON object
    if (preg_match('/(\{(?:[^{}]|(?R))*\})/s', $text, $m)) {
        $decoded = json_decode($m[1], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return ['parsed' => $decoded, 'strategy' => 'extracted_object', 'error' => null];
        }
    }

    // 4. Extract first JSON array
    if (preg_match('/(\[(?:[^\[\]]|(?R))*\])/s', $text, $m)) {
        $decoded = json_decode($m[1], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return ['parsed' => ['items' => $decoded], 'strategy' => 'extracted_array', 'error' => null];
        }
    }

    // 5. Repair common AI mistakes: trailing commas, single quotes, unquoted keys
    $repaired = $text;
    // Remove trailing commas before } or ]
    $repaired = preg_replace('/,\s*([\}\]])/m', '$1', $repaired);
    // Replace single-quote strings (careful, very basic)
    $repaired = preg_replace("/(?<![\\\\])'([^']*)'(?=[\\s:,\\}\\]])/", '"$1"', $repaired);
    $decoded  = json_decode($repaired, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        return ['parsed' => $decoded, 'strategy' => 'repaired', 'error' => null];
    }

    // 6. Give up — return raw as a wrapper
    return [
        'parsed'   => null,
        'strategy' => 'failed',
        'error'    => json_last_error_msg(),
    ];
}

// ── Route dispatcher ──────────────────────────────────────────────────────────
try {
    switch ($action) {

        // ── list ─────────────────────────────────────────────────────────────
        case 'list': {
            $filterArea = strInput($input, 'filter_area');
            $search     = strInput($input, 'search');
            $rows       = $repo->listForUser($userId, $filterArea, $search);
            exit(ok($rows));
        }

        // ── get ──────────────────────────────────────────────────────────────
        case 'get': {
            $id  = intInput($input, 'id');
            $rec = $repo->findById($id);
            if (!$rec || ($rec->userId !== $userId && !$rec->isPublic && !$userIsAdmin)) {
                exit(fail('Generator not found or access denied', 404));
            }
            exit(ok($rec->toArray()));
        }

        // ── get_info (no AI call, just param schema) ──────────────────────────
        case 'get_info': {
            $configId = strInput($input, 'config_id');
            if (!$configId) exit(fail('Missing config_id'));

            $rec = $repo->findActiveForUser($configId, $userId);
            if (!$rec) exit(fail('Generator not found', 404));

            exit(ok([
                'config_id'    => $rec->configId,
                'title'        => $rec->title,
                'model'        => $rec->model,
                'parameters'   => $rec->parameters,
                'output_schema'=> $rec->outputSchema,
                'uses_oracle'  => $rec->oracleConfig !== null,
            ]));
        }

        // ── create ────────────────────────────────────────────────────────────
        case 'create': {
            $title      = strInput($input, 'title');
            $model      = strInput($input, 'model');
            $configJson = strInput($input, 'config_json');
            if (!$title || !$model || !$configJson) {
                exit(fail('title, model, config_json are required'));
            }
            $isPublic       = !empty($input['is_public']) && $userIsAdmin;
            $oracleConfig   = !empty($input['oracle_config']) && is_array($input['oracle_config'])
                                ? $input['oracle_config'] : [];
            $displayAreaIds = !empty($input['display_area_ids']) && is_array($input['display_area_ids'])
                                ? array_map('intval', $input['display_area_ids']) : [];

            $newId = $repo->createFromConfigJson(
                $userId, $title, $model, $configJson,
                $isPublic, $oracleConfig, $displayAreaIds
            );
            exit(ok(['id' => $newId], 'Generator created successfully'));
        }

        // ── update ────────────────────────────────────────────────────────────
        case 'update': {
            $id  = intInput($input, 'id');
            $rec = $repo->findById($id);
            if (!$rec) exit(fail('Generator not found', 404));
            if ($rec->userId !== $userId && !$userIsAdmin) exit(fail('Access denied', 403));

            $title      = strInput($input, 'title')      ?: $rec->title;
            $model      = strInput($input, 'model')       ?: $rec->model;
            $configJson = strInput($input, 'config_json') ?: $rec->toConfigJson();
            $isPublic   = isset($input['is_public']) ? (bool)$input['is_public'] && $userIsAdmin : $rec->isPublic;
            // is_active: explicit boolean when sent from the forge edit modal
            $isActive   = isset($input['is_active']) ? (bool)$input['is_active'] : $rec->active;
            $listOrder  = isset($input['list_order']) ? (int)$input['list_order'] : $rec->listOrder;

            $oracleConfig = null;
            if (isset($input['oracle_config'])) {
                $oracleConfig = is_array($input['oracle_config']) && !empty($input['oracle_config'])
                    ? $input['oracle_config'] : null;
            } else {
                $oracleConfig = $rec->oracleConfig;
            }

            $displayAreaIds = isset($input['display_area_ids']) && is_array($input['display_area_ids'])
                                ? array_map('intval', $input['display_area_ids']) : [];

            $repo->updateFromConfigJson(
                $id, $title, $model, $configJson,
                $isPublic, $oracleConfig, $displayAreaIds, $isActive, $listOrder
            );
            exit(ok(null, 'Generator updated successfully'));
        }

        // ── delete ────────────────────────────────────────────────────────────
        case 'delete': {
            $id  = intInput($input, 'id');
            $rec = $repo->findById($id);
            if (!$rec) exit(fail('Generator not found', 404));
            if ($rec->userId !== $userId && !$userIsAdmin) exit(fail('Access denied', 403));

            $repo->delete($id);
            exit(ok(null, 'Generator deleted'));
        }

        // ── toggle ────────────────────────────────────────────────────────────
        case 'toggle': {
            $id  = intInput($input, 'id');
            $rec = $repo->findById($id);
            if (!$rec) exit(fail('Generator not found', 404));
            if ($rec->userId !== $userId && !$userIsAdmin) exit(fail('Access denied', 403));

            $newState = $repo->toggleActive($id);
            $r = ['ok' => true, 'active' => $newState, 'message' => $newState ? 'Activated' : 'Deactivated'];
            exit(json_encode($r, JSON_UNESCAPED_UNICODE));
        }

        // ── copy ──────────────────────────────────────────────────────────────
        case 'copy': {
            $id  = intInput($input, 'id');
            $rec = $repo->findById($id);
            if (!$rec) exit(fail('Generator not found', 404));
            if ($rec->userId !== $userId && !$rec->isPublic && !$userIsAdmin) {
                exit(fail('Access denied', 403));
            }
            $newId = $repo->duplicate($id, $userId);
            exit(ok(['new_id' => $newId], 'Generator copied'));
        }

        // ── update_order ──────────────────────────────────────────────────────
        case 'update_order': {
            $ids = $input['ids'] ?? [];
            if (!is_array($ids)) exit(fail('ids must be an array'));
            $repo->updateOrder(array_map('intval', $ids));
            exit(ok(null, 'Order saved'));
        }

        // ── get_areas ─────────────────────────────────────────────────────────
        case 'get_areas': {
            $areas = $repo->getAllDisplayAreas();
            exit(ok($areas));
        }

        // ── get_dictionaries ──────────────────────────────────────────────────
        case 'get_dictionaries': {
            $dictManager = new \App\Dictionary\DictionaryManager($pdo);
            $all   = $dictManager->getAllDictionaries();
            $dicts = array_map(fn($d) => ['id' => $d['id'], 'title' => $d['title']], $all);
            exit(ok($dicts));
        }

        // ── get_models ────────────────────────────────────────────────────────
        case 'get_models': {
            $catalog      = getAvailableModelsCatalog();
            $defaultModel = '';
            if (class_exists(\App\Core\AIProvider::class) &&
                defined('\App\Core\AIProvider::DEFAULT_MODEL')) {
                $defaultModel = \App\Core\AIProvider::DEFAULT_MODEL;
            }
            exit(ok(['catalog' => $catalog, 'default' => $defaultModel]));
        }

        // ── generate ─────────────────────────────────────────────────────────
        case 'generate': {
            $configId = strInput($input, 'config_id');
            if (!$configId) {
                // Also accept by numeric id
                $numId = intInput($input, 'generator_id');
                if ($numId) {
                    $rec = $repo->findById($numId);
                    if ($rec && ($rec->userId === $userId || $rec->isPublic)) {
                        $configId = $rec->configId;
                    }
                }
            }
            if (!$configId) exit(fail('Missing config_id'));

            $rec = $repo->findActiveForUser($configId, $userId);
            if (!$rec) exit(fail('Generator not found or inactive', 404));

            // Build params (strip internal keys)
            $params = $input;
            foreach (['action', 'config_id', 'generator_id', 'temperature', 'max_tokens', '_info'] as $k) {
                unset($params[$k]);
            }

            $aiOptions = [];
            if (isset($input['temperature'])) $aiOptions['temperature'] = (float)$input['temperature'];
            if (isset($input['max_tokens']))  $aiOptions['max_tokens']  = (int)$input['max_tokens'];

            // ── Oracle hint ──────────────────────────────────────────────────
            $oracleHint = null;
            if ($rec->oracleConfig && !empty($rec->oracleConfig['dictionary_ids'])) {
                try {
                    $bloom      = new \App\Oracle\Bloom();
                    $oracleHint = $bloom->generateHint(
                        $rec->oracleConfig['dictionary_ids'],
                        $rec->oracleConfig['num_words']   ?? 200,
                        $rec->oracleConfig['error_rate']  ?? 0.01,
                        isset($params['random_oracle_seed']) ? (int)$params['random_oracle_seed'] : null
                    );
                } catch (\Throwable $e) {
                    // Non-fatal
                }
            }

            // ── Build system message ──────────────────────────────────────────
            $sysParts = [];
            if ($rec->systemRole)    $sysParts[] = $rec->systemRole;
            if ($rec->instructions)  $sysParts[] = implode("\n", $rec->instructions);
            if ($oracleHint && !empty($oracleHint['meta']['sampled_lemmas'])) {
                $words      = implode(', ', $oracleHint['meta']['sampled_lemmas']);
                $sysParts[] = "INSPIRATIONAL HINT: Draw inspiration from these words (tone/theme/subject): [{$words}]. USE them — we want variety!";
            }
            $systemMsg = implode("\n\n", $sysParts);

            // ── Build user input ──────────────────────────────────────────────
            $userInput = [];
            foreach ($rec->parameters as $key => $def) {
                $userInput[$key] = $params[$key] ?? $def['default'] ?? null;
            }
            foreach ($params as $k => $v) {
                if (!isset($userInput[$k])) $userInput[$k] = $v;
            }

            $messages = [
                ['role' => 'system', 'content' => $systemMsg],
                ['role' => 'user',   'content' => json_encode(['input' => $userInput], JSON_UNESCAPED_UNICODE)],
            ];

            // ── Call AI provider ──────────────────────────────────────────────
            $startTime   = microtime(true);
            $aiProvider  = $spw->getAIProvider();
            $rawResponse = $aiProvider->sendMessage($rec->model, $messages, $aiOptions);
            $elapsedMs   = (int)((microtime(true) - $startTime) * 1000);

            // ── Parse response ────────────────────────────────────────────────
            $parseResult = robustJsonParse($rawResponse);

            // Determine desired output type from output_schema
            $outputType = $rec->outputSchema['type'] ?? 'object';

            // Friendly result extraction
            $friendlyResult = null;
            if ($parseResult['parsed']) {
                $parsed = $parseResult['parsed'];
                // If only one key in the result and it's a string → surface it directly
                if (count($parsed) === 1) {
                    $v = reset($parsed);
                    if (is_string($v)) {
                        $friendlyResult = $v;
                    }
                }
                // Look for common "result" or "text" or "content" keys
                foreach (['result', 'text', 'content', 'output', 'value', 'answer'] as $k) {
                    if (isset($parsed[$k]) && is_string($parsed[$k])) {
                        $friendlyResult = $parsed[$k];
                        break;
                    }
                }
            }

            $response = [
                'ok'              => true,
                'data'            => $parseResult['parsed'],
                'raw_response'    => $rawResponse,
                'parse_strategy'  => $parseResult['strategy'],
                'parse_error'     => $parseResult['error'],
                'friendly_result' => $friendlyResult,
                'elapsed_ms'      => $elapsedMs,
                'model'           => $rec->model,
            ];

            exit(json_encode($response, JSON_UNESCAPED_UNICODE));
        }

        default: {
            exit(fail("Unknown action: {$action}"));
        }
    }

} catch (\InvalidArgumentException $e) {
    exit(fail($e->getMessage()));
} catch (\Throwable $e) {
    http_response_code(500);
    exit(json_encode([
        'ok'    => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ], JSON_UNESCAPED_UNICODE));
}
