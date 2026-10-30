<?php
// ============================================================================
// File: public/kg_manager_api.php
// Backend Engine for AI Knowledge Graph Agent
// ============================================================================
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }

global $spw;
$pdo = $spw->getPDO();

// --- Zero-Touch DB Provisioning (Unified Agent Tables + ID Reservations) ---
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

CREATE TABLE IF NOT EXISTS `kg_id_reservations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `project_name` varchar(255) NOT NULL,
  `target_table` varchar(50) NOT NULL DEFAULT 'kg_nodes',
  `start_id` int(11) NOT NULL,
  `end_id` int(11) NOT NULL,
  `current_id` int(11) NOT NULL,
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Safety migration for legacy reservations that didn't have target_table
try {
    $pdo->exec("ALTER TABLE kg_id_reservations ADD COLUMN target_table VARCHAR(50) NOT NULL DEFAULT 'kg_nodes' AFTER project_name");
} catch(\Exception $e) {}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? $data['action'] ?? '';
$modulePrefix = 'kg';

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
}

function getSessions($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT id, title, updated_at FROM agent_chat_sessions WHERE user_id = ? AND module = 'kg' ORDER BY updated_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ensureSession($pdo, $userId, ?string $sessionId, string $titleHint = 'New KG Session'): string {
    if ($sessionId) {
        $stmt = $pdo->prepare("SELECT id FROM agent_chat_sessions WHERE id = ? AND user_id = ? AND module = 'kg'");
        $stmt->execute([$sessionId, $userId]);
        if ($stmt->fetchColumn()) return $sessionId;
    }
    $newId = generateUUID();
    $stmt = $pdo->prepare("INSERT INTO agent_chat_sessions (id, user_id, module, title, lore) VALUES (?, ?, 'kg', ?, '')");
    $stmt->execute([$newId, $userId, $titleHint]);
    return $newId;
}

function getSessionKgState($pdo, ?string $sessionId) {
    // 1. Fetch active reservations
    $reservations = $pdo->query("SELECT * FROM kg_id_reservations ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    // 2. Fetch session working set (nodes linked to this session)
    $sessionNodes = [];
    if ($sessionId) {
        $stmt = $pdo->prepare("
            SELECT n.id, n.name, n.node_type 
            FROM agent_session_items i
            JOIN kg_nodes n ON i.item_id = n.id
            WHERE i.session_id = ?
            ORDER BY i.created_at DESC
        ");
        $stmt->execute([$sessionId]);
        $sessionNodes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    return [
        'reservations' => $reservations,
        'session_nodes' => $sessionNodes
    ];
}

function writeResponse($pdo, $userId, $sessionId, $extra = []) {
    return array_merge([
        'success' => true,
        'session_id' => $sessionId,
        'sessions' => getSessions($pdo, $userId),
        'data_state' => getSessionKgState($pdo, $sessionId),
    ], $extra);
}

try {
    if ($action === 'load_state') {
        $sessionId = $_GET['session_id'] ?? $data['session_id'] ?? null;
        $chat = [];
        $lore = '';
        $model = \App\Core\AIProvider::getDefaultModel();

        if ($sessionId) {
            $stmt = $pdo->prepare("SELECT id, lore, model FROM agent_chat_sessions WHERE id = ? AND user_id = ? AND module = 'kg'");
            $stmt->execute([$sessionId, $userId]);
            $sess = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($sess) {
                $lore = $sess['lore'] ?: '';
                $model = $sess['model'] ?: $model;
                $stmt = $pdo->prepare("SELECT id, role, content, operations_log, ts FROM agent_chat_messages WHERE session_id = ? ORDER BY ts ASC");
                $stmt->execute([$sessionId]);
                $chat = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else { $sessionId = null; }
        }

        echo json_encode([
            'success' => true,
            'session_id' => $sessionId,
            'sessions' => getSessions($pdo, $userId),
            'data_state' => getSessionKgState($pdo, $sessionId),
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
            'data_state' => getSessionKgState($pdo, $sessionId)
        ]);
        exit;
    }

    if ($action === 'save_settings') {
        $lore = trim($data['lore'] ?? '');
        $model = trim($data['model'] ?? \App\Core\AIProvider::getDefaultModel());
        $sessionId = ensureSession($pdo, $userId, $data['session_id'] ?? null);
        $pdo->prepare("UPDATE agent_chat_sessions SET lore = ?, model = ? WHERE id = ? AND user_id = ? AND module = 'kg'")->execute([$lore, $model, $sessionId, $userId]);
        echo json_encode(writeResponse($pdo, $userId, $sessionId));
        exit;
    }

    if ($action === 'rename_session') {
        $sessionId = $data['session_id'] ?? '';
        $title = trim($data['title'] ?? '');
        if ($sessionId && $title !== '') {
            $pdo->prepare("UPDATE agent_chat_sessions SET title = ? WHERE id = ? AND user_id = ? AND module = 'kg'")->execute([$title, $sessionId, $userId]);
        }
        echo json_encode(['success' => true, 'sessions' => getSessions($pdo, $userId)]);
        exit;
    }

    if ($action === 'delete_session') {
        $sessionId = $data['session_id'] ?? '';
        $pdo->prepare("DELETE FROM agent_chat_sessions WHERE id = ? AND user_id = ? AND module = 'kg'")->execute([$sessionId, $userId]);
        $pdo->prepare("DELETE FROM agent_chat_messages WHERE session_id = ?")->execute([$sessionId]);
        $pdo->prepare("DELETE FROM agent_session_items WHERE session_id = ?")->execute([$sessionId]);
        echo json_encode(['success' => true, 'sessions' => getSessions($pdo, $userId)]);
        exit;
    }

    if ($action === 'delete_msg') {
        $msgId = $data['msg_id'] ?? 0;
        $sessionId = $data['session_id'] ?? '';
        if ($msgId && $sessionId) {
            $pdo->prepare("DELETE FROM agent_chat_messages WHERE id = ? AND session_id = ?")->execute([$msgId, $sessionId]);
        }
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($action === 'unlink_node') {
        $sessionId = $data['session_id'] ?? null;
        $id = (int)($data['id'] ?? 0);
        if ($id > 0 && $sessionId) {
            $pdo->prepare("DELETE FROM agent_session_items WHERE session_id = ? AND item_id = ?")->execute([$sessionId, $id]);
        }
        echo json_encode(writeResponse($pdo, $userId, $sessionId));
        exit;
    }

    if ($action === 'branch_chat') {
        $oldSessionId = $data['old_session_id'] ?? '';
        $msgId = $data['msg_id'] ?? 0;
        if (!$oldSessionId || !$msgId) throw new Exception("Missing parameters");

        $stmt = $pdo->prepare("SELECT title, lore, model FROM agent_chat_sessions WHERE id = ? AND user_id = ? AND module = 'kg'");
        $stmt->execute([$oldSessionId, $userId]);
        $sess = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sess) throw new Exception("Session not found");

        $newSessionId = generateUUID();
        $newTitle = $sess['title'] . ' (Branch)';
        
        $pdo->prepare("INSERT INTO agent_chat_sessions (id, user_id, module, title, lore, model) VALUES (?, ?, 'kg', ?, ?, ?)")->execute([$newSessionId, $userId, $newTitle, $sess['lore'], $sess['model']]);
        $pdo->prepare("INSERT INTO agent_chat_messages (session_id, role, content, operations_log, ts) SELECT ?, role, content, operations_log, ts FROM agent_chat_messages WHERE session_id = ? AND id <= ? ORDER BY ts ASC")->execute([$newSessionId, $oldSessionId, $msgId]);
        $pdo->prepare("INSERT INTO agent_session_items (session_id, item_id, created_at) SELECT ?, item_id, created_at FROM agent_session_items WHERE session_id = ?")->execute([$newSessionId, $oldSessionId]);

        echo json_encode(['success' => true, 'new_session_id' => $newSessionId]);
        exit;
    }

    if ($action === 'export_chat') {
        $sessionId = $_GET['session_id'] ?? '';
        $stmt = $pdo->prepare("SELECT title FROM agent_chat_sessions WHERE id = ? AND user_id = ? AND module = 'kg'");
        $stmt->execute([$sessionId, $userId]);
        $title = $stmt->fetchColumn() ?: 'Untitled Session';

        $output = "KG AGENT EXPORT\nSession: {$title}\n\n";
        $stmt = $pdo->prepare("SELECT role, content FROM agent_chat_messages WHERE session_id = ? ORDER BY ts ASC");
        $stmt->execute([$sessionId]);
        while ($msg = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $role = strtoupper($msg['role']);
            $output .= "[{$role}]:\n{$msg['content']}\n\n";
        }
        echo json_encode(['success' => true, 'export_text' => $output]);
        exit;
    }

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
            $title = mb_substr(preg_replace('/=== FILE:.*?===.*?====================/s', '', $userMsg), 0, 40) ?: 'New KG Session';
            $pdo->prepare("UPDATE agent_chat_sessions SET title = ? WHERE id = ? AND module = 'kg'")->execute([$title, $sessionId]);
        }

        $pyapiUrl = rtrim($GLOBALS['WORDNET_PYAPI_URL'] ?? 'http://127.0.0.1:8009', '/');
        $ch = curl_init($pyapiUrl . '/aiprovider/agent_bg');
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['module' => 'kg', 'session_id' => $sessionId, 'user_id' => (int)$userId]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_exec($ch);
        curl_close($ch);

        echo json_encode(writeResponse($pdo, $userId, $sessionId, ['status' => 'processing']));
        exit;
    }

    // --- KG Custom Endpoints ---
    if ($action === 'fetch_tree') {
        $cats = $pdo->query("SELECT id, parent_id, name FROM kg_categories ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
        $nodes = $pdo->query("SELECT id, category_id, name, node_type FROM kg_nodes WHERE status='active' ORDER BY sort_order ASC, name ASC")->fetchAll(PDO::FETCH_ASSOC);
        
        $tree = [];
        foreach ($cats as $c) {
            $tree[] = [
                'id'     => 'c_' . $c['id'],
                'parent' => $c['parent_id'] ? 'c_' . $c['parent_id'] : '#',
                'text'   => $c['name'] . " <span style='opacity:0.5;font-size:0.8em'>#" . $c['id'] . "</span>",
                'type'   => 'folder',
                'data'   => ['db_id' => $c['id'], 'type' => 'category']
            ];
        }
        foreach ($nodes as $n) {
            $tree[] = [
                'id'     => 'n_' . $n['id'],
                'parent' => $n['category_id'] ? 'c_' . $n['category_id'] : '#',
                'text'   => $n['name'] . " <span style='opacity:0.5;font-size:0.8em'>#" . $n['id'] . "</span>",
                'type'   => 'node',
                'data'   => ['db_id' => $n['id'], 'type' => 'node', 'node_type' => $n['node_type']]
            ];
        }
        echo json_encode(['success' => true, 'tree' => $tree]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);

} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}