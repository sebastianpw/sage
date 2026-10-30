<?php
/**
 * bash/agent_bg_runner.php
 * Universal CLI background runner for ALL AI Agent modules.
 * Usage: php bash/agent_bg_runner.php <module_prefix> <session_id> <user_id>
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

require dirname(__DIR__) . '/public/bootstrap.php';

$modulePrefix = strtolower(trim($argv[1] ?? ''));
$sessionId    = $argv[2] ?? '';
$userId       = (int)($argv[3] ?? 0);

if (!$modulePrefix || !$sessionId || !$userId) {
    die("Missing arguments. Usage: php agent_bg_runner.php <module_prefix> <session_id> <user_id>\n");
}

$logFile = PROJECT_ROOT . "/temp/{$modulePrefix}_bg.log";
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0777, true);
}

function bg_log($msg) {
    global $logFile, $sessionId, $modulePrefix;
    $time = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$time] [{$modulePrefix}:{$sessionId}] $msg\n", FILE_APPEND);
}

bg_log("--- Starting agent background worker ---");

set_time_limit(1800);
ini_set('memory_limit', '1024M');

global $spw;
$pdo = $spw->getPDO();

try {
    // 1. Resolve concrete module via Factory
    $agent = \App\Core\AgentFactory::create($modulePrefix, $pdo);
    $tablePrefix = $agent->getModulePrefix();

    // 2. Fetch Session Settings (lore & model) from unified agent table
    $stmt = $pdo->prepare("SELECT model, lore FROM `agent_chat_sessions` WHERE id = ? AND user_id = ? AND module = ?");
    $stmt->execute([$sessionId, $userId, $modulePrefix]);
    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    $model = ($session && isset($session['model'])) ? $session['model'] : \App\Core\AIProvider::getDefaultModel();
    $lore  = ($session && isset($session['lore'])) ? $session['lore'] : '';

    // 3. Fetch Live Database Context via Module hook
    $contextState = $agent->getCurrentStateContext($sessionId, $userId);

    // 4. Build System Prompt via Module method
    $systemPrompt = $agent->getSystemPrompt($lore);
    $apiMessages = [['role' => 'system', 'content' => $systemPrompt]];

    // 5. Load Chat History from unified agent table
    $stmt = $pdo->prepare("SELECT role, content FROM `agent_chat_messages` WHERE session_id = ? ORDER BY ts ASC");
    $stmt->execute([$sessionId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $lastUserMsg = array_pop($history);

    foreach ($history as $msg) {
        $apiMessages[] = [
            'role' => $msg['role'] === 'user' ? 'user' : 'assistant',
            'content' => $msg['content']
        ];
    }

    // Append live DB context to the active user request
    $finalUserContent = "--- CURRENT DATABASE WORKING SET ({$tablePrefix}) ---\n" . $contextState . "\n\n--- USER REQUEST ---\n" . ($lastUserMsg['content'] ?? '');
    $apiMessages[] = ['role' => 'user', 'content' => $finalUserContent];

    // Anthropic / Nova cache ephemeral optimization
    if (stripos($model, 'claude') !== false || stripos($model, 'nova') !== false) {
        if (isset($apiMessages[0])) {
            $apiMessages[0]['content'] = [
                ['type' => 'text', 'text' => $apiMessages[0]['content'], 'cache_control' => ['type' => 'ephemeral']]
            ];
        }
    }

    // 6. Query AI Provider
    bg_log("Calling AI model ($model)...");
    $aiProvider = $spw->getAIProvider();
    $startTime = microtime(true);
    $aiResponse = $aiProvider->sendMessage($model, $apiMessages, ['temperature' => 0.7]);
    $elapsed = round(microtime(true) - $startTime, 2);
    bg_log("AI responded in {$elapsed}s.");

} catch (\Throwable $e) {
    $aiResponse = "Error generating response: " . $e->getMessage();
    bg_log("FATAL THROWABLE: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
}

// 7. Parse JSON envelope robustly
$messageForUser = trim((string)$aiResponse);
$operations = [];
$jsonStr = '';

// Try matching explicit markdown json block first
if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/is', $aiResponse, $matches)) {
    $jsonStr = $matches[1];
} else {
    // Fallback: search for first { and last }
    $startPos = strpos($aiResponse, '{');
    $endPos = strrpos($aiResponse, '}');
    if ($startPos !== false && $endPos !== false && $endPos > $startPos) {
        $jsonStr = substr($aiResponse, $startPos, $endPos - $startPos + 1);
    }
}

if ($jsonStr) {
    $json = json_decode($jsonStr, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        if (isset($json['message'])) $messageForUser = trim($json['message']);
        if (isset($json['operations']) && is_array($json['operations'])) $operations = $json['operations'];
    } else {
        bg_log("JSON Parse Error: " . json_last_error_msg());
        bg_log("Failed string: " . $jsonStr);
    }
} else {
    bg_log("No JSON structure found in response.");
}

// Cleanup text if JSON failed but markdown is still lingering
if (empty($operations)) {
    $messageForUser = preg_replace('/```json/i', '', $messageForUser);
    $messageForUser = trim(str_replace('```', '', $messageForUser));
}

// 8. Reconnection check before execution
try {
    $pdo->query("SELECT 1");
} catch (\Throwable $e) {
    bg_log("Reconnecting to DB...");
    $dsn = "mysql:host=" . (getenv('DB_HOST') ?: '127.0.0.1') . ";port=" . (getenv('DB_PORT') ?: '3306') . ";dbname=" . $spw->getDbName() . ";charset=utf8mb4";
    $pdo = new PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
}

// 9. Execute DB Operations via Module hook
$execLog = [];
if (!empty($operations)) {
    bg_log("Executing " . count($operations) . " operations via module class...");
    try {
        $execLog = $agent->executeOperations($operations, $sessionId, $userId);
    } catch (\Throwable $e) {
        bg_log("EXECUTION FATAL ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
    }
}

// 10. Persist Assistant Response
try {
    $logJson = !empty($execLog) ? json_encode($execLog) : null;
    $tsMs = (int)round(microtime(true) * 1000);

    $stmt = $pdo->prepare("INSERT INTO `agent_chat_messages` (session_id, role, content, operations_log, ts) VALUES (?, 'assistant', ?, ?, ?)");
    $stmt->execute([$sessionId, $messageForUser, $logJson, $tsMs]);
    bg_log("Completed successfully. Response written to DB.");
} catch (\Throwable $e) {
    bg_log("CRITICAL: Failed to write response: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine());
}