<?php
// public/wtf_api.php
// ─────────────────────────────────────────────────────────────────────────────
// WTF — Work The Flow — API
// Thin HTTP layer over App\Wtf\WorkflowExecutor. This file knows about
// sessions/auth/PDO wiring only — all orchestration logic lives in the
// executor, all execution behavior lives in registered handlers.
//
// v2 CHANGE: added a small set of READ-ONLY actions (list_log_files,
// fetch_log_file, get_global_locks, release_lock, cleanup_locks, heartbeat)
// that mirror the equivalent actions in scheduler_forge_api.php. These exist
// purely so the WTF header's cloned "Logs" and "Locks" buttons/modals have
// something to call without WTF needing to know anything about scheduler
// internals beyond reading the same tables/files Scheduler Forge already
// reads. Nothing about workflow/run orchestration below this comment block
// changed from the previous version.
// ─────────────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
require_once dirname(__DIR__) . '/src/Wtf/StepHandlerInterface.php';
require_once dirname(__DIR__) . '/src/Wtf/Handlers/ManualHandler.php';
require_once dirname(__DIR__) . '/src/Wtf/Handlers/NoopDelayHandler.php';
require_once dirname(__DIR__) . '/src/Wtf/Handlers/SchedulerTaskHandler.php';
require_once dirname(__DIR__) . '/src/Service/GeneratorRepository.php';
require_once dirname(__DIR__) . '/src/Service/WrishEngine.php';
require_once dirname(__DIR__) . '/src/Wtf/Handlers/WrishRunHandler.php';
require_once dirname(__DIR__) . '/src/Service/SqlishEngine.php';
require_once dirname(__DIR__) . '/src/Wtf/Handlers/SqlishRunHandler.php';
require_once dirname(__DIR__) . '/src/Service/SplashEngine.php';
require_once dirname(__DIR__) . '/src/Wtf/Handlers/SplashRunHandler.php';
require_once dirname(__DIR__) . '/src/Wtf/Handlers/SubWorkflowHandler.php';
require_once dirname(__DIR__) . '/src/Wtf/HandlerRegistry.php';
require_once dirname(__DIR__) . '/src/Wtf/WorkflowExecutor.php';
require_once dirname(__DIR__) . '/src/Core/TaskLockManager.php';

use App\Wtf\WorkflowExecutor;
use App\Core\TaskLockManager;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

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

$executor = new WorkflowExecutor();

// tailFile() — identical small helper to the one in scheduler_forge_api.php,
// duplicated here rather than shared so wtf_api.php has no path dependency
// on the /api/ subfolder or its file layout.
function wtfTailFile($filepath, $lines = 200) {
    $f = @fopen($filepath, "r");
    if (!$f) return ["[Error] Cannot open log file"];
    $buffer = '';
    $chunkSize = 4096;
    if (fseek($f, 0, SEEK_END) === -1) {
        fclose($f);
        return ["[Error] Unable to seek"];
    }
    $pos = ftell($f);
    $lineCount = 0;
    $linesInBuffer = [];
    while ($pos > 0 && $lineCount < $lines) {
        $read = min($chunkSize, $pos);
        $pos -= $read;
        if (fseek($f, $pos) === -1) break;
        $chunk = @fread($f, $read);
        if ($chunk === false) break;
        $buffer = $chunk . $buffer;
        $linesInBuffer = explode("\n", $buffer);
        $lineCount = count($linesInBuffer) - 1;
    }
    fclose($f);
    $result = array_filter(array_slice($linesInBuffer, -$lines), fn($l) => trim($l) !== '');
    return $result ?: ["[Info] Log file is empty."];
}

try {
    switch ($action) {

        // ── Workflows (templates) ────────────────────────────────────────────
        case 'list_workflows':
            $page = max(1, (int)($_GET['page'] ?? $data['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? $data['limit'] ?? 20));
            $result = $executor->listWorkflowsPaged($userId, $page, $limit);
            echo json_encode(['success' => true] + $result);
            break;

        case 'get_workflow':
            $id = (int)($_GET['id'] ?? $data['id'] ?? 0);
            $wf = $executor->getWorkflow($userId, $id);
            echo json_encode($wf ? ['success' => true, 'workflow' => $wf] : ['error' => 'Not found']);
            break;

        case 'save_workflow':
            $wf = $executor->saveWorkflow($userId, $data['workflow'] ?? []);
            echo json_encode(['success' => true, 'workflow' => $wf]);
            break;

        case 'clone_workflow':
            $id = (int)($data['id'] ?? 0);
            $orig = $executor->getWorkflow($userId, $id);
            if (!$orig) throw new Exception("Workflow not found.");
            $orig['title'] = $orig['title'] . ' (Copy)';
            $orig['id'] = null; // force insert
            $wf = $executor->saveWorkflow($userId, $orig);
            echo json_encode(['success' => true, 'workflow' => $wf]);
            break;

        case 'delete_workflow':
            $executor->deleteWorkflow($userId, (int)($data['id'] ?? 0));
            echo json_encode(['success' => true]);
            break;

        case 'available_handlers':
            $registry = new \App\Wtf\HandlerRegistry();
            echo json_encode(['success' => true, 'handlers' => $registry->availableKeys()]);
            break;

        // ── Runs ─────────────────────────────────────────────────────────────
        case 'list_runs':
            $page = max(1, (int)($_GET['page'] ?? $data['page'] ?? 1));
            $limit = max(1, (int)($_GET['limit'] ?? $data['limit'] ?? 20));
            $result = $executor->listRunsPaged($userId, $page, $limit);
            echo json_encode(['success' => true] + $result);
            break;

        case 'get_run':
            $id = (int)($_GET['id'] ?? $data['id'] ?? 0);
            $run = $executor->getRun($userId, $id);
            echo json_encode($run ? ['success' => true, 'run' => $run] : ['error' => 'Not found']);
            break;

        case 'get_run_events':
            $id = (int)($_GET['id'] ?? $data['id'] ?? 0);
            echo json_encode(['success' => true, 'events' => $executor->getRunEvents($userId, $id)]);
            break;

        case 'export_run':
            $id = (int)($_GET['id'] ?? $data['id'] ?? 0);
            $run = $executor->getRun($userId, $id);
            if (!$run) throw new Exception("Run not found.");
            $events = $executor->getRunEvents($userId, $id);
            echo json_encode(['success' => true, 'export' => [
                'run' => $run,
                'events' => $events
            ]]);
            break;

        case 'start_run':
            $run = $executor->startRun(
                $userId,
                (int)($data['workflow_id'] ?? 0),
                $data['title'] ?? null,
                $data['context'] ?? [],
                !empty($data['auto_advance'])
            );
            echo json_encode(['success' => true, 'run' => $run]);
            break;

        case 'advance_run':
            // Only ever called by the UI for a MANUAL (auto_advance=0) run,
            // via the explicit "Proceed" button. Auto-advance runs are driven
            // exclusively by the scheduler heartbeat
            // (App\Wtf\WorkflowExecutor::advanceAllAutoRuns(), called from
            // App\Core\TaskRunnerScheduler) — the frontend never calls this
            // action on a timer or automatically for those runs. This
            // endpoint itself doesn't need to enforce that distinction; it's
            // a UI-side contract (see wtf.php), and advance() is safe to call
            // idempotently regardless of who calls it.
            $run = $executor->advance($userId, (int)($data['run_id'] ?? 0));
            echo json_encode(['success' => true, 'run' => $run]);
            break;

        case 'complete_step':
            $run = $executor->completeStepManually(
                $userId,
                (int)($data['run_id'] ?? 0),
                (int)($data['step_id'] ?? 0),
                $data['note'] ?? ''
            );
            echo json_encode(['success' => true, 'run' => $run]);
            break;

        case 'skip_step':
            $run = $executor->skipStep(
                $userId,
                (int)($data['run_id'] ?? 0),
                (int)($data['step_id'] ?? 0),
                $data['reason'] ?? ''
            );
            echo json_encode(['success' => true, 'run' => $run]);
            break;

        case 'retry_step':
            $run = $executor->retryStep($userId, (int)($data['run_id'] ?? 0), (int)($data['step_id'] ?? 0));
            echo json_encode(['success' => true, 'run' => $run]);
            break;

        case 'pause_run':
            $run = $executor->pauseRun($userId, (int)($data['run_id'] ?? 0));
            echo json_encode(['success' => true, 'run' => $run]);
            break;

        case 'resume_run':
            $run = $executor->resumeRun($userId, (int)($data['run_id'] ?? 0));
            echo json_encode(['success' => true, 'run' => $run]);
            break;

        case 'cancel_run':
            $run = $executor->cancelRun($userId, (int)($data['run_id'] ?? 0));
            echo json_encode(['success' => true, 'run' => $run]);
            break;

        case 'add_note':
            $executor->addNote(
                $userId,
                (int)($data['run_id'] ?? 0),
                $data['note'] ?? '',
                isset($data['step_id']) ? (int)$data['step_id'] : null
            );
            echo json_encode(['success' => true]);
            break;

        // ── Extras ───────────────────────────────────────────────────────────
        case 'list_wrish_sessions':
            $stmt = $pdo->prepare("SELECT session_id, title FROM wrish_chat_sessions WHERE user_id = ? ORDER BY updated_at DESC");
            $stmt->execute([$userId]);
            echo json_encode(['success' => true, 'sessions' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'list_scheduler_refs':
            $tasks = $pdo->query("SELECT id, name, args FROM scheduled_tasks ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
            $wrappers = $pdo->query("SELECT id, task_id, name, fixed_args FROM task_wrappers ORDER BY display_order ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'tasks' => $tasks, 'wrappers' => $wrappers]);
            break;
            
        case 'list_sqlish_templates':
            $stmt = $pdo->prepare("SELECT id, name, target_table, operation_type FROM sqlish_op_templates WHERE user_id = ? AND is_active = 1 ORDER BY name ASC");
            $stmt->execute([$userId]);
            echo json_encode(['success' => true, 'templates' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'list_splash_templates':
            $stmt = $pdo->prepare("SELECT id, name FROM splash_templates WHERE user_id = ? AND is_active = 1 ORDER BY name ASC");
            $stmt->execute([$userId]);
            echo json_encode(['success' => true, 'templates' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        // ── Cloned Scheduler Forge — Logs viewer (read-only) ────────────────
        // Same table/file access as scheduler_forge_api.php's list_log_files /
        // fetch_log_file actions. WTF only ever reads here — no writes, no
        // deletes, no admin actions on logs.
        case 'list_log_files':
            $logsDir = dirname(__DIR__) . '/logs';
            if (!is_dir($logsDir)) {
                echo json_encode(['success' => true, 'files' => []]);
                break;
            }
            $files = array_values(array_filter(scandir($logsDir), function($f) use ($logsDir) {
                return is_file($logsDir . '/' . $f)
                    && preg_match('/\.log$/i', $f)
                    && stripos($f, 'err') === false;
            }));
            usort($files, function($a, $b) use ($logsDir) {
                return filemtime($logsDir . '/' . $b) <=> filemtime($logsDir . '/' . $a);
            });
            echo json_encode(['success' => true, 'files' => $files]);
            break;

        case 'fetch_log_file':
            $file = basename($_GET['file'] ?? $data['file'] ?? '');
            $logsDir = dirname(__DIR__) . '/logs';
            $filepath = $logsDir . '/' . $file;
            if (!$file || !is_file($filepath)) {
                echo json_encode(['success' => true, 'content' => "[Info] No valid log file found yet."]);
            } else {
                $lines = wtfTailFile($filepath, 200);
                echo json_encode(['success' => true, 'content' => implode("\n", $lines)]);
            }
            break;

        // ── Cloned Scheduler Forge — Global Locks manager ───────────────────
        // Same table access as scheduler_forge_api.php's get_global_locks /
        // release_lock / cleanup_locks actions, reusing App\Core\TaskLockManager
        // so lock semantics (owner tokens, force-release, expiry) stay
        // identical to the scheduler's own UI — no separate lock logic here.
        case 'get_global_locks':
            $stmt = $pdo->query("
                SELECT l.id, l.task_id, l.lock_key, l.acquired_at, l.expires_at, l.pid, l.hostname, l.status, l.run_id, t.name as task_name,
                TIMESTAMPDIFF(SECOND, l.acquired_at, NOW()) as age_seconds,
                TIMESTAMPDIFF(SECOND, NOW(), l.expires_at) as ttl_seconds
                FROM task_locks l
                LEFT JOIN scheduled_tasks t ON l.task_id = t.id
                WHERE l.status = 'active'
                ORDER BY l.acquired_at DESC
            ");
            $locks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'locks' => $locks]);
            break;

        case 'release_lock':
            $lockManager = new TaskLockManager();
            $lockId = (int)($data['lock_id'] ?? 0);
            $ok = $lockManager->forceReleaseLock($lockId);
            echo json_encode(['success' => (bool)$ok]);
            break;

        case 'cleanup_locks':
            $lockManager = new TaskLockManager();
            $count = $lockManager->cleanupExpiredLocks();
            echo json_encode(['success' => true, 'count' => $count]);
            break;

        case 'scheduler_heartbeat':
            $stmt = $pdo->query("SELECT last_seen FROM scheduler_heartbeat WHERE id = 1");
            $heartbeat = $stmt->fetchColumn();
            echo json_encode([
                'success' => true,
                'last_seen' => $heartbeat,
                'server_time' => gmdate('Y-m-d H:i:s'),
            ]);
            break;

        default:
            echo json_encode(['error' => "Unknown action '{$action}'"]);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}