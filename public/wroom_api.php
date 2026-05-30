<?php
// public/wroom_api.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
require_once dirname(__DIR__) . '/src/Service/GeneratorRepository.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

global $spw; // Explicitly map the global SPW instance

if (!isset($pdo)) {
    $pdo = $spw->getPDO();
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? $data['action'] ?? '';

if ($action === 'load_state') {
    // 1. Settings & Context
    $stmt = $pdo->prepare("SELECT * FROM wroom_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $settingsRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $settings = [
        'depth' => $settingsRow['depth'] ?? 'standard',
        'autoFailure' => $settingsRow['auto_failure'] ?? 'no',
        'sessionTopic' => $settingsRow['session_topic'] ?? '',
        'offlineMode' => !empty($settingsRow['offline_mode']),
        'kgSelectedNodes' => json_decode($settingsRow['kg_selected_nodes'] ?? '[]', true),
        'kgWithContent' => !empty($settingsRow['kg_with_content']),
        'kgWithEdges' => isset($settingsRow['kg_with_edges']) ? (bool)$settingsRow['kg_with_edges'] : true,
        'draftDelta' => json_decode($settingsRow['draft_delta'] ?? 'null', true) ?: ['decisions'=>[], 'deferred'=>[], 'threads'=>[], 'updates'=>'']
    ];
    $context = [
        'phase' => $settingsRow['context_phase'] ?? 'S3',
        'status' => $settingsRow['context_status'] ?? '',
        'focus' => $settingsRow['context_focus'] ?? '',
        'registryVer' => $settingsRow['context_registry_ver'] ?? 'v0.1',
        'extra' => $settingsRow['context_extra'] ?? ''
    ];

    // 2. Threads
    $stmt = $pdo->prepare("SELECT * FROM wroom_threads WHERE user_id = ? ORDER BY id ASC");
    $stmt->execute([$userId]);
    $threads = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $threads[] = [
            'id' => $row['thread_id'],
            'name' => $row['name'],
            'type' => $row['type'],
            'seasons' => $row['seasons'],
            'status' => $row['status'],
            'axis' => $row['axis'],
            'tensions' => $row['tensions'],
            'questions' => $row['questions'],
            'chekhov' => $row['chekhov'],
            'connections' => $row['connections'],
            '_selected' => (bool)$row['is_selected']
        ];
    }

    // 3. Chekhov
    $stmt = $pdo->prepare("SELECT * FROM wroom_chekhov WHERE user_id = ? ORDER BY id ASC");
    $stmt->execute([$userId]);
    $chekhov = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $chekhov[] = [
            'threadId' => $row['thread_id'],
            'desc' => $row['description'],
            'ep' => $row['ep'],
            'paid' => (bool)$row['is_paid']
        ];
    }

    // 4. Deltas (Desc)
    $stmt = $pdo->prepare("SELECT * FROM wroom_deltas WHERE user_id = ? ORDER BY id DESC");
    $stmt->execute([$userId]);
    $deltas = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $deltas[] = [
            'date' => $row['date_str'],
            'topic' => $row['topic'],
            'decisions' => json_decode($row['decisions'] ?: '[]', true),
            'deferred' => json_decode($row['deferred'] ?: '[]', true),
            'threads' => json_decode($row['threads'] ?: '[]', true),
            'updates' => $row['updates']
        ];
    }

    // 5. Chat Sessions
    $stmt = $pdo->prepare("SELECT session_id, title, updated_at FROM wroom_chat_sessions WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$userId]);
    $chatSessions = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $chatSessions[] = [
            'id' => $row['session_id'],
            'title' => $row['title'],
            'updated_at' => $row['updated_at']
        ];
    }

    $currentSessionId = $chatSessions[0]['id'] ?? null;

    // 6. Conversation (for most recent session)
    $conversation = [];
    if ($currentSessionId) {
        $stmt = $pdo->prepare("SELECT * FROM wroom_conversation WHERE user_id = ? AND session_id = ? ORDER BY id ASC");
        $stmt->execute([$userId, $currentSessionId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $conversation[] = [
                'role' => $row['role'],
                'content' => $row['content'],
                'protocol' => $row['protocol'],
                'ts' => (int)$row['ts'],
                'context_snapshot' => $row['context_snapshot'] ? json_decode($row['context_snapshot'], true) : null
            ];
        }
    }

    echo json_encode([
        'success' => true,
        'state' => [
            'settings' => $settings,
            'context' => $context,
            'threads' => $threads,
            'chekhov' => $chekhov,
            'deltas' => $deltas,
            'chat_sessions' => $chatSessions,
            'chat_session_id' => $currentSessionId,
            'conversation' => $conversation
        ]
    ]);
    exit;
}

if ($action === 'load_chat') {
    $sessionId = $_GET['session_id'] ?? '';
    $stmt = $pdo->prepare("SELECT * FROM wroom_conversation WHERE user_id = ? AND session_id = ? ORDER BY id ASC");
    $stmt->execute([$userId, $sessionId]);
    $conversation = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $conversation[] = [
            'role' => $row['role'],
            'content' => $row['content'],
            'protocol' => $row['protocol'],
            'ts' => (int)$row['ts'],
            'context_snapshot' => $row['context_snapshot'] ? json_decode($row['context_snapshot'], true) : null
        ];
    }
    echo json_encode(['success' => true, 'conversation' => $conversation]);
    exit;
}

if ($action === 'delete_chat') {
    $sessionId = $data['session_id'] ?? '';
    $pdo->prepare("DELETE FROM wroom_chat_sessions WHERE user_id = ? AND session_id = ?")->execute([$userId, $sessionId]);
    $pdo->prepare("DELETE FROM wroom_conversation WHERE user_id = ? AND session_id = ?")->execute([$userId, $sessionId]);
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'save_state') {
    $pdo->beginTransaction();
    try {
        // 1. Settings & Context
        $s = $data['settings'] ?? [];
        $c = $data['context'] ?? [];
        $stmt = $pdo->prepare("
            INSERT INTO wroom_settings
            (user_id, model, depth, auto_failure, session_topic, context_phase, context_status, context_focus, context_registry_ver, context_extra, offline_mode, kg_selected_nodes, kg_with_content, kg_with_edges, draft_delta)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            model=VALUES(model), depth=VALUES(depth), auto_failure=VALUES(auto_failure), session_topic=VALUES(session_topic),
            context_phase=VALUES(context_phase), context_status=VALUES(context_status), context_focus=VALUES(context_focus),
            context_registry_ver=VALUES(context_registry_ver), context_extra=VALUES(context_extra), offline_mode=VALUES(offline_mode),
            kg_selected_nodes=VALUES(kg_selected_nodes), kg_with_content=VALUES(kg_with_content), kg_with_edges=VALUES(kg_with_edges),
            draft_delta=VALUES(draft_delta)
        ");
        $stmt->execute([
            $userId,
            '', 
            $s['depth'] ?? 'standard',
            $s['autoFailure'] ?? 'no',
            $s['sessionTopic'] ?? '',
            $c['phase'] ?? 'S3',
            $c['status'] ?? '',
            $c['focus'] ?? '',
            $c['registryVer'] ?? 'v0.1',
            $c['extra'] ?? '',
            !empty($s['offlineMode']) ? 1 : 0,
            json_encode($s['kgSelectedNodes'] ?? []),
            !empty($s['kgWithContent']) ? 1 : 0,
            isset($s['kgWithEdges']) && empty($s['kgWithEdges']) ? 0 : 1,
            !empty($s['draftDelta']) ? json_encode($s['draftDelta']) : null
        ]);

        // 2. Threads
        $pdo->prepare("DELETE FROM wroom_threads WHERE user_id = ?")->execute([$userId]);
        if (!empty($data['threads'])) {
            $tStmt = $pdo->prepare("
                INSERT INTO wroom_threads
                (user_id, thread_id, name, type, seasons, status, axis, tensions, questions, chekhov, connections, is_selected)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($data['threads'] as $t) {
                $tStmt->execute([
                    $userId,
                    $t['id'] ?? '',
                    $t['name'] ?? '',
                    $t['type'] ?? '',
                    $t['seasons'] ?? '',
                    $t['status'] ?? '',
                    $t['axis'] ?? '',
                    $t['tensions'] ?? '',
                    $t['questions'] ?? '',
                    $t['chekhov'] ?? '',
                    $t['connections'] ?? '',
                    !empty($t['_selected']) ? 1 : 0
                ]);
            }
        }

        // 3. Chekhov
        $pdo->prepare("DELETE FROM wroom_chekhov WHERE user_id = ?")->execute([$userId]);
        if (!empty($data['chekhov'])) {
            $cStmt = $pdo->prepare("
                INSERT INTO wroom_chekhov (user_id, thread_id, description, ep, is_paid)
                VALUES (?, ?, ?, ?, ?)
            ");
            foreach ($data['chekhov'] as $chk) {
                $cStmt->execute([
                    $userId,
                    $chk['threadId'] ?? '',
                    $chk['desc'] ?? '',
                    $chk['ep'] ?? '',
                    !empty($chk['paid']) ? 1 : 0
                ]);
            }
        }

        // 4. Deltas
        $pdo->prepare("DELETE FROM wroom_deltas WHERE user_id = ?")->execute([$userId]);
        if (!empty($data['deltas'])) {
            $dStmt = $pdo->prepare("
                INSERT INTO wroom_deltas (user_id, date_str, topic, decisions, deferred, threads, updates)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $deltasReversed = array_reverse($data['deltas']);
            foreach ($deltasReversed as $d) {
                $dStmt->execute([
                    $userId,
                    $d['date'] ?? '',
                    $d['topic'] ?? '',
                    json_encode($d['decisions'] ?? []),
                    json_encode($d['deferred'] ?? []),
                    json_encode($d['threads'] ?? []),
                    $d['updates'] ?? ''
                ]);
            }
        }

        // 5. Chat Sessions & Conversation
        $chatSessionId = $data['chat_session_id'] ?? 'default';
        $chatSessionTitle = $data['chat_session_title'] ?? 'New Session';

        // Upsert the chat session metadata
        if ($chatSessionId && !empty($data['conversation'])) {
            $stmt = $pdo->prepare("
                INSERT INTO wroom_chat_sessions (session_id, user_id, title, updated_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE title = VALUES(title), updated_at = NOW()
            ");
            $stmt->execute([$chatSessionId, $userId, $chatSessionTitle]);
        }

        // Overwrite only THIS specific chat session's conversation
        $pdo->prepare("DELETE FROM wroom_conversation WHERE user_id = ? AND session_id = ?")
            ->execute([$userId, $chatSessionId]);
            
        if (!empty($data['conversation'])) {
            $convStmt = $pdo->prepare("
                INSERT INTO wroom_conversation (user_id, session_id, role, content, protocol, ts, context_snapshot)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($data['conversation'] as $msg) {
                $convStmt->execute([
                    $userId,
                    $chatSessionId,
                    $msg['role'] ?? '',
                    $msg['content'] ?? '',
                    $msg['protocol'] ?? '',
                    $msg['ts'] ?? time() * 1000,
                    isset($msg['context_snapshot']) ? json_encode($msg['context_snapshot']) : null
                ]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

if ($action === 'generate' || $action === 'export_prompt') {
    try {
        $repo = new \App\Service\GeneratorRepository($pdo);
        $configId = 'wroom_architect_v1';
        $rec = $repo->findActiveForUser($configId, $userId);
        
        if (!$rec) {
            echo json_encode(['error' => "Generator config '$configId' not found or is inactive. Please create/enable it in Generator Forge."]);
            exit;
        }

        $systemRole = $rec->systemRole;
        $instructionsArr = $rec->instructions ?: [];
        $model = $rec->model; 

        // Build dynamically infused System Message
        $sysParts = [];
        if ($systemRole) {
            $sysParts[] = $systemRole;
        }
        if (!empty($instructionsArr)) {
            $sysParts[] = "═══ RULES & PROTOCOLS ═══\n" . implode("\n\n", $instructionsArr);
        }

        $threads = $data['threads'] ?? [];
        if (!empty($threads)) {
            $sysParts[] = "═══ THREAD REGISTRY (JSON) ═══\n" . json_encode($threads, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $sysParts[] = "═══ THREAD REGISTRY ═══\n(No threads in registry yet — the showrunner will add threads as the Registry builds.)";
        }

        $deltas = $data['deltas'] ?? [];
        if (!empty($deltas)) {
            $sysParts[] = "═══ RECENT SESSION DELTAS (JSON) ═══\n" . json_encode($deltas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $sysParts[] = "═══ RECENT SESSION DELTAS ═══\n(No prior sessions)";
        }

        $depth = $data['depth'] ?? 'standard';
        $depthInstr = $depth === 'brief'
            ? 'Keep your response focused and concise — key findings only, no sub-sections unless critical.'
            : ($depth === 'deep'
                ? 'Provide maximum depth: explore every sub-question, list all thread connections, surface every implication. Be thorough.'
                : 'Provide full protocol output — complete but focused. Do not pad.');
        $sysParts[] = "═══ RESPONSE INSTRUCTIONS ═══\n" . $depthInstr;

        $systemMsg = implode("\n\n", $sysParts);

        // Build API Message Payload
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => $systemMsg];

        $conversation = $data['conversation'] ?? [];
        foreach ($conversation as $msg) {
            $messages[] = [
                'role' => $msg['role'] === 'user' ? 'user' : 'assistant',
                'content' => $msg['content']
            ];
        }

        if ($action === 'export_prompt') {
            echo json_encode(['success' => true, 'messages' => $messages]);
            exit;
        }

        // Hand off to central AI orchestration
        $aiProvider = $spw->getAIProvider(); 
        
        $aiOptions = [];
        if (isset($rec->parameters['temperature'])) {
            $aiOptions['temperature'] = (float)$rec->parameters['temperature']['default'];
        }
        if (isset($rec->parameters['max_tokens'])) {
            $aiOptions['max_tokens'] = (int)$rec->parameters['max_tokens']['default'];
        } else {
            $aiOptions['max_tokens'] = 2500;
        }
        
        $rawResponse = $aiProvider->sendMessage($model, $messages, $aiOptions);

        echo json_encode(['success' => true, 'raw_response' => $rawResponse]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}
