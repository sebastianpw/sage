<?php
// public/chatty_api.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

global $spw;
$pdo = $spw->getPDO();

// Zero-touch database schema provisioning
$pdo->exec("CREATE TABLE IF NOT EXISTS `chatty_chat_sessions` (
  `session_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`session_id`),
  KEY `idx_chatty_chats_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS `chatty_conversation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(50) DEFAULT 'default',
  `role` varchar(50) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `ts` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_chatty_conversation_user` (`user_id`),
  KEY `idx_chatty_conv_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS `chatty_settings` (
  `user_id` int(11) NOT NULL,
  `model` varchar(100) DEFAULT 'openai',
  `system_prompt` longtext DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

$pdo->exec("CREATE TABLE IF NOT EXISTS `chatty_session_settings` (
  `session_id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `model` varchar(100) DEFAULT 'openai',
  `system_prompt` longtext DEFAULT NULL,
  PRIMARY KEY (`session_id`),
  KEY `idx_chatty_session_settings_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Zero-touch migration for new notification columns
try { $pdo->exec("ALTER TABLE chatty_settings ADD COLUMN mute_notifications TINYINT(1) DEFAULT 0"); } catch (\Exception $e) {}
try { $pdo->exec("ALTER TABLE chatty_settings ADD COLUMN last_read_msg_id INT(11) DEFAULT 0"); } catch (\Exception $e) {}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? $data['action'] ?? '';

// ── LOAD STATE ─────────────────────────────────────────────────────────────
if ($action === 'load_state') {
    $stmt = $pdo->prepare("SELECT * FROM chatty_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $globalSettings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    $stmt = $pdo->prepare("SELECT session_id as id, title, updated_at FROM chatty_chat_sessions WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$userId]);
    $chatSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $currentSessionId = $chatSessions[0]['id'] ?? null;
    
    $sessSettingsRow = [];
    if ($currentSessionId) {
        $stmt = $pdo->prepare("SELECT * FROM chatty_session_settings WHERE user_id = ? AND session_id = ?");
        $stmt->execute([$userId, $currentSessionId]);
        $sessSettingsRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }
    
    $settings = [
        'model' => $sessSettingsRow['model'] ?? $globalSettings['model'] ?? 'openai',
        'system_prompt' => $sessSettingsRow['system_prompt'] ?? $globalSettings['system_prompt'] ?? '',
        'mute_notifications' => (bool)($globalSettings['mute_notifications'] ?? false)
    ];
    
    $conversation = [];
    if ($currentSessionId) {
        $stmt = $pdo->prepare("SELECT * FROM chatty_conversation WHERE user_id = ? AND session_id = ? ORDER BY id ASC");
        $stmt->execute([$userId, $currentSessionId]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $conversation[] = [
                'id' => (int)$row['id'],
                'role' => $row['role'],
                'content' => $row['content'],
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
            'conversation' => $conversation
        ]
    ]);
    exit;
}

// ── UNREAD NOTIFICATIONS ────────────────────────────────────────────────
if ($action === 'check_unread') {
    $stmt = $pdo->prepare("SELECT last_read_msg_id, mute_notifications FROM chatty_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $set = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $lastRead = (int)($set['last_read_msg_id'] ?? 0);
    $mute = (int)($set['mute_notifications'] ?? 0);

    if ($mute) {
        echo json_encode(['success' => true, 'unread' => 0]);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM chatty_conversation WHERE user_id = ? AND role = 'assistant' AND id > ?");
    $stmt->execute([$userId, $lastRead]);
    $unreadCount = (int)$stmt->fetchColumn();

    echo json_encode(['success' => true, 'unread' => $unreadCount]);
    exit;
}

if ($action === 'mark_read') {
    $stmt = $pdo->prepare("SELECT MAX(id) FROM chatty_conversation WHERE user_id = ?");
    $stmt->execute([$userId]);
    $maxId = (int)$stmt->fetchColumn();
    
    if ($maxId > 0) {
        $stmt = $pdo->prepare("UPDATE chatty_settings SET last_read_msg_id = ? WHERE user_id = ?");
        $stmt->execute([$maxId, $userId]);
    }
    
    echo json_encode(['success' => true]);
    exit;
}

// ── LIST CHAT SESSIONS ──────────────────────────────────────────────────
if ($action === 'list_chat_sessions') {
    $page = max(1, (int)($_GET['page'] ?? $data['page'] ?? 1));
    $limit = max(1, (int)($_GET['limit'] ?? $data['limit'] ?? 20));
    $offset = ($page - 1) * $limit;

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM chatty_chat_sessions WHERE user_id = ?");
    $countStmt->execute([$userId]);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT session_id as id, title, updated_at FROM chatty_chat_sessions WHERE user_id = ? ORDER BY updated_at DESC LIMIT ? OFFSET ?");
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

// ── LOAD SPECIFIC CHAT ──────────────────────────────────────────────────
if ($action === 'load_chat') {
    $sessionId = $_GET['session_id'] ?? '';
    
    $stmt = $pdo->prepare("SELECT * FROM chatty_conversation WHERE user_id = ? AND session_id = ? ORDER BY id ASC");
    $stmt->execute([$userId, $sessionId]);
    $conversation = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $conversation[] = [
            'id' => (int)$row['id'],
            'role' => $row['role'],
            'content' => $row['content'],
            'ts' => (int)$row['ts']
        ];
    }
    
    $stmt = $pdo->prepare("SELECT * FROM chatty_session_settings WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$userId, $sessionId]);
    $sessSettingsRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    $stmt = $pdo->prepare("SELECT * FROM chatty_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $globalSettings = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    
    $settings = [
        'model' => $sessSettingsRow['model'] ?? $globalSettings['model'] ?? 'openai',
        'system_prompt' => $sessSettingsRow['system_prompt'] ?? $globalSettings['system_prompt'] ?? ''
    ];

    echo json_encode(['success' => true, 'conversation' => $conversation, 'settings' => $settings]);
    exit;
}

// ── SAVE SETTINGS ───────────────────────────────────────────────────────
if ($action === 'save_settings') {
    $s = $data['settings'] ?? [];
    $sessionId = $data['chat_session_id'] ?? '';
    
    if ($sessionId) {
        $stmt = $pdo->prepare("
            INSERT INTO chatty_session_settings (session_id, user_id, model, system_prompt)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
            model=VALUES(model), system_prompt=VALUES(system_prompt)
        ");
        $stmt->execute([
            $sessionId,
            $userId,
            $s['model'] ?? 'openai',
            $s['system_prompt'] ?? ''
        ]);
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO chatty_settings (user_id, model, system_prompt, mute_notifications)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        model=VALUES(model), system_prompt=VALUES(system_prompt), mute_notifications=VALUES(mute_notifications)
    ");
    $stmt->execute([
        $userId,
        $s['model'] ?? 'openai',
        $s['system_prompt'] ?? '',
        (int)($s['mute_notifications'] ?? 0)
    ]);
    echo json_encode(['success' => true]);
    exit;
}

// ── RENAME CHAT ─────────────────────────────────────────────────────────
if ($action === 'rename_chat') {
    $sessionId = $data['session_id'] ?? '';
    $title = $data['title'] ?? '';
    if ($sessionId && $title) {
        $pdo->prepare("UPDATE chatty_chat_sessions SET title = ? WHERE session_id = ? AND user_id = ?")
            ->execute([$title, $sessionId, $userId]);
    }
    echo json_encode(['success' => true]);
    exit;
}

// ── DELETE CHAT ─────────────────────────────────────────────────────────
if ($action === 'delete_chat') {
    $sessionId = $data['session_id'] ?? '';
    $pdo->prepare("DELETE FROM chatty_chat_sessions WHERE user_id = ? AND session_id = ?")->execute([$userId, $sessionId]);
    $pdo->prepare("DELETE FROM chatty_conversation WHERE user_id = ? AND session_id = ?")->execute([$userId, $sessionId]);
    $pdo->prepare("DELETE FROM chatty_session_settings WHERE user_id = ? AND session_id = ?")->execute([$userId, $sessionId]);
    echo json_encode(['success' => true]);
    exit;
}

// ── BRANCH CHAT ─────────────────────────────────────────────────────────
if ($action === 'branch_chat') {
    $oldSessionId = $data['old_session_id'] ?? '';
    $newSessionId = $data['new_session_id'] ?? '';
    $msgId = $data['msg_id'] ?? 0;
    
    if (!$oldSessionId || !$newSessionId || !$msgId) {
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }
    
    $stmt = $pdo->prepare("SELECT title FROM chatty_chat_sessions WHERE session_id = ? AND user_id = ?");
    $stmt->execute([$oldSessionId, $userId]);
    $title = $stmt->fetchColumn() ?: 'Branched Chat';
    $title .= ' (Branch)';
    
    $stmt = $pdo->prepare("INSERT INTO chatty_chat_sessions (session_id, user_id, title, updated_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$newSessionId, $userId, $title]);
    
    $stmt = $pdo->prepare("INSERT INTO chatty_conversation (user_id, session_id, role, content, ts) SELECT user_id, ?, role, content, ts FROM chatty_conversation WHERE session_id = ? AND user_id = ? AND id <= ? ORDER BY id ASC");
    $stmt->execute([$newSessionId, $oldSessionId, $userId, $msgId]);
    
    $stmt = $pdo->prepare("INSERT INTO chatty_session_settings (session_id, user_id, model, system_prompt) SELECT ?, user_id, model, system_prompt FROM chatty_session_settings WHERE session_id = ? AND user_id = ?");
    $stmt->execute([$newSessionId, $oldSessionId, $userId]);
    
    echo json_encode(['success' => true]);
    exit;
}

// ── DELETE MESSAGE ──────────────────────────────────────────────────────
if ($action === 'delete_msg') {
    $msgId = $data['msg_id'] ?? 0;
    $sessionId = $data['chat_session_id'] ?? '';
    
    if (!$msgId || !$sessionId) {
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }
    
    $pdo->prepare("DELETE FROM chatty_conversation WHERE id = ? AND session_id = ? AND user_id = ?")->execute([$msgId, $sessionId, $userId]);
    
    echo json_encode(['success' => true]);
    exit;
}

// ── EXPORT CHAT ─────────────────────────────────────────────────────────
if ($action === 'export_chat') {
    $sessionId = $_GET['chat_session_id'] ?? $data['chat_session_id'] ?? '';
    if (!$sessionId) {
        echo json_encode(['error' => 'Missing chat_session_id']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT title FROM chatty_chat_sessions WHERE session_id = ? AND user_id = ?");
    $stmt->execute([$sessionId, $userId]);
    $title = $stmt->fetchColumn() ?: 'Untitled Chat';

    $output = "CHATTY EXPORT\n";
    $output .= "Session ID: " . $sessionId . "\n";
    $output .= "Title: " . $title . "\n\n";
    $output .= "--- CONVERSATION LOG ---\n\n";

    $stmt = $pdo->prepare("SELECT role, content FROM chatty_conversation WHERE user_id = ? AND session_id = ? ORDER BY id ASC");
    $stmt->execute([$userId, $sessionId]);
    
    while ($msg = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $role = strtoupper($msg['role']);
        $text = $msg['content'];
        $output .= "[{$role}]:\n{$text}\n\n";
    }

    echo json_encode(['success' => true, 'export_text' => $output]);
    exit;
}

// ── SEND MESSAGE (DYNAMIC MODEL CHAT) ───────────────────────────────────
if ($action === 'send_message') {
    $sessionId = $data['chat_session_id'] ?? '';
    $message = $data['message'] ?? '';
    $settings = $data['settings'] ?? [];
    
    if (!$sessionId || trim($message) === '') {
        echo json_encode(['error' => 'Missing session or message']);
        exit;
    }

    $nowMs = (int)round(microtime(true) * 1000);
    
    // Save user message
    $stmt = $pdo->prepare("INSERT INTO chatty_conversation (user_id, session_id, role, content, ts) VALUES (?, ?, 'user', ?, ?)");
    $stmt->execute([$userId, $sessionId, $message, $nowMs]);
    $userMsgId = $pdo->lastInsertId();
    
    // Save/Sync settings for the background runner
    $stmt = $pdo->prepare("
        INSERT INTO chatty_session_settings (session_id, user_id, model, system_prompt)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        model=VALUES(model), system_prompt=VALUES(system_prompt)
    ");
    $stmt->execute([
        $sessionId,
        $userId,
        $settings['model'] ?? 'openai',
        $settings['system_prompt'] ?? ''
    ]);

    // Update or create session representation
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM chatty_conversation WHERE user_id = ? AND session_id = ?");
    $stmt->execute([$userId, $sessionId]);
    if ($stmt->fetchColumn() == 1) {
        $titleRaw = preg_replace('/=== FILE:.*?===.*?====================/s', '', $message);
        $titleRaw = trim($titleRaw);
        if ($titleRaw === '') $titleRaw = 'File Upload';
        $title = mb_substr($titleRaw, 0, 40);
        
        $stmt = $pdo->prepare("
            INSERT INTO chatty_chat_sessions (session_id, user_id, title, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE title=VALUES(title)
        ");
        $stmt->execute([$sessionId, $userId, $title]);
    } else {
        $pdo->prepare("UPDATE chatty_chat_sessions SET updated_at = NOW() WHERE session_id = ? AND user_id = ?")->execute([$sessionId, $userId]);
    }

    // TRIGGER ASYNC GENERATION VIA PYAPI BACKGROUND TASKS
    $pyapiUrl = rtrim($GLOBALS['WORDNET_PYAPI_URL'] ?? 'http://127.0.0.1:8009', '/');
    $ch = curl_init($pyapiUrl . '/aiprovider/chatty_bg');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'session_id' => $sessionId,
        'user_id' => $userId
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 5 seconds connect/execute limit to fire off the background task
    curl_exec($ch);
    curl_close($ch);

    echo json_encode([
        'success' => true,
        'user_msg_id' => $userMsgId,
        'status' => 'processing'
    ]);
    exit;
}