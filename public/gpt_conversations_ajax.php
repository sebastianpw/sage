<?php

require "error_reporting.php";
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

header('Content-Type: application/json');

$search = trim($_GET['q'] ?? '');
$page   = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$result = [
    'success' => false,
    'data' => [],
    'total' => 0,
    'pages' => 0,
];

try {
    if ($search === '') {
        $stmtTotal = $pdoSys->query("SELECT COUNT(*) FROM gpt_conversations");
        $totalConversations = $stmtTotal->fetchColumn();

        $stmt = $pdoSys->prepare("SELECT * FROM gpt_conversations ORDER BY created_at DESC LIMIT :offset, :limit");
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        // Search by title or messages content_text
        $like = "%$search%";
        $stmtTotal = $pdoSys->prepare("
            SELECT COUNT(DISTINCT c.external_id) 
            FROM gpt_conversations c
            LEFT JOIN gpt_messages m 
            ON c.external_id = m.conversation_external_id
            WHERE c.title LIKE :q OR m.content_text LIKE :q
        ");
        $stmtTotal->execute([':q' => $like]);
        $totalConversations = $stmtTotal->fetchColumn();

        $stmt = $pdoSys->prepare("
            SELECT DISTINCT c.* 
            FROM gpt_conversations c
            LEFT JOIN gpt_messages m 
            ON c.external_id = m.conversation_external_id
            WHERE c.title LIKE :q OR m.content_text LIKE :q
            ORDER BY c.created_at DESC
            LIMIT :offset, :limit
        ");
        $stmt->bindValue(':q', $like, PDO::PARAM_STR);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->execute();
    }

    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result['success'] = true;
    $result['data'] = $conversations;
    $result['total'] = intval($totalConversations);
    $result['pages'] = ceil($totalConversations / $perPage);

} catch (\Exception $e) {
    $result['error'] = $e->getMessage();
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
