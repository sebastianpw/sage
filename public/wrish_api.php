<?php
// public/wrish_api.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
require_once dirname(__DIR__) . '/src/Service/GeneratorRepository.php';
require_once dirname(__DIR__) . '/src/Service/WrishEngine.php';

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

// ── LOAD STATE ─────────────────────────────────────────────────────────────
if ($action === 'load_state') {
    // 1. Settings (Global default)
    $stmt = $pdo->prepare("SELECT * FROM wrish_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $globalSettings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    // 2. Pools (loaded but not used in UI anymore, kept for API compat if needed)
    $stmt = $pdo->prepare("SELECT * FROM wrish_pools WHERE user_id = ? ORDER BY id ASC");
    $stmt->execute([$userId]);
    $pools = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 3. Chat Sessions
    $stmt = $pdo->prepare("SELECT session_id as id, title, updated_at FROM wrish_chat_sessions WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$userId]);
    $chatSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $currentSessionId = $chatSessions[0]['id'] ?? null;
    
    // 1b. Settings (Session specific)
    $sessSettingsRow = [];
    if ($currentSessionId) {
        $stmt = $pdo->prepare("SELECT * FROM wrish_session_settings WHERE user_id = ? AND session_id = ?");
        $stmt->execute([$userId, $currentSessionId]);
        $sessSettingsRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    
    $settings = [
        'depth' => $sessSettingsRow['depth'] ?? $globalSettings['depth'] ?? 'standard',
        'frictionIntensity' => $sessSettingsRow['friction_intensity'] ?? $globalSettings['friction_intensity'] ?? 'medium',
        'autoAdvance' => (bool)($sessSettingsRow['auto_advance'] ?? $globalSettings['auto_advance'] ?? 0),
        'sessionTopic' => $sessSettingsRow['session_topic'] ?? $globalSettings['session_topic'] ?? '',
        'contextExtra' => $sessSettingsRow['context_extra'] ?? $globalSettings['context_extra'] ?? '',
        'offlineMode' => (bool)($sessSettingsRow['offline_mode'] ?? $globalSettings['offline_mode'] ?? 0),
        'overwriteDone' => (bool)($sessSettingsRow['overwrite_done'] ?? $globalSettings['overwrite_done'] ?? 0)
    ];
    
    // 4. Plan
    $plan = null;
    if ($currentSessionId) {
        $stmt = $pdo->prepare("SELECT * FROM wrish_plans WHERE user_id = ? AND session_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId, $currentSessionId]);
        $planRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($planRow) {
            $stmt = $pdo->prepare("SELECT * FROM wrish_plan_steps WHERE plan_id = ? ORDER BY step_order ASC");
            $stmt->execute([$planRow['id']]);
            $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $plan = [
                'id' => $planRow['id'],
                'title' => $planRow['title'],
                'premise' => $planRow['premise'],
                'status' => $planRow['status'],
                'steps' => $steps
            ];
        }
    }
    
    // 5. Conversation
    $conversation = [];
    if ($currentSessionId) {
        $stmt = $pdo->prepare("SELECT * FROM wrish_conversation WHERE user_id = ? AND session_id = ? ORDER BY id ASC");
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

    // 6. Active run
    $activeRun = null;
    if ($currentSessionId) {
        $engine = new \App\Service\WrishEngine();
        $activeRun = $engine->getActiveRun($userId, $currentSessionId);
    }
    
    
    
   echo json_encode([
        'success' => true,
        'state' => [
            'settings' => $settings,
            'pools' => $pools,
            'chat_sessions' => $chatSessions,
            'chat_session_id' => $currentSessionId,
            'plan' => $plan,
            'conversation' => $conversation,
            'active_run' => $activeRun
        ]
    ]);
    exit;
}

// ── LIST CHAT SESSIONS (paginated) ──────────────────────────────────────────
if ($action === 'list_chat_sessions') {
    $page = max(1, (int)($_GET['page'] ?? $data['page'] ?? 1));
    $limit = max(1, (int)($_GET['limit'] ?? $data['limit'] ?? 20));
    $offset = ($page - 1) * $limit;

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM wrish_chat_sessions WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT session_id as id, title, updated_at FROM wrish_chat_sessions WHERE user_id = ? ORDER BY updated_at DESC LIMIT ? OFFSET ?");
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
    exit;
}

// ── SAVE SETTINGS ────────────────────────────────────────────────────────
if ($action === 'save_settings') {
    $s = $data['settings'] ?? [];
    $sessionId = $data['chat_session_id'] ?? '';
    
    if ($sessionId) {
        $stmt = $pdo->prepare("
            INSERT INTO wrish_session_settings (session_id, user_id, depth, session_topic, offline_mode, auto_advance, friction_intensity, context_extra, overwrite_done)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            depth=VALUES(depth), session_topic=VALUES(session_topic), offline_mode=VALUES(offline_mode),
            auto_advance=VALUES(auto_advance), friction_intensity=VALUES(friction_intensity), context_extra=VALUES(context_extra), overwrite_done=VALUES(overwrite_done)
        ");
        $stmt->execute([
            $sessionId,
            $userId,
            $s['depth'] ?? 'standard',
            $s['sessionTopic'] ?? '',
            !empty($s['offlineMode']) ? 1 : 0,
            !empty($s['autoAdvance']) ? 1 : 0,
            $s['frictionIntensity'] ?? 'medium',
            $s['contextExtra'] ?? '',
            !empty($s['overwriteDone']) ? 1 : 0
        ]);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO wrish_settings (user_id, depth, session_topic, offline_mode, auto_advance, friction_intensity, context_extra, overwrite_done)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        depth=VALUES(depth), session_topic=VALUES(session_topic), offline_mode=VALUES(offline_mode),
        auto_advance=VALUES(auto_advance), friction_intensity=VALUES(friction_intensity), context_extra=VALUES(context_extra), overwrite_done=VALUES(overwrite_done)
    ");
    $stmt->execute([
        $userId,
        $s['depth'] ?? 'standard',
        $s['sessionTopic'] ?? '',
        !empty($s['offlineMode']) ? 1 : 0,
        !empty($s['autoAdvance']) ? 1 : 0,
        $s['frictionIntensity'] ?? 'medium',
        $s['contextExtra'] ?? '',
        !empty($s['overwriteDone']) ? 1 : 0
    ]);
    echo json_encode(['success' => true]);
    exit;
}
    
    
    
    
   // ── SAVE POOL ────────────────────────────────────────────────────────────
if ($action === 'save_pool') {
    $entry = $data['entry'] ?? [];
    if (empty($entry['label'])) { echo json_encode(['error'=>'Missing label']); exit; }
    
    if (!empty($entry['id'])) {
        $stmt = $pdo->prepare("UPDATE wrish_pools SET pool_type=?, label=?, body=?, weight=?, source=? WHERE id=? AND user_id=?");
        $stmt->execute([
            $entry['pool_type'] ?? 'vocabulary',
            $entry['label'],
            $entry['body'] ?? '',
            $entry['weight'] ?? 3,
            $entry['source'] ?? 'human',
            $entry['id'],
            $userId
        ]);
        $entry['id'] = (int)$entry['id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO wrish_pools (user_id, pool_type, label, body, weight, source) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $userId,
            $entry['pool_type'] ?? 'vocabulary',
            $entry['label'],
            $entry['body'] ?? '',
            $entry['weight'] ?? 3,
            $entry['source'] ?? 'human'
        ]);
        $entry['id'] = (int)$pdo->lastInsertId();
    }
    echo json_encode(['success' => true, 'entry' => $entry]);
    exit;
}

// ── DELETE POOL ──────────────────────────────────────────────────────────
if ($action === 'delete_pool') {
    $id = $data['id'] ?? null;
    if ($id) {
        $pdo->prepare("DELETE FROM wrish_pools WHERE id=? AND user_id=?")->execute([$id, $userId]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// ── RENAME CHAT ──────────────────────────────────────────────────────────
if ($action === 'rename_chat') {
    $sessionId = $data['session_id'] ?? '';
    $title = $data['title'] ?? '';
    if ($sessionId && $title) {
        $pdo->prepare("UPDATE wrish_chat_sessions SET title = ? WHERE session_id = ? AND user_id = ?")
            ->execute([$title, $sessionId, $userId]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// ── EXPORT FULL RESULT ───────────────────────────────────────────────────
if ($action === 'export_full_result') {
    $sessionId = $data['chat_session_id'] ?? $_GET['chat_session_id'] ?? '';
    if (!$sessionId) {
        echo json_encode(['error' => 'Missing chat_session_id']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM wrish_plans WHERE user_id = ? AND session_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId, $sessionId]);
    $plan = $stmt->fetch(PDO::FETCH_ASSOC);

    $output = "WRISH SESSION EXPORT\n";
    $output .= "Session ID: " . $sessionId . "\n\n";

    if ($plan) {
        $output .= "Plan Title: " . $plan['title'] . "\n";
        if (!empty($plan['premise'])) {
            $output .= "Premise: " . $plan['premise'] . "\n";
        }
        $output .= "\n--- STEPS ---\n\n";

        $stmt = $pdo->prepare("SELECT * FROM wrish_plan_steps WHERE plan_id = ? ORDER BY step_order ASC");
        $stmt->execute([$plan['id']]);
        $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($steps as $step) {
            $output .= "========================================\n";
            $output .= "STEP " . $step['step_order'] . ": " . $step['title'] . "\n";
            if (!empty($step['purpose'])) {
                $output .= "PURPOSE: " . $step['purpose'] . "\n";
            }
            $output .= "========================================\n\n";
            $output .= ($step['result_summary'] ?? '(No result yet)') . "\n\n";
        }
    } else {
        $output .= "No battle plan found for this session.\n\n";
    }

    $output .= "\n--- CONVERSATION LOG ---\n\n";
    $stmt = $pdo->prepare("SELECT role, display_content, content FROM wrish_conversation WHERE user_id = ? AND session_id = ? ORDER BY id ASC");
    $stmt->execute([$userId, $sessionId]);
    while ($msg = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $role = strtoupper($msg['role']);
        $text = $msg['display_content'] ?: $msg['content'];
        $output .= "[{$role}]:\n{$text}\n\n";
    }

    echo json_encode(['success' => true, 'export_text' => $output]);
    exit;
}
    
    
    
   
// ── SAVE PLAN ────────────────────────────────────────────────────────────
if ($action === 'save_plan') {
    $chatSessionId = $data['chat_session_id'] ?? '';
    $plan = $data['plan'] ?? [];
    if (!$chatSessionId || !$plan) {
        echo json_encode(['error' => 'Missing data']); exit;
    }
    
    $planId = $plan['id'] ?? null;
    
    $pdo->beginTransaction();
    try {
        if ($planId) {
            $stmt = $pdo->prepare("UPDATE wrish_plans SET title=?, premise=?, status=? WHERE id=? AND user_id=?");
            $stmt->execute([$plan['title'] ?? '', $plan['premise'] ?? '', $plan['status'] ?? 'active', $planId, $userId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO wrish_plans (user_id, session_id, title, premise, status) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $chatSessionId, $plan['title'] ?? '', $plan['premise'] ?? '', $plan['status'] ?? 'active']);
            $planId = $pdo->lastInsertId();
        }
        
        $pdo->prepare("DELETE FROM wrish_plan_steps WHERE plan_id = ?")->execute([$planId]);
        
        if (!empty($plan['steps'])) {
            $sStmt = $pdo->prepare("INSERT INTO wrish_plan_steps (plan_id, user_id, step_order, title, purpose, status, result_summary) VALUES (?, ?, ?, ?, ?, ?, ?)");
            foreach ($plan['steps'] as $s) {
                $sStmt->execute([
                    $planId,
                    $userId,
                    $s['step_order'] ?? 0,
                    $s['title'] ?? '',
                    $s['purpose'] ?? '',
                    $s['status'] ?? 'pending',
                    $s['result_summary'] ?? ''
                ]);
            }
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'plan_id' => $planId]);
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── SAVE CONVERSATION ────────────────────────────────────────────────────
if ($action === 'save_conversation') {
    $chatSessionId = $data['chat_session_id'] ?? 'default';
    $chatSessionTitle = $data['chat_session_title'] ?? 'New Session';
    $conversation = $data['conversation'] ?? [];
    
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO wrish_chat_sessions (session_id, user_id, title, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE title = VALUES(title), updated_at = NOW()
        ");
        $stmt->execute([$chatSessionId, $userId, $chatSessionTitle]);
        
        $pdo->prepare("DELETE FROM wrish_conversation WHERE user_id = ? AND session_id = ?")->execute([$userId, $chatSessionId]);
        
        if (!empty($conversation)) {
            $convStmt = $pdo->prepare("
                INSERT INTO wrish_conversation (user_id, session_id, role, content, display_content, protocol, ts)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($conversation as $msg) {
                $convStmt->execute([
                    $userId,
                    $chatSessionId,
                    $msg['role'] ?? '',
                    $msg['content'] ?? '',
                    $msg['display_content'] ?? null,
                    $msg['protocol'] ?? null,
                    $msg['ts'] ?? time() * 1000
                ]);
            }
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch(Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── LOAD CHAT ────────────────────────────────────────────────────────────
if ($action === 'load_chat') {
    $sessionId = $_GET['session_id'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM wrish_conversation WHERE user_id = ? AND session_id = ? ORDER BY id ASC");
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
    
    $plan = null;
    $stmt = $pdo->prepare("SELECT * FROM wrish_plans WHERE user_id = ? AND session_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId, $sessionId]);
    $planRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($planRow) {
        $stmt = $pdo->prepare("SELECT * FROM wrish_plan_steps WHERE plan_id = ? ORDER BY step_order ASC");
        $stmt->execute([$planRow['id']]);
        $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $plan = [
            'id' => $planRow['id'],
            'title' => $planRow['title'],
            'premise' => $planRow['premise'],
            'status' => $planRow['status'],
            'steps' => $steps
        ];
    }

    $stmt = $pdo->prepare("SELECT * FROM wrish_session_settings WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$userId, $sessionId]);
    $sessSettingsRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    $stmt = $pdo->prepare("SELECT * FROM wrish_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $globalSettings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    $settings = [
        'depth' => $sessSettingsRow['depth'] ?? $globalSettings['depth'] ?? 'standard',
        'frictionIntensity' => $sessSettingsRow['friction_intensity'] ?? $globalSettings['friction_intensity'] ?? 'medium',
        'autoAdvance' => (bool)($sessSettingsRow['auto_advance'] ?? $globalSettings['auto_advance'] ?? 0),
        'sessionTopic' => $sessSettingsRow['session_topic'] ?? $globalSettings['session_topic'] ?? '',
        'contextExtra' => $sessSettingsRow['context_extra'] ?? $globalSettings['context_extra'] ?? '',
        'offlineMode' => (bool)($sessSettingsRow['offline_mode'] ?? $globalSettings['offline_mode'] ?? 0),
        'overwriteDone' => (bool)($sessSettingsRow['overwrite_done'] ?? $globalSettings['overwrite_done'] ?? 0)
    ];

    $engine = new \App\Service\WrishEngine();
    $activeRun = $engine->getActiveRun($userId, $sessionId);

    echo json_encode(['success' => true, 'conversation' => $conversation, 'plan' => $plan, 'active_run' => $activeRun, 'settings' => $settings]);
    exit;
}
    
    
    
   
// ── DELETE CHAT ──────────────────────────────────────────────────────────
if ($action === 'delete_chat') {
    $sessionId = $data['session_id'] ?? '';
    
    // Cleanup plan steps first
    $stmt = $pdo->prepare("SELECT id FROM wrish_plans WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$userId, $sessionId]);
    $planIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if ($planIds) {
        $in = str_repeat('?,', count($planIds) - 1) . '?';
        $pdo->prepare("DELETE FROM wrish_plan_steps WHERE plan_id IN ($in)")->execute($planIds);
    }
    
    $pdo->prepare("DELETE FROM wrish_plans WHERE user_id = ? AND session_id = ?")->execute([$userId, $sessionId]);
    $pdo->prepare("DELETE FROM wrish_chat_sessions WHERE user_id = ? AND session_id = ?")->execute([$userId, $sessionId]);
    $pdo->prepare("DELETE FROM wrish_conversation WHERE user_id = ? AND session_id = ?")->execute([$userId, $sessionId]);
    $pdo->prepare("DELETE FROM wrish_session_settings WHERE user_id = ? AND session_id = ?")->execute([$userId, $sessionId]);
    
    echo json_encode(['success' => true]);
    exit;
}
// ── DETERMINE NEXT TURN (NEW, v2) ────────────────────────────────────────
// Tells the UI what the next turn's kind is (plan|step|done) without
// making any AI call — used so the UI can render the right controls
// (e.g. which step is up next) before the human decides whether to run
// it live (API Mode) or export it (Export Mode).
if ($action === 'next_turn_info') {
    try {
        $sessionId = $data['chat_session_id'] ?? $_GET['chat_session_id'] ?? '';
        if (!$sessionId) {
            echo json_encode(['error' => 'Missing chat_session_id']);
            exit;
        }
        $engine = new \App\Service\WrishEngine();
        $turn = $engine->determineNextTurn($userId, $sessionId);
        echo json_encode(['success' => true, 'turn' => $turn]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── RUN STEP (v2 — API MODE) ─────────────────────────────────────────────
// Does exactly one turn — whichever kind determineNextTurn() says is next
// — via a live API call. Zero classification/parsing for step turns; the
// one-time step-title extraction only ever runs internally, immediately
// after turn 1 (battle plan creation). $message is the existing message
// input box's text, included in the prompt exactly where it already sits
// today (end of context, as the QUERY).
if ($action === 'run_step') {
    try {
        $sessionId = $data['chat_session_id'] ?? '';
        if (!$sessionId) {
            echo json_encode(['error' => 'Missing chat_session_id']);
            exit;
        }

        $engine = new \App\Service\WrishEngine();
        $userInput = $data['message'] ?? null;
        $settings = $data['settings'] ?? [];
        $runId = isset($data['run_id']) ? (int)$data['run_id'] : null;

        $result = $engine->runStep($userId, $sessionId, $userInput, $settings, $runId);

        echo json_encode(['success' => true] + $result);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── EXPORT STEP PROMPT (v2 — EXPORT MODE, step A) ────────────────────────
// Builds the exact prompt text for whichever turn is next. Zero API calls
// — pure string assembly. $message is the existing message input box's
// text, included exactly where it already sits in the flow.
if ($action === 'export_step_prompt') {
    try {
        $sessionId = $data['chat_session_id'] ?? '';
        if (!$sessionId) {
            echo json_encode(['error' => 'Missing chat_session_id']);
            exit;
        }
        $engine = new \App\Service\WrishEngine();
        $settings = $data['settings'] ?? [];
        $userInput = $data['message'] ?? null;

        $promptText = $engine->exportStepPrompt($userId, $sessionId, $settings, $userInput);

        // Restore prior behavior: export mode's download must include the
        // full existing package (system/AI instructions + plan + steps +
        // conversation log), not just the bare next-step prompt, so the
        // whole thread/context travels with it.
        $fullContext = "";

        // AI INSTRUCTIONS: the system_role + instructions from the active
        // wrish_showrunner_v1 generator config. These are normally injected
        // live as a system message in callGenerator() and never persisted
        // anywhere else — so without this block they're absent from every
        // export, not just recently.
        $stmt = $pdo->prepare("
            SELECT system_role, instructions FROM generator_config
            WHERE config_id = ? AND active = 1 AND (user_id = ? OR user_id IS NULL)
            ORDER BY user_id IS NULL ASC LIMIT 1
        ");
        $stmt->execute([\App\Service\WrishEngine::CREATIVE_CONFIG_ID, $userId]);
        $genCfg = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($genCfg) {
            $fullContext .= "=== AI INSTRUCTIONS (SYSTEM) ===\n\n";
            if (!empty($genCfg['system_role'])) {
                $fullContext .= $genCfg['system_role'] . "\n\n";
            }
            $instr = json_decode($genCfg['instructions'], true);
            if (is_array($instr) && !empty($instr)) {
                $fullContext .= "--- RULES & PROTOCOLS ---\n" . implode("\n\n", $instr) . "\n\n";
            }
            $fullContext .= "=== END AI INSTRUCTIONS ===\n\n";
        }

        $stmt = $pdo->prepare("SELECT * FROM wrish_plans WHERE user_id = ? AND session_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId, $sessionId]);
        $plan = $stmt->fetch(PDO::FETCH_ASSOC);

        $fullContext .= "WRISH SESSION EXPORT\n";
        $fullContext .= "Session ID: " . $sessionId . "\n\n";

        if ($plan) {
            $fullContext .= "Plan Title: " . $plan['title'] . "\n";
            if (!empty($plan['premise'])) {
                $fullContext .= "Premise: " . $plan['premise'] . "\n";
            }
            $fullContext .= "\n--- STEPS ---\n\n";

            $stmt = $pdo->prepare("SELECT * FROM wrish_plan_steps WHERE plan_id = ? ORDER BY step_order ASC");
            $stmt->execute([$plan['id']]);
            $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($steps as $step) {
                $fullContext .= "========================================\n";
                $fullContext .= "STEP " . $step['step_order'] . ": " . $step['title'] . "\n";
                if (!empty($step['purpose'])) {
                    $fullContext .= "PURPOSE: " . $step['purpose'] . "\n";
                }
                $fullContext .= "========================================\n\n";
                $fullContext .= ($step['result_summary'] ?? '(No result yet)') . "\n\n";
            }
        } else {
            $fullContext .= "No battle plan found for this session.\n\n";
        }

        $fullContext .= "\n--- CONVERSATION LOG ---\n\n";
        $stmt = $pdo->prepare("SELECT role, display_content, content FROM wrish_conversation WHERE user_id = ? AND session_id = ? ORDER BY id ASC");
        $stmt->execute([$userId, $sessionId]);
        while ($msg = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $role = strtoupper($msg['role']);
            $text = $msg['display_content'] ?: $msg['content'];
            $fullContext .= "[{$role}]:\n{$text}\n\n";
        }

        $fullContext .= "\n--- NEXT STEP PROMPT (this is the one to run externally) ---\n\n";
        $fullContext .= $promptText;

        echo json_encode(['success' => true, 'prompt_text' => $promptText, 'full_context' => $fullContext]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}




// ── INJECT STEP (v2 — EXPORT MODE, step B) ───────────────────────────────
// Persists a pasted-in creative response for whichever turn is currently
// next, and commits it exactly like a live API turn would (including the
// one-time step-title extraction if this was turn 1). Zero API calls
// beyond that single internal extraction call when applicable.
if ($action === 'inject_step') {
    try {
        $sessionId = $data['chat_session_id'] ?? '';
        $injectedAiText = $data['injected_ai_text'] ?? '';
        if (!$sessionId || !$injectedAiText) {
            echo json_encode(['error' => 'Missing chat_session_id or injected_ai_text']);
            exit;
        }
        $engine = new \App\Service\WrishEngine();
        $settings = $data['settings'] ?? [];
        $runId = isset($data['run_id']) ? (int)$data['run_id'] : null;

        $result = $engine->injectStep($userId, $sessionId, $settings, $injectedAiText, $runId);
        echo json_encode(['success' => true] + $result);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── REPLACE LAST ASSISTANT MESSAGE (v2 — EXPORT MODE ONLY) ───────────────
// Corrects an accidental bad paste. Only ever touches the most recent
// assistant message — by design, never an arbitrary historical one — since
// that's the only message that hasn't yet caused any downstream plan/step
// state to unwind. The human can keep editing/replacing freely until they
// click the next-step action, at which point that content is committed.
if ($action === 'replace_last_response') {
    try {
        $sessionId = $data['chat_session_id'] ?? '';
        $newText = $data['new_text'] ?? '';
        if (!$sessionId || $newText === '') {
            echo json_encode(['error' => 'Missing chat_session_id or new_text']);
            exit;
        }
        $engine = new \App\Service\WrishEngine();
        $ok = $engine->replaceLastAssistantMessage($userId, $sessionId, $newText);
        echo json_encode($ok ? ['success' => true] : ['error' => 'No assistant message found to replace']);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ── START / GET / PAUSE / RESUME / CANCEL RUN ────────────────────────────
if ($action === 'start_run') {
    try {
        $sessionId = $data['chat_session_id'] ?? '';
        if (!$sessionId) {
            echo json_encode(['error' => 'Missing chat_session_id']);
            exit;
        }
        $engine = new \App\Service\WrishEngine();
        $autoAdvance = !empty($data['auto_advance']);
        $maxSteps = isset($data['max_steps']) ? (int)$data['max_steps'] : null;
        $run = $engine->startRun($userId, $sessionId, $autoAdvance, $maxSteps);
        echo json_encode(['success' => true, 'run' => $run]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_run') {
    $runId = (int)($_GET['run_id'] ?? $data['run_id'] ?? 0);
    $engine = new \App\Service\WrishEngine();
    $run = $engine->getRun($userId, $runId);
    echo json_encode($run ? ['success' => true, 'run' => $run] : ['error' => 'Not found']);
    exit;
}

if ($action === 'pause_run') {
    $runId = (int)($data['run_id'] ?? 0);
    $engine = new \App\Service\WrishEngine();
    $engine->pauseRun($userId, $runId);
    echo json_encode(['success' => true, 'run' => $engine->getRun($userId, $runId)]);
    exit;
}

if ($action === 'resume_run') {
    $runId = (int)($data['run_id'] ?? 0);
    $engine = new \App\Service\WrishEngine();
    $engine->resumeRun($userId, $runId);
    echo json_encode(['success' => true, 'run' => $engine->getRun($userId, $runId)]);
    exit;
}

if ($action === 'cancel_run') {
    $runId = (int)($data['run_id'] ?? 0);
    $engine = new \App\Service\WrishEngine();
    $engine->cancelRun($userId, $runId);
    echo json_encode(['success' => true, 'run' => $engine->getRun($userId, $runId)]);
    exit;
}







    
    
    