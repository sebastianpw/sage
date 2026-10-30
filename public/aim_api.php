<?php
// ============================================================================
// File: public/aim_api.php
// Backend Engine for AI Idea Manager (session-scoped projects)
// ============================================================================
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) { echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit; }

global $spw;
$pdo = $spw->getPDO();

// --- 1. Zero-Touch Database Migration ---
$pdo->exec("
CREATE TABLE IF NOT EXISTS `aim_chat_sessions` (
  `id` varchar(50) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_aim_sess_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `aim_chat_messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_id` varchar(50) NOT NULL,
  `role` varchar(50) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `operations_log` mediumtext DEFAULT NULL,
  `ts` bigint(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_aim_msg_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `aim_clusters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text,
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `aim_ideas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cluster_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `status` enum('unassigned', 'yes', 'no') DEFAULT 'unassigned',
  `created_at` timestamp DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_aim_ideas_cluster` (`cluster_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `aim_settings` (
  `user_id` int(11) NOT NULL,
  `lore` longtext DEFAULT NULL,
  `model` varchar(100) DEFAULT 'openai',
  PRIMARY KEY (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

// Session-scoping columns (idempotent — silently no-op if already present).
try { $pdo->exec("ALTER TABLE aim_clusters ADD COLUMN session_id VARCHAR(50) NULL DEFAULT NULL"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE aim_ideas ADD COLUMN session_id VARCHAR(50) NULL DEFAULT NULL"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE aim_chat_sessions ADD COLUMN lore LONGTEXT NULL DEFAULT NULL"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE aim_chat_sessions ADD COLUMN kg_selected_nodes LONGTEXT NULL DEFAULT NULL"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE aim_clusters ADD INDEX idx_aim_clusters_session (session_id)"); } catch (\Throwable $e) {}
try { $pdo->exec("ALTER TABLE aim_ideas ADD INDEX idx_aim_ideas_session (session_id)"); } catch (\Throwable $e) {}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $data['action'] ?? '';

// --- HELPER FUNCTIONS ---
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
}

function getSessions($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT id, title, updated_at FROM aim_chat_sessions WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getUserModel($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT model FROM aim_settings WHERE user_id = ?");
    $stmt->execute([$userId]);
    $m = $stmt->fetchColumn();
    return $m ?: \App\Core\AIProvider::getDefaultModel();
}

function ensureSession($pdo, $userId, ?string $sessionId, string $titleHint = 'New Project'): string {
    if ($sessionId) {
        $stmt = $pdo->prepare("SELECT id FROM aim_chat_sessions WHERE id = ? AND user_id = ?");
        $stmt->execute([$sessionId, $userId]);
        if ($stmt->fetchColumn()) return $sessionId;
    }
    $newId = generateUUID();
    $stmt = $pdo->prepare("INSERT INTO aim_chat_sessions (id, user_id, title, lore) VALUES (?, ?, ?, '')");
    $stmt->execute([$newId, $userId, $titleHint]);
    return $newId;
}

function getPortfolioState($pdo, ?string $sessionId) {
    if (!$sessionId) return [];

    $stmt = $pdo->prepare("SELECT * FROM aim_clusters WHERE session_id = ? ORDER BY name ASC");
    $stmt->execute([$sessionId]);
    $clusters = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT * FROM aim_ideas WHERE session_id = ? ORDER BY id ASC");
    $stmt->execute([$sessionId]);
    $ideas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $portfolio = [];
    $uncategorized = ['id' => null, 'name' => 'Uncategorized', 'description' => 'Ideas without a cluster', 'ideas' => []];

    $cMap = [];
    foreach ($clusters as $c) {
        $c['ideas'] = [];
        $cMap[$c['id']] = $c;
    }

    foreach ($ideas as $i) {
        if ($i['cluster_id'] && isset($cMap[$i['cluster_id']])) {
            $cMap[$i['cluster_id']]['ideas'][] = $i;
        } else {
            $uncategorized['ideas'][] = $i;
        }
    }

    foreach ($cMap as $c) $portfolio[] = $c;
    if (!empty($uncategorized['ideas'])) array_unshift($portfolio, $uncategorized);

    return $portfolio;
}

function writeResponse($pdo, $userId, $sessionId, $extra = []) {
    return array_merge([
        'success' => true,
        'session_id' => $sessionId,
        'sessions' => getSessions($pdo, $userId),
        'portfolio' => getPortfolioState($pdo, $sessionId),
    ], $extra);
}

// --- ROUTER ---
try {
    if ($action === 'get_models') {
        echo json_encode([
            'success' => true,
            'catalog' => \App\Core\AIProvider::getModelCatalog()
        ]);
        exit;
    }

    if ($action === 'load_state') {
        $sessionId = $data['session_id'] ?? null;
        $chat = [];
        $lore = '';
        $kgSelectedNodes = [];

        if ($sessionId) {
            $stmt = $pdo->prepare("SELECT id, lore, kg_selected_nodes FROM aim_chat_sessions WHERE id = ? AND user_id = ?");
            $stmt->execute([$sessionId, $userId]);
            $sess = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$sess) {
                $sessionId = null;
            } else {
                $lore = $sess['lore'] ?: '';
                $kgSelectedNodes = $sess['kg_selected_nodes'] ? json_decode($sess['kg_selected_nodes'], true) : [];
                $stmt = $pdo->prepare("SELECT role, content, operations_log FROM aim_chat_messages WHERE session_id = ? ORDER BY ts ASC");
                $stmt->execute([$sessionId]);
                $chat = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }

        echo json_encode([
            'success' => true,
            'session_id' => $sessionId,
            'sessions' => getSessions($pdo, $userId),
            'portfolio' => getPortfolioState($pdo, $sessionId),
            'settings' => [
                'lore' => $lore, 
                'model' => getUserModel($pdo, $userId), 
                'kg_selected_nodes' => $kgSelectedNodes
            ],
            'model_catalog' => \App\Core\AIProvider::getModelCatalog(),
            'chat' => $chat
        ]);
        exit;
    }

    if ($action === 'save_settings') {
        $lore = $data['lore'] ?? '';
        $model = $data['model'] ?? \App\Core\AIProvider::getDefaultModel();
        $kgNodes = isset($data['kg_selected_nodes']) ? json_encode($data['kg_selected_nodes']) : null;

        $sessionId = ensureSession($pdo, $userId, $data['session_id'] ?? null);
        if ($kgNodes !== null) {
            $pdo->prepare("UPDATE aim_chat_sessions SET lore = ?, kg_selected_nodes = ? WHERE id = ? AND user_id = ?")
                ->execute([$lore, $kgNodes, $sessionId, $userId]);
        } else {
            $pdo->prepare("UPDATE aim_chat_sessions SET lore = ? WHERE id = ? AND user_id = ?")
                ->execute([$lore, $sessionId, $userId]);
        }

        $pdo->prepare("INSERT INTO aim_settings (user_id, model) VALUES (?, ?) ON DUPLICATE KEY UPDATE model = VALUES(model)")
            ->execute([$userId, $model]);

        echo json_encode(writeResponse($pdo, $userId, $sessionId));
        exit;
    }

    if ($action === 'rename_session') {
        $sessionId = $data['session_id'] ?? '';
        $title = trim($data['title'] ?? '');
        if ($sessionId && $title !== '') {
            $pdo->prepare("UPDATE aim_chat_sessions SET title = ? WHERE id = ? AND user_id = ?")
                ->execute([$title, $sessionId, $userId]);
        }
        echo json_encode(['success' => true, 'sessions' => getSessions($pdo, $userId)]);
        exit;
    }

    if ($action === 'delete_session') {
        $sessionId = $data['session_id'];
        $pdo->prepare("DELETE FROM aim_chat_sessions WHERE id = ? AND user_id = ?")->execute([$sessionId, $userId]);
        $pdo->prepare("DELETE FROM aim_chat_messages WHERE session_id = ?")->execute([$sessionId]);
        $pdo->prepare("DELETE FROM aim_ideas WHERE session_id = ?")->execute([$sessionId]);
        $pdo->prepare("DELETE FROM aim_clusters WHERE session_id = ?")->execute([$sessionId]);
        echo json_encode(['success' => true, 'sessions' => getSessions($pdo, $userId)]);
        exit;
    }

    if ($action === 'send_message') {
        $sessionId = $data['session_id'] ?? null;
        $userMsg = trim($data['message'] ?? '');
        if (!$userMsg) throw new Exception("Empty message");

        $isNewSession = false;
        if (!$sessionId) {
            $sessionId = generateUUID();
            $title = mb_substr($userMsg, 0, 40) . (strlen($userMsg) > 40 ? '...' : '');
            $stmt = $pdo->prepare("INSERT INTO aim_chat_sessions (id, user_id, title, lore) VALUES (?, ?, ?, '')");
            $stmt->execute([$sessionId, $userId, $title]);
            $isNewSession = true;
        } else {
            $stmt = $pdo->prepare("SELECT id FROM aim_chat_sessions WHERE id = ? AND user_id = ?");
            $stmt->execute([$sessionId, $userId]);
            if (!$stmt->fetchColumn()) throw new Exception("Session not found");
            $pdo->prepare("UPDATE aim_chat_sessions SET updated_at = NOW() WHERE id = ?")->execute([$sessionId]);
        }

        $msgCount = $pdo->prepare("SELECT COUNT(*) FROM aim_chat_messages WHERE session_id = ?");
        $msgCount->execute([$sessionId]);
        $isFirstMessage = ((int)$msgCount->fetchColumn()) === 0;

        $stmt = $pdo->prepare("INSERT INTO aim_chat_messages (session_id, role, content, ts) VALUES (?, 'user', ?, ?)");
        $stmt->execute([$sessionId, $userMsg, time() * 1000]);

        if ($isFirstMessage) {
            $title = mb_substr($userMsg, 0, 40) . (strlen($userMsg) > 40 ? '...' : '');
            $pdo->prepare("UPDATE aim_chat_sessions SET title = ? WHERE id = ?")->execute([$title, $sessionId]);
        }

        $stmt = $pdo->prepare("SELECT lore FROM aim_chat_sessions WHERE id = ? AND user_id = ?");
        $stmt->execute([$sessionId, $userId]);
        $lore = $stmt->fetchColumn() ?: 'No overarching lore provided yet.';

        $sysInstructions = <<<'EOT'
You are the AI Idea Manager. Your task is to brainstorm, evaluate, and formally organize narrative ideas for the user.
You directly manage a relational database of 'Clusters' (categories/folders) and 'Ideas' (the actual items).

--- DATABASE SCHEMA & RULES ---
You manipulate the database by outputting a JSON block EXACTLY formatted like this at the end of your response:
<JSON_OPEN>
{
  "message": "Your conversational response addressing the user. Talk here, nowhere else.",
  "operations": [
    {"action": "add_cluster", "name": "Cluster Title", "description": "Cluster focus"},
    {"action": "add_idea", "cluster_name": "Cluster Title", "title": "Idea Name", "description": "Details", "status": "unassigned|yes|no"},
    {"action": "update_idea", "id": 5, "status": "yes"}
  ]
}
<JSON_CLOSE>
Available operations:
1. "add_cluster": requires `name`, `description`.
2. "add_idea": requires `title`, `description`, `status`. To put it in a cluster, provide either `cluster_id` (integer) OR `cluster_name` (string). If `cluster_name` doesn't exist, it will be automatically created!
3. "update_idea": requires `id`. Optional fields: `cluster_id`, `title`, `description`, `status`.
4. "delete_idea": requires `id`.
5. "delete_cluster": requires `id`.

Rules:
- You MUST output the JSON block containing "message" and "operations". 
- If no DB changes are needed, leave "operations" as an empty array `[]`.
- All your conversational output MUST go inside the "message" key. DO NOT include text outside the JSON block.
EOT;
        $sysInstructions = str_replace('<JSON_OPEN>', '```json', $sysInstructions);
        $sysInstructions = str_replace('<JSON_CLOSE>', '```', $sysInstructions);

        $messages = [
            ['role' => 'system', 'content' => "--- STORY LORE ---\n{$lore}\n\n{$sysInstructions}"]
        ];

        $historyStmt = $pdo->prepare("SELECT role, content FROM aim_chat_messages WHERE session_id = ? ORDER BY ts ASC");
        $historyStmt->execute([$sessionId]);
        $history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

        array_pop($history);

        foreach ($history as $msg) {
            $messages[] = ['role' => $msg['role'] === 'user' ? 'user' : 'assistant', 'content' => $msg['content']];
        }

        $dbState = json_encode(getPortfolioState($pdo, $sessionId), JSON_PRETTY_PRINT);
        $finalUserPrompt = "--- CURRENT PORTFOLIO DB STATE ---\n{$dbState}\n\n--- USER MESSAGE ---\n{$userMsg}";
        $messages[] = ['role' => 'user', 'content' => $finalUserPrompt];

        $model = getUserModel($pdo, $userId);
        $aiOptions = ['temperature' => 0.7];

        try {
            $aiResponse = $spw->getAIProvider()->sendMessage($model, $messages, $aiOptions);
        } catch (Exception $e) {
            throw new Exception("AI Provider Error: " . $e->getMessage());
        }

        $messageForUser = trim($aiResponse);
        $operations = [];

        $startPos = strpos($messageForUser, '{');
        $endPos = strrpos($messageForUser, '}');

        if ($startPos !== false && $endPos !== false && $endPos > $startPos) {
            $jsonStr = substr($messageForUser, $startPos, $endPos - $startPos + 1);
            $json = json_decode($jsonStr, true);

            if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
                if (isset($json['message'])) {
                    $messageForUser = trim($json['message']);
                }
                if (isset($json['operations']) && is_array($json['operations'])) {
                    $operations = $json['operations'];
                }
            } else {
                $messageForUser = preg_replace('/```json/i', '', $messageForUser);
                $messageForUser = str_replace('```', '', $messageForUser);
            }
        } else {
            $messageForUser = preg_replace('/```json/i', '', $messageForUser);
            $messageForUser = str_replace('```', '', $messageForUser);
        }

        $messageForUser = trim($messageForUser);

        $execLog = [];
        if (!empty($operations)) {
            foreach ($operations as $op) {
                $actionName = $op['action'] ?? '';
                try {
                    if ($actionName === 'add_cluster') {
                        $stmt = $pdo->prepare("INSERT INTO aim_clusters (name, description, session_id) VALUES (?, ?, ?)");
                        $stmt->execute([$op['name'] ?? 'Unnamed', $op['description'] ?? '', $sessionId]);
                        $execLog[] = "Created cluster: {$op['name']}";
                    }
                    elseif ($actionName === 'add_idea') {
                        $cId = null;
                        if (!empty($op['cluster_id'])) {
                            $cId = (int)$op['cluster_id'];
                        } elseif (!empty($op['cluster_name'])) {
                            $stmt = $pdo->prepare("SELECT id FROM aim_clusters WHERE name = ? AND session_id = ?");
                            $stmt->execute([$op['cluster_name'], $sessionId]);
                            $cId = $stmt->fetchColumn();
                            if (!$cId) {
                                $pdo->prepare("INSERT INTO aim_clusters (name, description, session_id) VALUES (?, '', ?)")
                                    ->execute([$op['cluster_name'], $sessionId]);
                                $cId = $pdo->lastInsertId();
                            }
                        }

                        $stmt = $pdo->prepare("INSERT INTO aim_ideas (cluster_id, title, description, status, session_id) VALUES (?, ?, ?, ?, ?)");
                        $status = in_array($op['status'] ?? '', ['yes','no','unassigned']) ? $op['status'] : 'unassigned';
                        $stmt->execute([$cId, $op['title'] ?? 'Untitled', $op['description'] ?? '', $status, $sessionId]);
                        $execLog[] = "Created idea: {$op['title']}";
                    }
                    elseif ($actionName === 'update_idea') {
                        if (empty($op['id'])) continue;
                        $id = (int)$op['id'];

                        $chk = $pdo->prepare("SELECT id FROM aim_ideas WHERE id = ? AND session_id = ?");
                        $chk->execute([$id, $sessionId]);
                        if (!$chk->fetchColumn()) continue;

                        $updates = []; $params = [];
                        if (isset($op['title'])) { $updates[] = "title = ?"; $params[] = $op['title']; }
                        if (isset($op['description'])) { $updates[] = "description = ?"; $params[] = $op['description']; }
                        if (isset($op['status']) && in_array($op['status'], ['yes','no','unassigned'])) { $updates[] = "status = ?"; $params[] = $op['status']; }
                        if (array_key_exists('cluster_id', $op)) { $updates[] = "cluster_id = ?"; $params[] = $op['cluster_id'] ? (int)$op['cluster_id'] : null; }

                        if (!empty($updates)) {
                            $params[] = $id;
                            $pdo->prepare("UPDATE aim_ideas SET " . implode(', ', $updates) . " WHERE id = ?")->execute($params);
                            $execLog[] = "Updated idea #{$id}";
                        }
                    }
                    elseif ($actionName === 'delete_idea') {
                        if (empty($op['id'])) continue;
                        $pdo->prepare("DELETE FROM aim_ideas WHERE id = ? AND session_id = ?")->execute([(int)$op['id'], $sessionId]);
                        $execLog[] = "Deleted idea #{$op['id']}";
                    }
                    elseif ($actionName === 'delete_cluster') {
                        if (empty($op['id'])) continue;
                        $cid = (int)$op['id'];
                        $pdo->prepare("UPDATE aim_ideas SET cluster_id = NULL WHERE cluster_id = ? AND session_id = ?")->execute([$cid, $sessionId]);
                        $pdo->prepare("DELETE FROM aim_clusters WHERE id = ? AND session_id = ?")->execute([$cid, $sessionId]);
                        $execLog[] = "Deleted cluster #{$cid}";
                    }
                } catch (\Throwable $t) {
                    $execLog[] = "Failed '{$actionName}': " . $t->getMessage();
                }
            }
        }

        $logJson = !empty($execLog) ? json_encode($execLog) : null;

        $stmt = $pdo->prepare("INSERT INTO aim_chat_messages (session_id, role, content, operations_log, ts) VALUES (?, 'ai', ?, ?, ?)");
        $stmt->execute([$sessionId, $messageForUser, $logJson, time() * 1000]);

        echo json_encode(writeResponse($pdo, $userId, $sessionId, [
            'ai_text' => $messageForUser,
            'operations_log' => $logJson,
        ]));
        exit;
    }

    // ══════════════════════════════════════════════════════════════════════
    // MANUAL LAYER — session-scoped, mirrors old idea_manager patterns
    // ══════════════════════════════════════════════════════════════════════

    if ($action === 'save_cluster') {
        $sessionId = ensureSession($pdo, $userId, $data['session_id'] ?? null);
        $id = (int)($data['id'] ?? 0);
        $name = trim($data['name'] ?? '');
        $desc = trim($data['description'] ?? '');
        if (!$name) throw new Exception('Name is required');

        if ($id > 0) {
            $pdo->prepare("UPDATE aim_clusters SET name = ?, description = ? WHERE id = ? AND session_id = ?")
                ->execute([$name, $desc, $id, $sessionId]);
        } else {
            $pdo->prepare("INSERT INTO aim_clusters (name, description, session_id) VALUES (?, ?, ?)")
                ->execute([$name, $desc, $sessionId]);
        }
        echo json_encode(writeResponse($pdo, $userId, $sessionId));
        exit;
    }

    if ($action === 'create_item') {
        $sessionId = ensureSession($pdo, $userId, $data['session_id'] ?? null);
        $clusterId = (isset($data['cluster_id']) && $data['cluster_id'] !== '' && $data['cluster_id'] !== null) ? (int)$data['cluster_id'] : null;
        $title = trim($data['title'] ?? 'New Idea');
        $desc = trim($data['description'] ?? '');
        $status = in_array($data['status'] ?? '', ['yes','no','unassigned']) ? $data['status'] : 'unassigned';

        $pdo->prepare("INSERT INTO aim_ideas (title, description, cluster_id, status, session_id) VALUES (?, ?, ?, ?, ?)")
            ->execute([$title, $desc, $clusterId, $status, $sessionId]);
        $newId = (int)$pdo->lastInsertId();

        echo json_encode(writeResponse($pdo, $userId, $sessionId, ['id' => $newId]));
        exit;
    }

    if ($action === 'update_item') {
        $sessionId = ensureSession($pdo, $userId, $data['session_id'] ?? null);
        $id = (int)($data['id'] ?? 0);
        if (!$id) throw new Exception('Missing item id');

        $chk = $pdo->prepare("SELECT id FROM aim_ideas WHERE id = ? AND session_id = ?");
        $chk->execute([$id, $sessionId]);
        if (!$chk->fetchColumn()) throw new Exception('Item not found in this project');

        if (array_key_exists('status', $data)) {
            $status = in_array($data['status'], ['yes','no','unassigned']) ? $data['status'] : 'unassigned';
            $pdo->prepare("UPDATE aim_ideas SET status = ? WHERE id = ?")->execute([$status, $id]);
        }
        if (array_key_exists('cluster_id', $data)) {
            $cId = ($data['cluster_id'] === '' || $data['cluster_id'] === null) ? null : (int)$data['cluster_id'];
            $pdo->prepare("UPDATE aim_ideas SET cluster_id = ? WHERE id = ?")->execute([$cId, $id]);
        }
        if (array_key_exists('title', $data)) {
            $title = trim($data['title']);
            if ($title !== '') {
                $pdo->prepare("UPDATE aim_ideas SET title = ? WHERE id = ?")->execute([$title, $id]);
            }
        }
        if (array_key_exists('description', $data)) {
            $desc = trim($data['description']);
            $pdo->prepare("UPDATE aim_ideas SET description = ? WHERE id = ?")->execute([$desc, $id]);
        }
        echo json_encode(writeResponse($pdo, $userId, $sessionId));
        exit;
    }

    if ($action === 'delete_ideas_batch') {
        $sessionId = ensureSession($pdo, $userId, $data['session_id'] ?? null);
        $statuses = $data['statuses'] ?? [];
        if (!is_array($statuses) || empty($statuses)) throw new Exception('Select at least one status.');
        $allowed = ['yes','no','unassigned'];
        $statuses = array_values(array_intersect($statuses, $allowed));
        if (empty($statuses)) throw new Exception('Invalid status selection.');

        $where = ["session_id = ?"];
        $params = [$sessionId];
        $inP = implode(',', array_fill(0, count($statuses), '?'));
        $where[] = "status IN ($inP)";
        $params = array_merge($params, $statuses);

        $clusters = $data['clusters'] ?? null;
        $legacyScope = $data['cluster_scope'] ?? null;

        if (is_array($clusters)) {
            if (!in_array('all', $clusters, true)) {
                $numericIds = [];
                $hasUncat = false;
                foreach ($clusters as $c) {
                    if ($c === 'unassigned' || $c === 'uncat') $hasUncat = true;
                    elseif (is_numeric($c)) $numericIds[] = (int)$c;
                }
                $clusterParts = [];
                $clusterParams = [];
                if ($hasUncat) $clusterParts[] = "cluster_id IS NULL";
                if (!empty($numericIds)) {
                    $in = implode(',', array_fill(0, count($numericIds), '?'));
                    $clusterParts[] = "cluster_id IN ($in)";
                    foreach ($numericIds as $nid) $clusterParams[] = $nid;
                }
                if (empty($clusterParts)) {
                    $where[] = "0=1";
                } else {
                    $where[] = '(' . implode(' OR ', $clusterParts) . ')';
                    $params = array_merge($params, $clusterParams);
                }
            }
        } elseif ($legacyScope === 'unassigned') {
            $where[] = "cluster_id IS NULL";
        } elseif ($legacyScope !== 'all' && $legacyScope !== null && is_numeric($legacyScope)) {
            $where[] = "cluster_id = ?";
            $params[] = (int)$legacyScope;
        }

        $whereClause = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM aim_ideas WHERE $whereClause");
        $countStmt->execute($params);
        $matched = (int)$countStmt->fetchColumn();

        if ($matched === 0) {
            echo json_encode(writeResponse($pdo, $userId, $sessionId, ['count' => 0, 'message' => 'No items matched.']));
            exit;
        }
        $pdo->prepare("DELETE FROM aim_ideas WHERE $whereClause")->execute($params);
        echo json_encode(writeResponse($pdo, $userId, $sessionId, ['count' => $matched, 'message' => "Deleted $matched ideas."]));
        exit;
    }

    if ($action === 'truncate_table') {
        $sessionId = ensureSession($pdo, $userId, $data['session_id'] ?? null);
        $target = $data['target'] ?? '';
        if ($target === 'ideas') {
            $pdo->prepare("DELETE FROM aim_ideas WHERE session_id = ?")->execute([$sessionId]);
            echo json_encode(writeResponse($pdo, $userId, $sessionId, ['message' => 'Ideas cleared.']));
            exit;
        } elseif ($target === 'clusters') {
            $pdo->prepare("UPDATE aim_ideas SET cluster_id = NULL WHERE session_id = ?")->execute([$sessionId]);
            $pdo->prepare("DELETE FROM aim_clusters WHERE session_id = ?")->execute([$sessionId]);
            echo json_encode(writeResponse($pdo, $userId, $sessionId, ['message' => 'Clusters cleared (ideas unassigned).']));
            exit;
        } elseif ($target === 'both') {
            $pdo->prepare("DELETE FROM aim_ideas WHERE session_id = ?")->execute([$sessionId]);
            $pdo->prepare("DELETE FROM aim_clusters WHERE session_id = ?")->execute([$sessionId]);
            echo json_encode(writeResponse($pdo, $userId, $sessionId, ['message' => 'Both cleared.']));
            exit;
        }
        throw new Exception('Invalid truncate target.');
    }

    if ($action === 'export_json') {
        $sessionId = $data['session_id'] ?? null;
        if (!$sessionId) throw new Exception('No project loaded.');

        $clustersToExport = $data['clusters'] ?? [];
        $statuses = $data['statuses'] ?? ['yes','no','unassigned'];
        if (!is_array($statuses) || empty($statuses)) $statuses = ['yes','no','unassigned'];

        $allowed = ['yes','no','unassigned'];
        $statuses = array_values(array_intersect($statuses, $allowed));
        if (empty($statuses)) $statuses = $allowed;

        $exportData = [];
        $inP = implode(',', array_fill(0, count($statuses), '?'));

        foreach ($clustersToExport as $cId) {
            if ($cId === 'unassigned' || $cId === 'uncat') {
                $stmt = $pdo->prepare("SELECT title, description, status FROM aim_ideas WHERE cluster_id IS NULL AND session_id = ? AND status IN ($inP) ORDER BY id ASC");
                $stmt->execute(array_merge([$sessionId], $statuses));
                $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $exportData[] = ['cluster_name' => 'Uncategorized', 'cluster_description' => '', 'items' => $items];
            } elseif (is_numeric($cId)) {
                $stmtC = $pdo->prepare("SELECT name, description FROM aim_clusters WHERE id = ? AND session_id = ?");
                $stmtC->execute([(int)$cId, $sessionId]);
                $info = $stmtC->fetch(PDO::FETCH_ASSOC);
                if ($info) {
                    $params = array_merge([(int)$cId, $sessionId], $statuses);
                    $stmtI = $pdo->prepare("SELECT title, description, status FROM aim_ideas WHERE cluster_id = ? AND session_id = ? AND status IN ($inP) ORDER BY id ASC");
                    $stmtI->execute($params);
                    $info['items'] = $stmtI->fetchAll(PDO::FETCH_ASSOC);
                    $exportData[] = $info;
                }
            }
        }

        $filename = "aim_export_" . date('Ymd_His') . ".json";
        echo json_encode(['success' => true, 'filename' => $filename, 'data' => $exportData]);
        exit;
    }

    if ($action === 'ingest_json') {
        $sessionId = ensureSession($pdo, $userId, $data['session_id'] ?? null);
        $json = $data['json_data'] ?? '';
        $parsed = json_decode($json, true);
        if (!is_array($parsed)) throw new Exception('Invalid JSON string.');

        $normalizedItems = [];

        if (isset($parsed[0]) && is_array($parsed[0]) && (isset($parsed[0]['items']) || isset($parsed[0]['cluster_name']))) {
            foreach ($parsed as $cluster) {
                $cName = $cluster['cluster_name'] ?? null;
                $cDesc = $cluster['cluster_description'] ?? '';
                $items = $cluster['items'] ?? [];
                if (is_array($items)) {
                    foreach ($items as $it) {
                        $normalizedItems[] = [
                            'title' => $it['title'] ?? 'Untitled',
                            'description' => $it['description'] ?? '',
                            'status' => $it['status'] ?? 'unassigned',
                            'cluster_name' => $cName,
                            'cluster_description' => $cDesc
                        ];
                    }
                }
            }
        }
        elseif (isset($parsed['items']) && is_array($parsed['items'])) {
            $normalizedItems = $parsed['items'];
        }
        elseif (isset($parsed[0]) && is_array($parsed[0]) && isset($parsed[0]['title'])) {
            $normalizedItems = $parsed;
        }
        else throw new Exception('Unrecognized JSON structure.');

        $inserted = 0;
        foreach ($normalizedItems as $item) {
            $title = $item['title'] ?? 'Untitled';
            $desc = $item['description'] ?? '';
            $status = in_array($item['status'] ?? '', ['yes','no','unassigned']) ? $item['status'] : 'unassigned';
            $cName = $item['cluster_name'] ?? null;
            $cDesc = $item['cluster_description'] ?? '';

            $cId = null;
            if ($cName && $cName !== 'Uncategorized') {
                $stmt = $pdo->prepare("SELECT id FROM aim_clusters WHERE name = ? AND session_id = ?");
                $stmt->execute([$cName, $sessionId]);
                $cId = $stmt->fetchColumn();
                if (!$cId) {
                    $pdo->prepare("INSERT INTO aim_clusters (name, description, session_id) VALUES (?, ?, ?)")
                        ->execute([$cName, $cDesc, $sessionId]);
                    $cId = (int)$pdo->lastInsertId();
                } elseif ($cDesc) {
                    $pdo->prepare("UPDATE aim_clusters SET description = ? WHERE id = ? AND (description IS NULL OR description = '')")->execute([$cDesc, $cId]);
                }
            }

            $pdo->prepare("INSERT INTO aim_ideas (title, description, cluster_id, status, session_id) VALUES (?, ?, ?, ?, ?)")
                ->execute([$title, $desc, $cId, $status, $sessionId]);
            $inserted++;
        }

        echo json_encode(writeResponse($pdo, $userId, $sessionId, ['message' => "Ingested $inserted items."]));
        exit;
    }

    if ($action === 'download_instructions') {
        $instructions = [
            '_comment' => 'JSON Structure Instructions for Idea Ingestion. Accepts an array of clusters or an array of items.',
            'example_clustered_format' => [
                [
                    'cluster_name' => 'Cluster Name',
                    'cluster_description' => 'Cluster Description',
                    'items' => [
                        ['title' => 'Item Title', 'description' => 'Item Description']
                    ]
                ]
            ],
            'example_flat_format' => [
                'items' => [
                    ['title' => 'Item Title', 'description' => 'Item Description', 'cluster_name' => 'Cluster Name', 'cluster_description' => 'Cluster Description']
                ]
            ]
        ];
        echo json_encode(['success' => true, 'filename' => 'aim_ingest_instructions.json', 'data' => $instructions]);
        exit;
    }

    echo json_encode(['success' => false, 'error' => 'Unknown action']);

} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


