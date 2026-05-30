<?php
// public/api/scheduler_forge_api.php
// ─────────────────────────────────────────────────────────────────────────────
// SCHEDULER FORGE API
// Handles interactions between the Scheduler Forge UI and the database.
// ─────────────────────────────────────────────────────────────────────────────

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../env_locals.php';

use App\Core\TaskLockManager;

header('Content-Type: application/json');

function jsonResponse($ok, $data = null, $error = null) {
    echo json_encode(['ok' => $ok, 'data' => $data, 'error' => $error]);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method Not Allowed');
    }

    $raw = file_get_contents('php://input');
    $req = json_decode($raw, true);
    if (!$req || !isset($req['action'])) {
        throw new Exception('Invalid JSON or missing action');
    }

    $action = $req['action'];
    $lockManager = new TaskLockManager();
    global $pdo;

    switch ($action) {
        case 'list_tasks':
            // Added lock_scope to the select so we can render it in the sidebar pills
            $stmt = $pdo->query("
                SELECT id, name, script_path, last_run, active, require_lock, lock_scope, `order`, run_now 
                FROM scheduled_tasks 
                ORDER BY `order` ASC, id ASC
            ");
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $locksStmt = $pdo->query("
                SELECT task_id, COUNT(*) as c 
                FROM task_locks 
                WHERE status = 'active' AND expires_at > UTC_TIMESTAMP() 
                GROUP BY task_id
            ");
            $locksCount =[];
            foreach ($locksStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $locksCount[$row['task_id']] = (int)$row['c'];
            }
            
            foreach ($tasks as &$t) {
                $t['active_locks'] = $locksCount[$t['id']] ?? 0;
            }
            
            jsonResponse(true, $tasks);
            break;

        case 'get_task':
            $id = (int)($req['id'] ?? 0);
            $stmt = $pdo->prepare("SELECT * FROM scheduled_tasks WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$task) throw new Exception('Task not found');
            
            $locks = $lockManager->getTaskLocks($id);
            
            $runsStmt = $pdo->prepare("
                SELECT id, pid, status, started_at, finished_at, exit_code 
                FROM task_runs 
                WHERE task_id = :id 
                ORDER BY started_at DESC LIMIT 20
            ");
            $runsStmt->execute(['id' => $id]);
            $runs = $runsStmt->fetchAll(PDO::FETCH_ASSOC);

            jsonResponse(true,[
                'task' => $task,
                'locks' => $locks,
                'runs' => $runs
            ]);
            break;

        case 'save_task':
            $id = (int)($req['id'] ?? 0);
            $taskData = $req['task'] ?? [];
            
            $fields =[
                'name', 'script_path', 'args', 'schedule_time', 'schedule_interval', 
                'schedule_dow', 'active', 'max_concurrent_runs', 'lock_timeout_minutes', 
                'require_lock', 'lock_scope', 'order'
            ];
            
            $data =[];
            foreach ($fields as $f) {
                if (array_key_exists($f, $taskData)) {
                    $val = $taskData[$f];
                    if ($val === '') $val = null;
                    if (in_array($f, ['active', 'require_lock'])) $val = $val ? 1 : 0;
                    $data[$f] = $val;
                }
            }
            
            if ($id > 0) {
                $set =[];
                foreach ($data as $k => $v) {
                    $set[] = "`$k` = :$k";
                }
                $sql = "UPDATE scheduled_tasks SET " . implode(', ', $set) . " WHERE id = :id";
                $data['id'] = $id;
                $stmt = $pdo->prepare($sql);
                $stmt->execute($data);
                jsonResponse(true,['id' => $id]);
            } else {
                $cols = array_keys($data);
                $vals = array_map(fn($c) => ":$c", $cols);
                $sql = "INSERT INTO scheduled_tasks (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ")";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($data);
                jsonResponse(true,['id' => $pdo->lastInsertId()]);
            }
            break;

        case 'delete_task':
            $id = (int)($req['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM scheduled_tasks WHERE id = :id");
            $stmt->execute(['id' => $id]);
            jsonResponse(true);
            break;

        case 'copy_task':
            $id = (int)($req['id'] ?? 0);
            $stmt = $pdo->prepare("
                SELECT name, script_path, args, schedule_time, schedule_interval, 
                       schedule_dow, max_concurrent_runs, lock_timeout_minutes, 
                       require_lock, lock_scope, `order` 
                FROM scheduled_tasks WHERE id = :id
            ");
            $stmt->execute(['id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) throw new Exception('Task not found');
            
            $row['name'] = 'Copy of ' . $row['name'];
            $cols = array_keys($row);
            $vals = array_map(fn($c) => ":$c", $cols);
            
            $insertStmt = $pdo->prepare("INSERT INTO scheduled_tasks (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $vals) . ")");
            $insertStmt->execute($row);
            jsonResponse(true,['new_id' => $pdo->lastInsertId()]);
            break;

        case 'toggle_task':
            $id = (int)($req['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE scheduled_tasks SET active = NOT active WHERE id = :id");
            $stmt->execute(['id' => $id]);
            
            $stmt = $pdo->prepare("SELECT active FROM scheduled_tasks WHERE id = :id");
            $stmt->execute(['id' => $id]);
            $active = (int)$stmt->fetchColumn();
            jsonResponse(true,['active' => $active]);
            break;

        case 'run_task':
            $id = (int)($req['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE scheduled_tasks SET run_now = 1 WHERE id = :id");
            $stmt->execute(['id' => $id]);
            jsonResponse(true);
            break;

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
            jsonResponse(true, $locks);
            break;

        case 'release_lock':
            $lockId = (int)($req['lock_id'] ?? 0);
            $success = $lockManager->releaseLock($lockId, true);
            if ($success) {
                jsonResponse(true);
            } else {
                jsonResponse(false, null, 'Failed to release lock');
            }
            break;

        case 'cleanup_locks':
            $count = $lockManager->cleanupExpiredLocks();
            jsonResponse(true,['count' => $count]);
            break;
            
        case 'heartbeat':
            $stmt = $pdo->query("SELECT last_seen FROM scheduler_heartbeat WHERE id = 1");
            $heartbeat = $stmt->fetchColumn();
            jsonResponse(true,[
                'last_seen' => $heartbeat,
                'server_time' => gmdate('Y-m-d H:i:s')
            ]);
            break;

        case 'get_log':
            $runId = (int)($req['run_id'] ?? 0);
            $type = $req['type'] ?? 'stdout';
            $stmt = $pdo->prepare("SELECT stdout_log, stderr_log FROM task_runs WHERE id = :id");
            $stmt->execute(['id' => $runId]);
            $run = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$run) throw new Exception('Run not found');
            
            $file = $type === 'stderr' ? $run['stderr_log'] : $run['stdout_log'];
            if (!$file || !file_exists($file)) {
                jsonResponse(true,['content' => "No log file found or missing: " . basename((string)$file)]);
            } else {
                $lines = tailFile($file, 200);
                jsonResponse(true,['content' => implode("\n", $lines)]);
            }
            break;

        case 'list_log_files':
            $logsDir = __DIR__ . '/../../logs';
            if (!is_dir($logsDir)) {
                jsonResponse(true,[]);
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
            jsonResponse(true, $files);
            break;

        case 'fetch_log_file':
            $file = basename($req['file'] ?? '');
            $logsDir = __DIR__ . '/../../logs';
            $filepath = $logsDir . '/' . $file;
            if (!$file || !is_file($filepath)) {
                jsonResponse(true,['content' => "[Info] No valid log file found yet."]);
            } else {
                $lines = tailFile($filepath, 200);
                jsonResponse(true,['content' => implode("\n", $lines)]);
            }
            break;

        case 'fetch_queue':
            $page = max(1, (int)($req['page'] ?? 1));
            $limit = max(1, (int)($req['limit'] ?? 50));
            $isArchive = !empty($req['archive']);
            $offset = ($page - 1) * $limit;
            
            $table = $isArchive ? 'map_run_queue_archive' : 'map_run_queue';
            
            // Safety check for table existence
            $tableExists = $pdo->query("SHOW TABLES LIKE '$table'")->rowCount() > 0;
            if (!$tableExists) {
                jsonResponse(true,['rows' => [], 'total' => 0, 'page' => 1, 'limit' => $limit, 'total_pages' => 1, 'pending_count' => 0]);
                break;
            }

            $countStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
            $total = (int)$countStmt->fetchColumn();
            
            $stmt = $pdo->prepare("
                SELECT id, map_run_id, entity_type, entity_id, asset_type, asset_id, status, priority, attempts, max_attempts, api_provider_config, error_msg, created_at 
                FROM `$table` 
                ORDER BY " . ($isArchive ? "id DESC" : "priority DESC, id DESC") . "
                LIMIT :limit OFFSET :offset
            ");
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $pendingCount = 0;
            $pendingByEntity = [];
            if (!$isArchive) {
                $pendingStmt = $pdo->query("SELECT COUNT(*) FROM `map_run_queue` WHERE status = 'pending'");
                $pendingCount = (int)$pendingStmt->fetchColumn();
                $byEntityStmt = $pdo->query("SELECT entity_type, COUNT(*) as cnt FROM `map_run_queue` WHERE status = 'pending' GROUP BY entity_type ORDER BY cnt DESC");
                $pendingByEntity = $byEntityStmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            jsonResponse(true,[
                'rows' => $rows,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => max(1, ceil($total / $limit)),
                'pending_count' => $pendingCount,
                'pending_by_entity' => $pendingByEntity
            ]);
            break;

        case 'archive_completed_queue':
            try {
                $pdo->beginTransaction();
                $insertSql = "INSERT IGNORE INTO map_run_queue_archive 
                    (id, map_run_id, entity_type, entity_id, asset_type, asset_id, status, priority, attempts, max_attempts, api_provider_config, error_msg, created_at, started_at, completed_at, archived_at)
                    SELECT id, map_run_id, entity_type, entity_id, asset_type, asset_id, status, priority, attempts, max_attempts, api_provider_config, error_msg, created_at, started_at, completed_at, NOW()
                    FROM map_run_queue
                    WHERE status = 'completed'";
                $pdo->exec($insertSql);
                
                $count = $pdo->exec("DELETE FROM map_run_queue WHERE status = 'completed'");
                $pdo->commit();
                jsonResponse(true,['count' => $count]);
            } catch (Exception $ex) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $ex;
            }
            break;

        case 'cancel_queue_items':
            // Marks selected pending items as cancelled and moves them to the archive table.
            // Only items with status = 'pending' are affected; others in the id list are silently skipped.
            $ids = array_filter(array_map('intval', $req['ids'] ?? []), fn($id) => $id > 0);
            if (empty($ids)) {
                jsonResponse(false, null, 'No valid IDs provided');
                break;
            }
            try {
                $pdo->beginTransaction();
                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                // Copy pending rows into archive with status = 'cancelled'
                $insertSql = "INSERT IGNORE INTO map_run_queue_archive
                    (id, map_run_id, entity_type, entity_id, asset_type, asset_id, status, priority, attempts, max_attempts, api_provider_config, error_msg, created_at, started_at, completed_at, archived_at)
                    SELECT id, map_run_id, entity_type, entity_id, asset_type, asset_id, 'cancelled', priority, attempts, max_attempts, api_provider_config, error_msg, created_at, started_at, completed_at, NOW()
                    FROM map_run_queue
                    WHERE id IN ($placeholders) AND status = 'pending'";
                $ins = $pdo->prepare($insertSql);
                $ins->execute($ids);

                // Delete the pending rows from the live queue
                $delSql = "DELETE FROM map_run_queue WHERE id IN ($placeholders) AND status = 'pending'";
                $del = $pdo->prepare($delSql);
                $del->execute($ids);
                $count = $del->rowCount();

                $pdo->commit();
                jsonResponse(true, ['count' => $count]);
            } catch (Exception $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $ex;
            }
            break;

        case 'uncancel_queue_items':
            // Restores selected cancelled items from the archive back to the live queue as pending.
            // Only items with status = 'cancelled' are affected; finished/failed rows are silently skipped.
            $ids = array_filter(array_map('intval', $req['ids'] ?? []), fn($id) => $id > 0);
            if (empty($ids)) {
                jsonResponse(false, null, 'No valid IDs provided');
                break;
            }
            try {
                $pdo->beginTransaction();
                $placeholders = implode(',', array_fill(0, count($ids), '?'));

                // Re-insert into live queue with status = 'pending'
                $insertSql = "INSERT IGNORE INTO map_run_queue
                    (id, map_run_id, entity_type, entity_id, asset_type, asset_id, status, priority, attempts, max_attempts, api_provider_config, error_msg, created_at, started_at, completed_at)
                    SELECT id, map_run_id, entity_type, entity_id, asset_type, asset_id, 'pending', priority, attempts, max_attempts, api_provider_config, error_msg, created_at, NULL, NULL
                    FROM map_run_queue_archive
                    WHERE id IN ($placeholders) AND status = 'cancelled'";
                $ins = $pdo->prepare($insertSql);
                $ins->execute($ids);
                $count = $ins->rowCount();

                // Remove restored rows from archive
                $delSql = "DELETE FROM map_run_queue_archive WHERE id IN ($placeholders) AND status = 'cancelled'";
                $del = $pdo->prepare($delSql);
                $del->execute($ids);

                $pdo->commit();
                jsonResponse(true, ['count' => $count]);
            } catch (Exception $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $ex;
            }
            break;

        case 'toggle_queue_priority':
            // Inverts priority between 0 and 1 for the given live queue item IDs.
            $ids = array_filter(array_map('intval', $req['ids'] ?? []), fn($id) => $id > 0);
            if (empty($ids)) {
                jsonResponse(false, null, 'No valid IDs provided');
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $upd = $pdo->prepare("UPDATE map_run_queue SET priority = CASE WHEN priority = 0 THEN 1 ELSE 0 END WHERE id IN ($placeholders)");
            $upd->execute($ids);
            jsonResponse(true, ['count' => $upd->rowCount()]);
            break;

        case 'delete_failed_queue_items':
            // Hard-deletes selected failed items from the live queue.
            // Only rows with status = 'failed' are affected.
            $ids = array_filter(array_map('intval', $req['ids'] ?? []), fn($id) => $id > 0);
            if (empty($ids)) {
                jsonResponse(false, null, 'No valid IDs provided');
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $del = $pdo->prepare("DELETE FROM map_run_queue WHERE id IN ($placeholders) AND status = 'failed'");
            $del->execute($ids);
            jsonResponse(true, ['count' => $del->rowCount()]);
            break;

        case 'reset_failed_queue_items':
            // Resets selected failed items: clears attempts to 0 and sets status back to pending.
            // Only rows with status = 'failed' are affected.
            $ids = array_filter(array_map('intval', $req['ids'] ?? []), fn($id) => $id > 0);
            if (empty($ids)) {
                jsonResponse(false, null, 'No valid IDs provided');
                break;
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $upd = $pdo->prepare("UPDATE map_run_queue SET status = 'pending', attempts = 0, error_msg = NULL, started_at = NULL, completed_at = NULL WHERE id IN ($placeholders) AND status = 'failed'");
            $upd->execute($ids);
            jsonResponse(true, ['count' => $upd->rowCount()]);
            break;

        case 'fetch_provider_config':
            // Returns all enabled endpoints + current scope defaults.
            // Used by the Provider Panel in view_queue.php.
            $endpoints = [];
            $stmt = $pdo->query(
                "SELECT id, endpoint_code, provider_name, base_url, path_template,
                        http_method, url_mode, is_enabled, notes
                 FROM worker_img_api_endpoint
                 WHERE is_enabled = 1
                 ORDER BY provider_name ASC, endpoint_code ASC"
            );
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $endpoints[] = [
                    'id'            => (int)$row['id'],
                    'endpoint_code' => $row['endpoint_code'],
                    'provider_name' => $row['provider_name'],
                    'base_url'      => $row['base_url'],
                    'path_template' => $row['path_template'],
                    'http_method'   => $row['http_method'],
                    'url_mode'      => $row['url_mode'],
                    'notes'         => $row['notes'],
                ];
            }

            $scopes = [];
            $stmt2 = $pdo->query(
                "SELECT d.id, d.scope, d.endpoint_id, d.model_override,
                        d.width_override, d.height_override, d.notes,
                        e.endpoint_code, e.provider_name
                 FROM worker_img_provider_default d
                 JOIN worker_img_api_endpoint e ON e.id = d.endpoint_id
                 ORDER BY d.scope ASC"
            );
            foreach ($stmt2->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $scopes[] = [
                    'id'             => (int)$row['id'],
                    'scope'          => $row['scope'],
                    'endpoint_id'    => (int)$row['endpoint_id'],
                    'model_override' => $row['model_override'],
                    'width_override' => $row['width_override']  ? (int)$row['width_override']  : null,
                    'height_override'=> $row['height_override'] ? (int)$row['height_override'] : null,
                    'notes'          => $row['notes'],
                    'endpoint_code'  => $row['endpoint_code'],
                    'provider_name'  => $row['provider_name'],
                ];
            }

            jsonResponse(true, ['endpoints' => $endpoints, 'scopes' => $scopes]);
            break;

        case 'save_provider_default':
            // Upserts a scope row in worker_img_provider_default.
            $scope           = trim($req['scope']           ?? '');
            $endpoint_id     = (int)($req['endpoint_id']    ?? 0);
            $model_override  = trim($req['model_override']  ?? '') ?: null;
            $width_override  = (int)($req['width_override'] ?? 0) ?: null;
            $height_override = (int)($req['height_override']?? 0) ?: null;

            if (!$scope || !$endpoint_id) {
                jsonResponse(false, null, 'scope and endpoint_id required');
                break;
            }

            $stmt = $pdo->prepare(
                "INSERT INTO worker_img_provider_default
                    (scope, endpoint_id, model_override, width_override, height_override)
                 VALUES (:scope, :ep, :model, :w, :h)
                 ON DUPLICATE KEY UPDATE
                    endpoint_id     = VALUES(endpoint_id),
                    model_override  = VALUES(model_override),
                    width_override  = VALUES(width_override),
                    height_override = VALUES(height_override)"
            );
            $stmt->execute([
                ':scope' => $scope,
                ':ep'    => $endpoint_id,
                ':model' => $model_override,
                ':w'     => $width_override,
                ':h'     => $height_override,
            ]);

            // Return the updated row
            $row = $pdo->query(
                "SELECT d.*, e.endpoint_code, e.provider_name
                 FROM worker_img_provider_default d
                 JOIN worker_img_api_endpoint e ON e.id = d.endpoint_id
                 WHERE d.scope = " . $pdo->quote($scope) . " LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);

            jsonResponse(true, [
                'scope'          => $row['scope'],
                'endpoint_id'    => (int)$row['endpoint_id'],
                'model_override' => $row['model_override'],
                'width_override' => $row['width_override']  ? (int)$row['width_override']  : null,
                'height_override'=> $row['height_override'] ? (int)$row['height_override'] : null,
                'endpoint_code'  => $row['endpoint_code'],
                'provider_name'  => $row['provider_name'],
            ]);
            break;

        case 'set_queue_item_provider':
            // Updates api_provider_config on a single pending/failed queue row.
            // Accepts { id: N, api_provider_config: {...}|null }
            $id     = (int)($req['id'] ?? 0);
            $config = $req['api_provider_config'] ?? null;

            if (!$id) {
                jsonResponse(false, null, 'id required');
                break;
            }

            // Only allow editing pending or failed rows
            $check = $pdo->prepare(
                "SELECT id, status, api_provider_config FROM map_run_queue
                 WHERE id = :id AND status IN ('pending','failed') LIMIT 1"
            );
            $check->execute([':id' => $id]);
            $row = $check->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                jsonResponse(false, null, 'Row not found or not editable');
                break;
            }

            if ($config === null) {
                $newJson = null;
            } else {
                // Merge: preserve existing task-level keys (limit, offset, no_styles, add_to_prompt)
                // and replace/set the provider sub-key.
                try {
                    $existing = $row['api_provider_config'] ? json_decode($row['api_provider_config'], true) : [];
                } catch (Exception $e) {
                    $existing = [];
                }
                if (!is_array($existing)) $existing = [];

                if (is_array($config)) {
                    // Caller sends the full merged config
                    $merged = $config;
                } else {
                    $merged = $existing;
                    $merged['provider'] = $config;
                }
                $newJson = json_encode($merged, JSON_UNESCAPED_UNICODE);
            }

            $upd = $pdo->prepare(
                "UPDATE map_run_queue SET api_provider_config = :cfg WHERE id = :id"
            );
            $upd->execute([':cfg' => $newJson, ':id' => $id]);

            jsonResponse(true, ['id' => $id, 'api_provider_config' => $newJson]);
            break;

        default:
            throw new Exception('Unknown action');
    }
} catch (Exception $e) {
    jsonResponse(false, null, $e->getMessage());
}

function tailFile($filepath, $lines = 100) {
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
    $linesInBuffer =[];
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