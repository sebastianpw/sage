<?php
// ============================================================================
// File: public/sketches_api.php
// Backend Engine for AI Sketches Agent & Database Bridge
// ============================================================================
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { 
    echo json_encode(['success' => false, 'error' => 'Unauthorized']); 
    exit; 
}

global $spw;
$pdo = $spw->getPDO();

// --- 1. Zero-Touch Database Provisioning (Unified Agent Tables) ---
$pdo->exec("
CREATE TABLE IF NOT EXISTS `agent_chat_sessions` (
  `id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `module` varchar(50) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `lore` longtext DEFAULT NULL,
  `model` varchar(100) DEFAULT 'openai',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_agent_sess_user_mod` (`user_id`, `module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `agent_chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(50) NOT NULL,
  `role` varchar(50) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `operations_log` mediumtext DEFAULT NULL,
  `ts` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_agent_msg_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `agent_session_items` (
  `session_id` varchar(50) NOT NULL,
  `item_id` int(11) NOT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`session_id`, `item_id`),
  KEY `idx_agent_item_id` (`item_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// --- 1.5 Legacy Table Migration (Safely migrate data from previous generation) ---
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'sketches_chat_sessions'");
    if ($chk->rowCount() > 0) {
        $pdo->exec("INSERT IGNORE INTO agent_chat_sessions (id, user_id, module, title, lore, model, updated_at) SELECT id, user_id, 'sketches', title, lore, model, updated_at FROM sketches_chat_sessions");
        $pdo->exec("INSERT IGNORE INTO agent_chat_messages (id, session_id, role, content, operations_log, ts) SELECT id, session_id, role, content, operations_log, ts FROM sketches_chat_messages");
        $pdo->exec("INSERT IGNORE INTO agent_session_items (session_id, item_id, created_at) SELECT session_id, item_id, created_at FROM sketches_chat_session_items");
        
        // Safely drop old tables after migration is complete
        $pdo->exec("DROP TABLE sketches_chat_session_items, sketches_chat_messages, sketches_chat_sessions");
    }
} catch (\Throwable $t) {
    // Silently ignore if migration fails or tables don't exist
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? $data['action'] ?? '';

// --- Helpers ---
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', 
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), 
        mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, 
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function getSessions($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT id, title, updated_at FROM agent_chat_sessions WHERE user_id = ? AND module = 'sketches' ORDER BY updated_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ensureSession($pdo, $userId, ?string $sessionId, string $titleHint = 'New Sketches Session'): string {
    if ($sessionId) {
        $stmt = $pdo->prepare("SELECT id FROM agent_chat_sessions WHERE id = ? AND user_id = ? AND module = 'sketches'");
        $stmt->execute([$sessionId, $userId]);
        if ($stmt->fetchColumn()) return $sessionId;
    }
    $newId = generateUUID();
    $stmt = $pdo->prepare("INSERT INTO agent_chat_sessions (id, user_id, module, title, lore) VALUES (?, ?, 'sketches', ?, '')");
    $stmt->execute([$newId, $userId, $titleHint]);
    return $newId;
}

function getSessionSketchesState($pdo, ?string $sessionId) {
    if (!$sessionId) return [];
    $stmt = $pdo->prepare("
        SELECT s.id, s.name, s.description, s.regenerate_images, s.active_map_run_id, s.created_at
        FROM agent_session_items i
        JOIN sketches s ON i.item_id = s.id
        WHERE i.session_id = ?
        ORDER BY s.id DESC
    ");
    $stmt->execute([$sessionId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function writeResponse($pdo, $userId, $sessionId, $extra = []) {
    return array_merge([
        'success' => true,
        'session_id' => $sessionId,
        'sessions' => getSessions($pdo, $userId),
        'data_state' => getSessionSketchesState($pdo, $sessionId),
    ], $extra);
}

function makeUniqueSketchName($pdo, string $baseName): string {
    $name = trim($baseName) ?: 'Sketch_' . time();
    $counter = 1;
    $testName = $name;
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sketches WHERE name = ?");
        $stmt->execute([$testName]);
        if ((int)$stmt->fetchColumn() === 0) return $testName;
        $testName = $name . '_' . $counter++;
    }
}

// --- ROUTER ---
try {
    if ($action === 'load_state') {
        $sessionId = $_GET['session_id'] ?? $data['session_id'] ?? null;
        $chat = [];
        $lore = '';
        $model = \App\Core\AIProvider::getDefaultModel();

        if ($sessionId) {
            $stmt = $pdo->prepare("SELECT id, lore, model FROM agent_chat_sessions WHERE id = ? AND user_id = ? AND module = 'sketches'");
            $stmt->execute([$sessionId, $userId]);
            $sess = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sess) {
                $sessionId = null;
            } else {
                $lore = $sess['lore'] ?: '';
                $model = $sess['model'] ?: $model;
                $stmt = $pdo->prepare("SELECT id, role, content, operations_log, ts FROM agent_chat_messages WHERE session_id = ? ORDER BY ts ASC");
                $stmt->execute([$sessionId]);
                $chat = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        echo json_encode([
            'success' => true,
            'session_id' => $sessionId,
            'sessions' => getSessions($pdo, $userId),
            'data_state' => getSessionSketchesState($pdo, $sessionId),
            'settings' => ['lore' => $lore, 'model' => $model],
            'model_catalog' => \App\Core\AIProvider::getModelCatalog(),
            'chat' => $chat
        ]);
        exit;
    }

    if ($action === 'load_chat') {
        $sessionId = $_GET['session_id'] ?? $data['session_id'] ?? null;
        $chat = [];
        if ($sessionId) {
            $stmt = $pdo->prepare("SELECT id, role, content, operations_log, ts FROM agent_chat_messages WHERE session_id = ? ORDER BY ts ASC");
            $stmt->execute([$sessionId]);
            $chat = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode([
            'success' => true,
            'chat' => $chat,
            'data_state' => getSessionSketchesState($pdo, $sessionId)
        ]);
        exit;
    }

    if ($action === 'save_settings') {
        $lore = trim($data['lore'] ?? '');
        $model = trim($data['model'] ?? \App\Core\AIProvider::getDefaultModel());
        $sessionId = ensureSession($pdo, $userId, $data['session_id'] ?? null);

        $pdo->prepare("UPDATE agent_chat_sessions SET lore = ?, model = ? WHERE id = ? AND user_id = ? AND module = 'sketches'")
            ->execute([$lore, $model, $sessionId, $userId]);

        echo json_encode(writeResponse($pdo, $userId, $sessionId));
        exit;
    }

    if ($action === 'rename_session') {
        $sessionId = $data['session_id'] ?? '';
        $title = trim($data['title'] ?? '');
        if ($sessionId && $title !== '') {
            $pdo->prepare("UPDATE agent_chat_sessions SET title = ? WHERE id = ? AND user_id = ? AND module = 'sketches'")
                ->execute([$title, $sessionId, $userId]);
        }
        echo json_encode(['success' => true, 'sessions' => getSessions($pdo, $userId)]);
        exit;
    }

    if ($action === 'delete_session') {
        $sessionId = $data['session_id'] ?? '';
        $pdo->prepare("DELETE FROM agent_chat_sessions WHERE id = ? AND user_id = ? AND module = 'sketches'")->execute([$sessionId, $userId]);
        $pdo->prepare("DELETE FROM agent_chat_messages WHERE session_id = ?")->execute([$sessionId]);
        $pdo->prepare("DELETE FROM agent_session_items WHERE session_id = ?")->execute([$sessionId]);
        echo json_encode(['success' => true, 'sessions' => getSessions($pdo, $userId)]);
        exit;
    }

    if ($action === 'save_sketch') {
        $sessionId = ensureSession($pdo, $userId, $data['session_id'] ?? null);
        $name = makeUniqueSketchName($pdo, $data['name'] ?? 'Sketch');
        $desc = trim($data['description'] ?? '');
        $regen = !empty($data['regenerate_images']) ? 1 : 0;

        $stmt = $pdo->prepare("INSERT INTO sketches (name, description, regenerate_images) VALUES (?, ?, ?)");
        $stmt->execute([$name, $desc, $regen]);
        $newId = (int)$pdo->lastInsertId();

        $pdo->prepare("INSERT IGNORE INTO agent_session_items (session_id, item_id) VALUES (?, ?)")
            ->execute([$sessionId, $newId]);

        echo json_encode(writeResponse($pdo, $userId, $sessionId, ['created_id' => $newId]));
        exit;
    }

    if ($action === 'update_sketch_desc') {
        $sessionId = $data['session_id'] ?? null;
        $id = (int)($data['id'] ?? 0);
        $desc = trim($data['description'] ?? '');
        if ($id > 0) {
            $pdo->prepare("UPDATE sketches SET description = ? WHERE id = ?")->execute([$desc, $id]);
        }
        echo json_encode(writeResponse($pdo, $userId, $sessionId));
        exit;
    }

    if ($action === 'delete_sketch') {
        $sessionId = $data['session_id'] ?? null;
        $id = (int)($data['id'] ?? 0);
        if ($id > 0) {
            if ($sessionId) {
                $pdo->prepare("DELETE FROM agent_session_items WHERE session_id = ? AND item_id = ?")->execute([$sessionId, $id]);
            }
            $pdo->prepare("DELETE FROM sketches WHERE id = ?")->execute([$id]);
        }
        echo json_encode(writeResponse($pdo, $userId, $sessionId));
        exit;
    }

    if ($action === 'unlink_sketch') {
        $sessionId = $data['session_id'] ?? null;
        $id = (int)($data['id'] ?? 0);
        if ($id > 0 && $sessionId) {
            $pdo->prepare("DELETE FROM agent_session_items WHERE session_id = ? AND item_id = ?")->execute([$sessionId, $id]);
        }
        echo json_encode(writeResponse($pdo, $userId, $sessionId));
        exit;
    }

    if ($action === 'toggle_regen') {
        $id = (int)($data['id'] ?? 0);
        $val = !empty($data['value']) ? 1 : 0;
        $pdo->prepare("UPDATE sketches SET regenerate_images = ? WHERE id = ?")->execute([$val, $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'run_scheduler') {
        $pdo->prepare("UPDATE scheduled_tasks SET run_now = 1 WHERE id = 15")->execute();
        echo json_encode(['success' => true, 'message' => 'Scheduler triggered (Task #15)']);
        exit;
    }

    // ── UNIVERSAL AGENT ACTIONS (Branch, Export, Delete Msg) ──
    if ($action === 'delete_msg') {
        $msgId = $data['msg_id'] ?? 0;
        $sessionId = $data['session_id'] ?? '';
        if ($msgId && $sessionId) {
            $pdo->prepare("DELETE FROM agent_chat_messages WHERE id = ? AND session_id = ?")->execute([$msgId, $sessionId]);
        }
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'export_chat') {
        $sessionId = $_GET['session_id'] ?? '';
        $stmt = $pdo->prepare("SELECT title FROM agent_chat_sessions WHERE id = ? AND user_id = ? AND module = 'sketches'");
        $stmt->execute([$sessionId, $userId]);
        $title = $stmt->fetchColumn() ?: 'Untitled Session';

        $output = "SKETCHES AGENT EXPORT\nSession: {$title}\n\n";
        $stmt = $pdo->prepare("SELECT role, content FROM agent_chat_messages WHERE session_id = ? ORDER BY ts ASC");
        $stmt->execute([$sessionId]);
        
        while ($msg = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $role = strtoupper($msg['role']);
            $output .= "[{$role}]:\n{$msg['content']}\n\n";
        }
        echo json_encode(['success' => true, 'export_text' => $output]);
        exit;
    }

    if ($action === 'branch_chat') {
        $oldSessionId = $data['old_session_id'] ?? '';
        $msgId = $data['msg_id'] ?? 0;
        if (!$oldSessionId || !$msgId) throw new Exception("Missing parameters");

        $stmt = $pdo->prepare("SELECT title, lore, model FROM agent_chat_sessions WHERE id = ? AND user_id = ? AND module = 'sketches'");
        $stmt->execute([$oldSessionId, $userId]);
        $sess = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sess) throw new Exception("Session not found");

        $newSessionId = generateUUID();
        $newTitle = $sess['title'] . ' (Branch)';
        
        // Copy Session
        $pdo->prepare("INSERT INTO agent_chat_sessions (id, user_id, module, title, lore, model) VALUES (?, ?, 'sketches', ?, ?, ?)")
            ->execute([$newSessionId, $userId, $newTitle, $sess['lore'], $sess['model']]);
            
        // Copy Messages up to msgId
        $pdo->prepare("INSERT INTO agent_chat_messages (session_id, role, content, operations_log, ts) SELECT ?, role, content, operations_log, ts FROM agent_chat_messages WHERE session_id = ? AND id <= ? ORDER BY ts ASC")
            ->execute([$newSessionId, $oldSessionId, $msgId]);
            
        // Copy Linked Items
        $pdo->prepare("INSERT INTO agent_session_items (session_id, item_id, created_at) SELECT ?, item_id, created_at FROM agent_session_items WHERE session_id = ?")
            ->execute([$newSessionId, $oldSessionId]);

        echo json_encode(['success' => true, 'new_session_id' => $newSessionId]);
        exit;
    }

    // ── SEND MESSAGE (Dispatches to the universal runner) ──
    if ($action === 'send_message') {
        $sessionId = $data['session_id'] ?? null;
        $userMsg = trim($data['message'] ?? '');
        if (!$userMsg) throw new Exception("Empty message");

        $sessionId = ensureSession($pdo, $userId, $sessionId, mb_substr($userMsg, 0, 40));

        $tsMs = (int)round(microtime(true) * 1000);
        $stmt = $pdo->prepare("INSERT INTO agent_chat_messages (session_id, role, content, ts) VALUES (?, 'user', ?, ?)");
        $stmt->execute([$sessionId, $userMsg, $tsMs]);

        $count = (int)$pdo->query("SELECT COUNT(*) FROM agent_chat_messages WHERE session_id = '$sessionId'")->fetchColumn();
        if ($count <= 1) {
            $title = mb_substr(preg_replace('/=== FILE:.*?===.*?====================/s', '', $userMsg), 0, 40) ?: 'New Sketches Session';
            $pdo->prepare("UPDATE agent_chat_sessions SET title = ? WHERE id = ? AND module = 'sketches'")->execute([$title, $sessionId]);
        }

        // Trigger Async Execution via PyAPI Universal Agent Endpoint
        $pyapiUrl = rtrim($GLOBALS['WORDNET_PYAPI_URL'] ?? 'http://127.0.0.1:8009', '/');
        $ch = curl_init($pyapiUrl . '/aiprovider/agent_bg');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'module' => 'sketches',
            'session_id' => $sessionId,
            'user_id' => (int)$userId
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);

        echo json_encode(writeResponse($pdo, $userId, $sessionId, [
            'status' => 'processing'
        ]));
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);

} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
