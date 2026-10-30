<?php
// public/splashu_api.php
// ─────────────────────────────────────────────────────────────────────────────
// SPLASHU — API
// Mirrors WrishEngine/Wrish API architecture exactly, adapted for Splash
// preconfigured templates and generator roles.
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
require_once dirname(__DIR__) . '/src/Service/GeneratorRepository.php';
require_once dirname(__DIR__) . '/src/Service/SplashEngine.php';

use App\Service\SplashEngine;
use App\Service\GeneratorRepository;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

global $spw;
if (!isset($pdo)) {
    $pdo = $spw->getPDO();
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? $data['action'] ?? '';
$engine = new SplashEngine();
$repo = new GeneratorRepository($pdo);

// Helper for calling generator directly with role config
function callRoleGenerator(int $userId, string $configId, array $conversationMessages, $spw, GeneratorRepository $repo): string {
    $rec = $repo->findActiveForUser($configId, $userId);
    if (!$rec) {
        throw new Exception("Generator config '{$configId}' not found or inactive.");
    }
    $sysParts = [];
    if ($rec->systemRole) $sysParts[] = $rec->systemRole;
    if (!empty($rec->instructions)) $sysParts[] = "═══ RULES & PROTOCOLS ═══\n" . implode("\n\n", $rec->instructions);

    $messages = [['role' => 'system', 'content' => implode("\n\n", $sysParts)]];
    foreach ($conversationMessages as $msg) {
        $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
    }
    $aiOptions = [];
    if (isset($rec->parameters['temperature'])) $aiOptions['temperature'] = (float)$rec->parameters['temperature']['default'];
    $aiOptions['max_tokens'] = isset($rec->parameters['max_tokens']) ? (int)$rec->parameters['max_tokens']['default'] : 65536;

    return $spw->getAIProvider()->sendMessage($rec->model, $messages, $aiOptions);
}

// Ensure support tables exist or use wrish_chat_sessions / wrish_conversation / wrish_plans / wrish_plan_steps / wrish_session_settings tables by session convention
// (We share session storage tables with Wrish or use dedicated splashu tables if needed. To keep it robust without altering base schema, we use wrish_* tables with session prefix or create dedicated splashu session tables if missing).
$pdo->exec("
    CREATE TABLE IF NOT EXISTS splashu_sessions (
        session_id VARCHAR(50) NOT NULL,
        user_id INT(11) NOT NULL,
        title VARCHAR(255) DEFAULT NULL,
        template_id INT(11) DEFAULT NULL,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (session_id),
        KEY idx_splashu_sessions_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS splashu_conversation (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        session_id VARCHAR(50) DEFAULT 'default',
        role VARCHAR(50) DEFAULT NULL,
        content MEDIUMTEXT DEFAULT NULL,
        display_content MEDIUMTEXT DEFAULT NULL,
        ts BIGINT(20) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_splashu_conv_user (user_id),
        KEY idx_splashu_conv_session (session_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS splashu_plans (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        session_id VARCHAR(50) NOT NULL,
        title VARCHAR(255) DEFAULT NULL,
        premise MEDIUMTEXT DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'active',
        PRIMARY KEY (id),
        KEY idx_splashu_plans_user (user_id),
        KEY idx_splashu_plans_session (session_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS splashu_plan_steps (
        id INT(11) NOT NULL AUTO_INCREMENT,
        plan_id INT(11) NOT NULL,
        user_id INT(11) NOT NULL,
        step_order INT(11) NOT NULL DEFAULT 0,
        title VARCHAR(255) NOT NULL,
        purpose TEXT DEFAULT NULL,
        generator_config_id VARCHAR(255) DEFAULT NULL,
        prompt_instructions MEDIUMTEXT DEFAULT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'pending',
        result_summary MEDIUMTEXT DEFAULT NULL,
        completed_at TIMESTAMP NULL DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_splashu_steps_plan (plan_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");
$pdo->exec("
    CREATE TABLE IF NOT EXISTS splashu_session_settings (
        session_id VARCHAR(50) NOT NULL,
        user_id INT(11) NOT NULL,
        auto_advance TINYINT(1) DEFAULT 0,
        source_text LONGTEXT DEFAULT NULL,
        offline_mode TINYINT(1) DEFAULT 0,
        overwrite_done TINYINT(1) DEFAULT 0,
        PRIMARY KEY (session_id),
        KEY idx_splashu_settings_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
");

function loadSplashuPlan(PDO $pdo, int $userId, string $sessionId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM splashu_plans WHERE user_id = ? AND session_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId, $sessionId]);
    $planRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$planRow) return null;

    $stmt = $pdo->prepare("SELECT * FROM splashu_plan_steps WHERE plan_id = ? ORDER BY step_order ASC");
    $stmt->execute([$planRow['id']]);
    $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return [
        'id' => $planRow['id'],
        'title' => $planRow['title'],
        'premise' => $planRow['premise'],
        'status' => $planRow['status'],
        'steps' => $steps,
    ];
}

function determineNextSplashuTurn(PDO $pdo, int $userId, string $sessionId): array {
    $plan = loadSplashuPlan($pdo, $userId, $sessionId);
    if (!$plan) return ['kind' => 'select_room'];

    $stmt = $pdo->prepare("SELECT * FROM splashu_session_settings WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$userId, $sessionId]);
    $settings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    $overwriteDone = !empty($settings['overwrite_done']);

    foreach ($plan['steps'] as $step) {
        if ($step['status'] === 'active' || $step['status'] === 'pending') {
            return ['kind' => 'step', 'step' => $step];
        }
    }

    if ($overwriteDone) {
        $stmt = $pdo->prepare("SELECT MAX(step_order) FROM splashu_plan_steps WHERE plan_id = ?");
        $stmt->execute([$plan['id']]);
        $maxOrder = (int)$stmt->fetchColumn();

        $pdo->prepare("INSERT INTO splashu_plan_steps (plan_id, user_id, step_order, title, purpose, status) VALUES (?, ?, ?, ?, ?, 'active')")
            ->execute([$plan['id'], $userId, $maxOrder + 1, 'Manual Continuation ' . ($maxOrder + 1), 'Manual continuation step']);
        
        $pdo->prepare("UPDATE splashu_plans SET status='active' WHERE id=?")->execute([$plan['id']]);
        return determineNextSplashuTurn($pdo, $userId, $sessionId);
    }

    return ['kind' => 'done'];
}

try {
    switch ($action) {
        
        
        
        
       case 'list_templates':
            $page = max(1, (int)($_GET['page'] ?? $data['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? $data['limit'] ?? 20));
            $result = $engine->listTemplatesPaged($userId, $page, $limit);
            echo json_encode(['success' => true] + $result);
            break;
        
        
        case 'load_state':
            $stmt = $pdo->prepare("SELECT session_id as id, title, updated_at FROM splashu_sessions WHERE user_id = ? ORDER BY updated_at DESC");
            $stmt->execute([$userId]);
            $chatSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $currentSessionId = $chatSessions[0]['id'] ?? null;

            $sessSettingsRow = [];
            if ($currentSessionId) {
                $stmt = $pdo->prepare("SELECT * FROM splashu_session_settings WHERE user_id = ? AND session_id = ?");
                $stmt->execute([$userId, $currentSessionId]);
                $sessSettingsRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            }
            $settings = [
                'autoAdvance' => (bool)($sessSettingsRow['auto_advance'] ?? 0),
                'sourceText' => $sessSettingsRow['source_text'] ?? '',
                'offlineMode' => (bool)($sessSettingsRow['offline_mode'] ?? 0),
                'overwriteDone' => (bool)($sessSettingsRow['overwrite_done'] ?? 0)
            ];

            $plan = $currentSessionId ? loadSplashuPlan($pdo, $userId, $currentSessionId) : null;

            $conversation = [];
            if ($currentSessionId) {
                $stmt = $pdo->prepare("SELECT * FROM splashu_conversation WHERE user_id = ? AND session_id = ? ORDER BY id ASC");
                $stmt->execute([$userId, $currentSessionId]);
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $conversation[] = [
                        'id' => (int)$row['id'],
                        'role' => $row['role'],
                        'content' => $row['content'],
                        'display_content' => $row['display_content'],
                        'ts' => (int)$row['ts']
                    ];
                }
            }

            echo json_encode([
                'success' => true,
                'state' => [
                    'settings' => $settings,
                    'chat_sessions' => $chatSessions,
                    'chat_session_id' => $currentSessionId,
                    'plan' => $plan,
                    'conversation' => $conversation
                ]
            ]);
            break;

        case 'list_chat_sessions':
            $page = max(1, (int)($_GET['page'] ?? $data['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? $data['limit'] ?? 20));
            $offset = ($page - 1) * $limit;

            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM splashu_sessions WHERE user_id = ?");
            $countStmt->execute([$userId]);
            $total = (int)$countStmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT session_id as id, title, updated_at FROM splashu_sessions WHERE user_id = ? ORDER BY updated_at DESC LIMIT ? OFFSET ?");
            $stmt->bindValue(1, $userId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->bindValue(3, $offset, PDO::PARAM_INT);
            $stmt->execute();
            $chatSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'chat_sessions' => $chatSessions,
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => max(1, (int)ceil($total / $limit)),
            ]);
            break;

        case 'save_settings':
            $s = $data['settings'] ?? [];
            $sessionId = $data['chat_session_id'] ?? '';
            if ($sessionId) {
                $stmt = $pdo->prepare("
                    INSERT INTO splashu_session_settings (session_id, user_id, auto_advance, source_text, offline_mode, overwrite_done)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    auto_advance=VALUES(auto_advance), source_text=VALUES(source_text), offline_mode=VALUES(offline_mode), overwrite_done=VALUES(overwrite_done)
                ");
                $stmt->execute([
                    $sessionId,
                    $userId,
                    !empty($s['autoAdvance']) ? 1 : 0,
                    $s['sourceText'] ?? '',
                    !empty($s['offlineMode']) ? 1 : 0,
                    !empty($s['overwriteDone']) ? 1 : 0
                ]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'rename_chat':
            $sessionId = $data['session_id'] ?? '';
            $title = $data['title'] ?? '';
            if ($sessionId && $title) {
                $pdo->prepare("UPDATE splashu_sessions SET title = ? WHERE session_id = ? AND user_id = ?")->execute([$title, $sessionId, $userId]);
            }
            echo json_encode(['success' => true]);
            break;

        case 'delete_chat':
            $sessionId = $data['session_id'] ?? '';
            $stmt = $pdo->prepare("SELECT id FROM splashu_plans WHERE user_id = ? AND session_id = ?");
            $stmt->execute([$userId, $sessionId]);
            $planIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            if ($planIds) {
                $in = str_repeat('?,', count($planIds) - 1) . '?';
                $pdo->prepare("DELETE FROM splashu_plan_steps WHERE plan_id IN ($in)")->execute($planIds);
            }
            $pdo->prepare("DELETE FROM splashu_plans WHERE user_id = ? AND session_id = ?")->execute([$userId, $sessionId]);
            $pdo->prepare("DELETE FROM splashu_sessions WHERE user_id = ? AND session_id = ?")->execute([$userId, $sessionId]);
            $pdo->prepare("DELETE FROM splashu_conversation WHERE user_id = ? AND session_id = ?")->execute([$userId, $sessionId]);
            $pdo->prepare("DELETE FROM splashu_session_settings WHERE user_id = ? AND session_id = ?")->execute([$userId, $sessionId]);
            echo json_encode(['success' => true]);
            break;

        case 'load_chat':
            $sessionId = $_GET['session_id'] ?? '';
            $stmt = $pdo->prepare("SELECT * FROM splashu_conversation WHERE user_id = ? AND session_id = ? ORDER BY id ASC");
            $stmt->execute([$userId, $sessionId]);
            $conversation = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $conversation[] = [
                    'id' => (int)$row['id'],
                    'role' => $row['role'],
                    'content' => $row['content'],
                    'display_content' => $row['display_content'],
                    'ts' => (int)$row['ts']
                ];
            }
            $plan = loadSplashuPlan($pdo, $userId, $sessionId);

            $stmt = $pdo->prepare("SELECT * FROM splashu_session_settings WHERE user_id = ? AND session_id = ?");
            $stmt->execute([$userId, $sessionId]);
            $sessSettingsRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $settings = [
                'autoAdvance' => (bool)($sessSettingsRow['auto_advance'] ?? 0),
                'sourceText' => $sessSettingsRow['source_text'] ?? '',
                'offlineMode' => (bool)($sessSettingsRow['offline_mode'] ?? 0),
                'overwriteDone' => (bool)($sessSettingsRow['overwrite_done'] ?? 0)
            ];

            echo json_encode(['success' => true, 'conversation' => $conversation, 'plan' => $plan, 'settings' => $settings]);
            break;

        case 'next_turn_info':
            $sessionId = $data['chat_session_id'] ?? $_GET['chat_session_id'] ?? '';
            $turn = determineNextSplashuTurn($pdo, $userId, $sessionId);
            echo json_encode(['success' => true, 'turn' => $turn]);
            break;

        case 'select_room':
            $sessionId = $data['chat_session_id'] ?? '';
            $templateId = (int)($data['template_id'] ?? 0);
            $settings = $data['settings'] ?? [];
            if (!$sessionId || $templateId <= 0) throw new Exception("Missing session or template.");

            $template = $engine->getTemplate($userId, $templateId);
            if (!$template) throw new Exception("Room template not found.");

            $stmt = $pdo->prepare("
                INSERT INTO splashu_sessions (session_id, user_id, title, template_id, updated_at)
                VALUES (?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE title = VALUES(title), template_id = VALUES(template_id), updated_at = NOW()
            ");
            $stmt->execute([$sessionId, $userId, 'Room: ' . $template['name'], $templateId]);

            $stmt = $pdo->prepare("INSERT INTO splashu_plans (user_id, session_id, title, premise, status) VALUES (?, ?, ?, ?, 'active')");
            $stmt->execute([$userId, $sessionId, $template['name'], $template['description']]);
            $planId = (int)$pdo->lastInsertId();

            $sStmt = $pdo->prepare("
                INSERT INTO splashu_plan_steps (plan_id, user_id, step_order, title, purpose, generator_config_id, prompt_instructions, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $roles = $template['roles'] ?? [];
            foreach ($roles as $i => $r) {
                $sStmt->execute([
                    $planId, $userId, $i + 1,
                    $r['role_name'],
                    $r['prompt_instructions'] ?? '',
                    $r['generator_config_id'],
                    $r['prompt_instructions'] ?? '',
                    $i === 0 ? 'active' : 'pending'
                ]);
            }

            $plan = loadSplashuPlan($pdo, $userId, $sessionId);
            $aiText = "Writers' Room **" . $template['name'] . "** initialized with " . count($roles) . " roles ready in chain.";

            $nowMs = (int)round(microtime(true) * 1000);
            $pdo->prepare("INSERT INTO splashu_conversation (user_id, session_id, role, content, display_content, ts) VALUES (?, ?, 'user', ?, ?, ?)")
                ->execute([$userId, $sessionId, 'Selected room template #' . $templateId, 'Selected Room: ' . $template['name'], $nowMs]);
            $pdo->prepare("INSERT INTO splashu_conversation (user_id, session_id, role, content, ts) VALUES (?, ?, 'assistant', ?, ?)")
                ->execute([$userId, $sessionId, $aiText, (int)round(microtime(true) * 1000)]);

            echo json_encode(['success' => true, 'turn_type' => 'room_selected', 'ai_text' => $aiText, 'plan' => $plan]);
            break;

        case 'run_step':
            $sessionId = $data['chat_session_id'] ?? '';
            $userInput = $data['message'] ?? null;
            $settings = $data['settings'] ?? [];
            if (!$sessionId) throw new Exception("Missing session_id.");

            $turn = determineNextSplashuTurn($pdo, $userId, $sessionId);
            if ($turn['kind'] === 'done') {
                echo json_encode(['success' => true, 'turn_type' => 'done', 'ai_text' => 'Room chain is already complete.', 'plan' => loadSplashuPlan($pdo, $userId, $sessionId)]);
                exit;
            }
            if ($turn['kind'] === 'select_room') {
                throw new Exception("Please select a room template first.");
            }

            $step = $turn['step'];
            
            // Gather previous outputs in the chain for context
            $stmt = $pdo->prepare("SELECT title, result_summary FROM splashu_plan_steps WHERE plan_id = ? AND step_order < ? ORDER BY step_order ASC");
            $stmt->execute([$step['plan_id'], $step['step_order']]);
            $priorSteps = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sourceText = $settings['sourceText'] ?? '';
            $promptParts = [];
            if ($sourceText !== '') {
                $promptParts[] = "═══ ORIGINAL SOURCE LORE / PLOT ═══\n" . $sourceText . "\n═════════════════════════════════════";
            }
            if (!empty($priorSteps)) {
                $promptParts[] = "═══ PRIOR WRITERS' ROOM OUTPUTS ═══";
                foreach ($priorSteps as $ps) {
                    $promptParts[] = "--- Role: " . $ps['title'] . " ---\n" . ($ps['result_summary'] ?? '');
                }
                $promptParts[] = "═════════════════════════════════════";
            }
            $promptParts[] = "YOUR ROLE INSTRUCTIONS:\n" . ($step['prompt_instructions'] ?? $step['purpose']);
            if ($userInput !== null && $userInput !== '') {
                $promptParts[] = "ADDITIONAL SHOWRUNNER INSTRUCTIONS:\n" . $userInput;
            }

            $fullPrompt = implode("\n\n", $promptParts);

            // Execute via configured generator config for this specific role
            $genConfigId = $step['generator_config_id'] ?? 'splash_writer_15_sketches_v1';
            $aiText = callRoleGenerator($userId, $genConfigId, [['role' => 'user', 'content' => $fullPrompt]], $spw, $repo);

            // Commit turn
            $nowMs = (int)round(microtime(true) * 1000);
            $pdo->prepare("INSERT INTO splashu_conversation (user_id, session_id, role, content, display_content, ts) VALUES (?, ?, 'user', ?, ?, ?)")
                ->execute([$userId, $sessionId, $fullPrompt, $userInput ?: ('Execute Role: ' . $step['title']), $nowMs]);
            $pdo->prepare("INSERT INTO splashu_conversation (user_id, session_id, role, content, ts) VALUES (?, ?, 'assistant', ?, ?)")
                ->execute([$userId, $sessionId, $aiText, (int)round(microtime(true) * 1000)]);

            $pdo->prepare("UPDATE splashu_plan_steps SET status='done', result_summary=?, completed_at=NOW() WHERE id=?")->execute([$aiText, $step['id']]);

            $nextStmt = $pdo->prepare("SELECT id FROM splashu_plan_steps WHERE plan_id=? AND step_order > ? ORDER BY step_order ASC LIMIT 1");
            $nextStmt->execute([$step['plan_id'], $step['step_order']]);
            $nextId = $nextStmt->fetchColumn();

            if ($nextId) {
                $pdo->prepare("UPDATE splashu_plan_steps SET status='active' WHERE id=?")->execute([$nextId]);
            } else {
                $pdo->prepare("UPDATE splashu_plans SET status='completed' WHERE id=?")->execute([$step['plan_id']]);
            }

            $plan = loadSplashuPlan($pdo, $userId, $sessionId);
            echo json_encode(['success' => true, 'turn_type' => 'step_completed', 'ai_text' => $aiText, 'plan' => $plan]);
            break;

        case 'export_step_prompt':
            $sessionId = $data['chat_session_id'] ?? '';
            $userInput = $data['message'] ?? null;
            $settings = $data['settings'] ?? [];
            if (!$sessionId) throw new Exception("Missing session_id.");

            $turn = determineNextSplashuTurn($pdo, $userId, $sessionId);
            if ($turn['kind'] === 'done' || $turn['kind'] === 'select_room') throw new Exception("No active step to export.");

            $step = $turn['step'];
            $stmt = $pdo->prepare("SELECT title, result_summary FROM splashu_plan_steps WHERE plan_id = ? AND step_order < ? ORDER BY step_order ASC");
            $stmt->execute([$step['plan_id'], $step['step_order']]);
            $priorSteps = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $sourceText = $settings['sourceText'] ?? '';
            $promptParts = [];
            if ($sourceText !== '') {
                $promptParts[] = "═══ ORIGINAL SOURCE LORE / PLOT ═══\n" . $sourceText . "\n═════════════════════════════════════";
            }
            if (!empty($priorSteps)) {
                $promptParts[] = "═══ PRIOR WRITERS' ROOM OUTPUTS ═══";
                foreach ($priorSteps as $ps) {
                    $promptParts[] = "--- Role: " . $ps['title'] . " ---\n" . ($ps['result_summary'] ?? '');
                }
                $promptParts[] = "═════════════════════════════════════";
            }
            $promptParts[] = "YOUR ROLE INSTRUCTIONS:\n" . ($step['prompt_instructions'] ?? $step['purpose']);
            if ($userInput !== null && $userInput !== '') {
                $promptParts[] = "ADDITIONAL SHOWRUNNER INSTRUCTIONS:\n" . $userInput;
            }
            $promptText = implode("\n\n", $promptParts);

            // Fetch generator config system role / instructions if any
            $genConfigId = $step['generator_config_id'] ?? 'splash_writer_15_sketches_v1';
            $genRec = $repo->findActiveForUser($genConfigId, $userId);

            $fullContext = "";
            if ($genRec) {
                $fullContext .= "=== AI INSTRUCTIONS (ROLE: {$step['title']}) ===\n\n";
                if ($genRec->systemRole) $fullContext .= $genRec->systemRole . "\n\n";
                if (!empty($genRec->instructions)) {
                    $fullContext .= "--- RULES & PROTOCOLS ---\n" . implode("\n\n", $genRec->instructions) . "\n\n";
                }
                $fullContext .= "=== END AI INSTRUCTIONS ===\n\n";
            }

            $fullContext .= "--- CONVERSATION LOG ---\n\n";
            $stmt = $pdo->prepare("SELECT role, display_content, content FROM splashu_conversation WHERE user_id = ? AND session_id = ? ORDER BY id ASC");
            $stmt->execute([$userId, $sessionId]);
            while ($msg = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $role = strtoupper($msg['role']);
                $text = $msg['display_content'] ?: $msg['content'];
                $fullContext .= "[{$role}]:\n{$text}\n\n";
            }

            $fullContext .= "\n--- NEXT ROLE PROMPT (run externally using generator: {$genConfigId}) ---\n\n" . $promptText;

            echo json_encode(['success' => true, 'prompt_text' => $promptText, 'full_context' => $fullContext]);
            break;

        case 'inject_step':
            $sessionId = $data['chat_session_id'] ?? '';
            $injectedAiText = $data['injected_ai_text'] ?? '';
            if (!$sessionId || !$injectedAiText) throw new Exception("Missing data.");

            $turn = determineNextSplashuTurn($pdo, $userId, $sessionId);
            if ($turn['kind'] === 'done' || $turn['kind'] === 'select_room') throw new Exception("No active step.");

            $step = $turn['step'];
            $nowMs = (int)round(microtime(true) * 1000);
            $pdo->prepare("INSERT INTO splashu_conversation (user_id, session_id, role, content, display_content, ts) VALUES (?, ?, 'user', ?, ?, ?)")
                ->execute([$userId, $sessionId, '(injected external response)', '(Injected External Response)', $nowMs]);
            $pdo->prepare("INSERT INTO splashu_conversation (user_id, session_id, role, content, ts) VALUES (?, ?, 'assistant', ?, ?)")
                ->execute([$userId, $sessionId, $injectedAiText, (int)round(microtime(true) * 1000)]);

            $pdo->prepare("UPDATE splashu_plan_steps SET status='done', result_summary=?, completed_at=NOW() WHERE id=?")->execute([$injectedAiText, $step['id']]);

            $nextStmt = $pdo->prepare("SELECT id FROM splashu_plan_steps WHERE plan_id=? AND step_order > ? ORDER BY step_order ASC LIMIT 1");
            $nextStmt->execute([$step['plan_id'], $step['step_order']]);
            $nextId = $nextStmt->fetchColumn();

            if ($nextId) {
                $pdo->prepare("UPDATE splashu_plan_steps SET status='active' WHERE id=?")->execute([$nextId]);
            } else {
                $pdo->prepare("UPDATE splashu_plans SET status='completed' WHERE id=?")->execute([$step['plan_id']]);
            }

            $plan = loadSplashuPlan($pdo, $userId, $sessionId);
            echo json_encode(['success' => true, 'turn_type' => 'step_completed', 'ai_text' => $injectedAiText, 'plan' => $plan]);
            break;

        case 'replace_last_response':
            $sessionId = $data['chat_session_id'] ?? '';
            $newText = $data['new_text'] ?? '';
            if (!$sessionId || $newText === '') throw new Exception("Missing data.");

            $stmt = $pdo->prepare("SELECT id FROM splashu_conversation WHERE user_id = ? AND session_id = ? AND role = 'assistant' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$userId, $sessionId]);
            $id = $stmt->fetchColumn();
            if (!$id) {
                echo json_encode(['error' => 'No assistant message found']);
                exit;
            }
            $pdo->prepare("UPDATE splashu_conversation SET content = ? WHERE id = ? AND user_id = ?")->execute([$newText, $id, $userId]);
            echo json_encode(['success' => true]);
            break;

        case 'export_full_result':
            $sessionId = $data['chat_session_id'] ?? $_GET['chat_session_id'] ?? '';
            $plan = loadSplashuPlan($pdo, $userId, $sessionId);
            $output = "SPLASHU SESSION SCRIPT EXPORT\nSession ID: " . $sessionId . "\n\n";
            if ($plan) {
                foreach ($plan['steps'] as $s) {
                    $output .= "========================================\n";
                    $output .= "ROLE " . $s['step_order'] . ": " . $s['title'] . "\n";
                    $output .= "========================================\n\n";
                    $output .= ($s['result_summary'] ?? '(No output)') . "\n\n";
                }
            }
            echo json_encode(['success' => true, 'export_text' => $output]);
            break;

        default:
            echo json_encode(['error' => "Unknown action '{$action}'"]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}