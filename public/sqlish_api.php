<?php
// public/sqlish_api.php
// ─────────────────────────────────────────────────────────────────────────────
// SQLISH — API
// Thin HTTP layer over App\Service\SqlishEngine.
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
require_once dirname(__DIR__) . '/src/Service/GeneratorRepository.php';
require_once dirname(__DIR__) . '/src/Service/SqlishEngine.php';

use App\Service\SqlishEngine;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?: [];
$action = $_GET['action'] ?? $data['action'] ?? '';

$engine = new SqlishEngine();

try {
    switch ($action) {
        // ── Schema lookups ───────────────────────────────────────────
        case 'list_tables':
            echo json_encode(['success' => true, 'tables' => $engine->listAvailableTables()]);
            break;

        case 'list_columns':
            $table = $_GET['table'] ?? $data['table'] ?? '';
            if (!$table) throw new Exception("Table required");
            echo json_encode(['success' => true, 'columns' => $engine->listTableColumns($table)]);
            break;

        // ── Templates ────────────────────────────────────────────────
        case 'list_templates':
            $page = max(1, (int)($_GET['page'] ?? $data['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? $data['limit'] ?? 20));
            $result = $engine->listTemplatesPaged($userId, $page, $limit);
            echo json_encode(['success' => true] + $result);
            break;

        case 'get_template':
            $id = (int)($_GET['id'] ?? $data['id'] ?? 0);
            $tpl = $engine->getTemplate($userId, $id);
            echo json_encode($tpl ? ['success' => true, 'template' => $tpl] : ['error' => 'Not found']);
            break;

        case 'save_template':
            $tpl = $engine->saveTemplate($userId, $data['template'] ?? []);
            echo json_encode(['success' => true, 'template' => $tpl]);
            break;

        case 'delete_template':
            $id = (int)($data['id'] ?? 0);
            $engine->deleteTemplate($userId, $id);
            echo json_encode(['success' => true]);
            break;

        // ── Runs ─────────────────────────────────────────────────────
        case 'list_runs':
            $page = max(1, (int)($_GET['page'] ?? $data['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? $data['limit'] ?? 20));
            $result = $engine->listRunsPaged($userId, $page, $limit);
            echo json_encode(['success' => true] + $result);
            break;

        case 'get_run':
            $id = (int)($_GET['id'] ?? $data['id'] ?? 0);
            $run = $engine->getRun($userId, $id);
            echo json_encode($run ? ['success' => true, 'run' => $run] : ['error' => 'Not found']);
            break;

        case 'start_run':
            $templateId = (int)($data['template_id'] ?? 0);
            $sourceText = $data['source_text'] ?? '';
            $context = $data['context'] ?? [];
            $title = $data['title'] ?? null;
            
            $run = $engine->runOnce($userId, $templateId, $sourceText, $context, $title);
            echo json_encode(['success' => true, 'run' => $run]);
            break;

        case 'cancel_run':
            $id = (int)($data['id'] ?? 0);
            $engine->cancelRun($userId, $id);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['error' => "Unknown action '{$action}'"]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}