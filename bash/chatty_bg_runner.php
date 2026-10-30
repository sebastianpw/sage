<?php
/**
 * chatty_bg_runner.php
 * CLI background worker for long-running Chatty AI generation requests.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

require dirname(__DIR__) . '/public/bootstrap.php';

$sessionId = $argv[1] ?? '';
$userId = (int)($argv[2] ?? 0);

if (!$sessionId || !$userId) {
    die("Missing arguments\n");
}

// Ensure temp directory exists for logging
$logFile = PROJECT_ROOT . '/temp/chatty_bg.log';
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0777, true);
}

function bg_log($msg) {
    global $logFile, $sessionId;
    $time = date('Y-m-d H:i:s');
    file_put_contents($logFile, "[$time] [Session:$sessionId] $msg\n", FILE_APPEND);
}

bg_log("--- Starting background job for user $userId ---");

// 30 Minutes Execution Threshold for Heavy AI Operations
set_time_limit(1800);
ini_set('memory_limit', '1024M');

global $spw;
$pdo = $spw->getPDO();

try {
    bg_log("Fetching settings...");
    // 1. Fetch Session Specific Settings
    $stmt = $pdo->prepare("SELECT model, system_prompt FROM chatty_session_settings WHERE session_id = ? AND user_id = ?");
    $stmt->execute([$sessionId, $userId]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$settings) {
        $stmt = $pdo->prepare("SELECT model, system_prompt FROM chatty_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    $model = $settings['model'] ?? 'openai';
    $systemPrompt = $settings['system_prompt'] ?? '';

    bg_log("Loading conversation history...");
    // 2. Load Conversation History
    $stmt = $pdo->prepare("SELECT role, content FROM chatty_conversation WHERE user_id = ? AND session_id = ? ORDER BY id ASC");
    $stmt->execute([$userId, $sessionId]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $apiMessages = [];
    if (!empty($systemPrompt)) {
        $apiMessages[] = ['role' => 'system', 'content' => $systemPrompt];
    }

    $firstUserIdx = -1;
    foreach ($history as $msg) {
        $apiMessages[] = ['role' => $msg['role'], 'content' => $msg['content']];
        if ($firstUserIdx === -1 && $msg['role'] === 'user') {
            $firstUserIdx = count($apiMessages) - 1;
        }
    }

    // 3. Cache Control Formatting (For Claude/Anthropic Context Scaling)
    $isClaudeOrNova = (strpos(strtolower($model), 'claude') !== false || strpos(strtolower($model), 'nova') !== false);

    if ($isClaudeOrNova) {
        if (isset($apiMessages[0]) && $apiMessages[0]['role'] === 'system') {
            $apiMessages[0]['content'] = [
                ['type' => 'text', 'text' => $apiMessages[0]['content'], 'cache_control' => ['type' => 'ephemeral']]
            ];
        }
        if ($firstUserIdx !== -1 && $firstUserIdx !== 0) {
            $apiMessages[$firstUserIdx]['content'] = [
                ['type' => 'text', 'text' => $apiMessages[$firstUserIdx]['content'], 'cache_control' => ['type' => 'ephemeral']]
        ];
        }
        $historyCount = count($apiMessages);
        $secondToLastIdx = $historyCount - 2;
        if ($secondToLastIdx > $firstUserIdx && $secondToLastIdx > 0) {
            $apiMessages[$secondToLastIdx]['content'] = [
                ['type' => 'text', 'text' => $apiMessages[$secondToLastIdx]['content'], 'cache_control' => ['type' => 'ephemeral']]
            ];
        }
    }

    // 4. Request the AI Provider
    bg_log("Sending request to AI Provider (Model: $model)...");
    $aiProvider = $spw->getAIProvider();
    
    $startTime = microtime(true);
    $aiText = $aiProvider->sendMessage($model, $apiMessages, ['jsonMode' => false]);
    $elapsed = round(microtime(true) - $startTime, 2);
    
    bg_log("AI Provider responded in {$elapsed}s. Response length: " . strlen($aiText));

} catch (\Exception $e) {
    $aiText = "Error generating response: " . $e->getMessage();
    bg_log("AI Provider Exception: " . $e->getMessage());
}

// 5. Fallback Protection
if (trim((string)$aiText) === '') {
    $aiText = "The network connection timed out or the AI returned empty text. Your document was saved to the chat. Please type 'continue' to fetch the response.";
    bg_log("Warning: AI text was empty.");
}

// 6. DB Connection Safety Check
bg_log("Checking DB connection state before save...");
try {
    $pdo->query("SELECT 1");
} catch (\Exception $e) {
    bg_log("DB connection lost. Attempting to reconnect: " . $e->getMessage());
    try {
        $dsn = "mysql:host=" . (getenv('DB_HOST') ?: '127.0.0.1') . ";port=" . (getenv('DB_PORT') ?: '3306') . ";dbname=" . $spw->getDbName() . ";charset=utf8mb4";
        $pdo = new \PDO($dsn, getenv('DB_USER'), getenv('DB_PASS'), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION
        ]);
        bg_log("Reconnected to database successfully.");
    } catch (\Exception $ex) {
        bg_log("Reconnect failed! " . $ex->getMessage());
    }
}

// 7. Persist Result & Free Browser Poller
try {
    bg_log("Saving response to database...");
    $tsMs = (int)round(microtime(true) * 1000);
    $stmt = $pdo->prepare("INSERT INTO chatty_conversation (user_id, session_id, role, content, ts) VALUES (?, ?, 'assistant', ?, ?)");
    $stmt->execute([$userId, $sessionId, $aiText, $tsMs]);
    bg_log("Successfully inserted response into DB. Job done.");
} catch (\Exception $e) {
    bg_log("FAILED to insert into DB: " . $e->getMessage());
    // Emergency save to disk so data is never lost
    $emergencyFile = PROJECT_ROOT . "/temp/emergency_chat_{$sessionId}.txt";
    file_put_contents($emergencyFile, $aiText);
    bg_log("Emergency saved response to: $emergencyFile");
}