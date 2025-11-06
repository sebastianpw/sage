<?php
// public/style_profiles_api.php
// Consolidated API for style profiles: actions: list, load, save_json, save_db, delete, download
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$projectRoot = defined('PROJECT_ROOT') ? PROJECT_ROOT : dirname(__DIR__);
$saveDir = $projectRoot . '/data/style_profiles';
if (!is_dir($saveDir)) @mkdir($saveDir, 0755, true);

function jsonResp($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

// normalize action
$action = isset($_GET['action']) ? strtolower(trim($_GET['action'])) : (isset($_REQUEST['action']) ? strtolower(trim($_REQUEST['action'])) : null);

// handle GET download separately (binary)
if ($action === 'download') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        header($_SERVER['SERVER_PROTOCOL'] . ' 400 Bad Request');
        echo "Missing id";
        exit;
    }
    try {
        $stmt = $pdo->prepare("SELECT filename, json_payload, profile_name FROM style_profiles WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
            echo "Profile not found";
            exit;
        }

        $filename = $row['filename'];
        $filepath = $saveDir . '/' . $filename;
        $downloadName = ($row['profile_name'] ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $row['profile_name']) : 'style_profile') . '_' . $id . '.json';

        if ($filename && is_file($filepath) && is_readable($filepath)) {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;
        }

        if (!empty($row['json_payload'])) {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
            echo $row['json_payload'];
            exit;
        }

        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
        echo "No file available";
        exit;
    } catch (Exception $e) {
        if (isset($fileLogger) && is_callable([$fileLogger,'error'])) $fileLogger->error('style_profiles_api download error: '.$e->getMessage());
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
        echo "Server error";
        exit;
    }
}

// for JSON-based actions (list, load, save_json, save_db, delete)
if (!in_array($action, ['list','load','save_json','save_db','delete'])) {
    jsonResp(['status'=>'error','message'=>'Unknown action']);
}

// read input body if POST for relevant actions
$rawBody = file_get_contents('php://input');
$body = null;
if ($rawBody) {
    $body = json_decode($rawBody, true);
    // if JSON parse failed, body stays null (we'll handle it per-action)
}

// action: list
if ($action === 'list') {
    try {
        $stmt = $pdo->query("SELECT id, profile_name, filename, created_at FROM style_profiles ORDER BY created_at DESC LIMIT 500");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        jsonResp(['status'=>'ok','profiles'=>$rows]);
    } catch (Exception $e) {
        jsonResp(['status'=>'error','message'=>$e->getMessage()]);
    }
}

// action: load
if ($action === 'load') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) jsonResp(['status'=>'error','message'=>'Missing id']);
    try {
        $stmt = $pdo->prepare("
            SELECT sp.id AS profile_id, sp.profile_name, sp.created_at,
                   da.id AS axis_id, da.axis_name, da.pole_left, da.pole_right,
                   spa.value
            FROM style_profiles sp
            JOIN style_profile_axes spa ON spa.profile_id = sp.id
            JOIN design_axes da ON da.id = spa.axis_id
            WHERE sp.id = :id
            ORDER BY da.id ASC
        ");
        $stmt->execute([':id'=>$id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) jsonResp(['status'=>'error','message'=>'Profile not found or no axes saved']);

        $profile = [
            'id' => (int)$rows[0]['profile_id'],
            'profile_name' => $rows[0]['profile_name'],
            'created_at' => $rows[0]['created_at'],
            'axes' => []
        ];
        foreach ($rows as $r) {
            $profile['axes'][] = [
                'id' => (int)$r['axis_id'],
                'key' => $r['axis_name'],
                'pole_left' => $r['pole_left'],
                'pole_right' => $r['pole_right'],
                'value' => (int)$r['value']
            ];
        }
        jsonResp(['status'=>'ok','payload'=>$profile]);
    } catch (Exception $e) {
        jsonResp(['status'=>'error','message'=>$e->getMessage()]);
    }
}

// action: save_json  (client wants to save a JSON file, optionally insert DB row if insert_db flag)
if ($action === 'save_json') {
    if (!$body) jsonResp(['status'=>'error','message'=>'Empty request or invalid JSON']);
    $profileName = isset($body['profile_name']) ? trim($body['profile_name']) : null;
    $axes = isset($body['axes']) && is_array($body['axes']) ? $body['axes'] : [];
    $insertDb = isset($body['insert_db']) ? (bool)$body['insert_db'] : false;

    $payload = [
        'profile_name' => $profileName,
        'created_at' => $body['created_at'] ?? date(DATE_ATOM),
        'axes' => $axes
    ];

    $filenameSafe = $profileName ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profileName) : 'style_profile';
    $filename = $filenameSafe . '_' . date('Y-m-d_H-i-s') . '.json';
    $filepath = $saveDir . '/' . $filename;

    $written = @file_put_contents($filepath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($written === false) {
        jsonResp(['status'=>'error','message'=>'Could not write file', 'payload'=>$payload]);
    }

    if ($insertDb) {
        try {
            $stmt = $pdo->prepare("INSERT INTO style_profiles (profile_name, filename, json_payload, created_at) VALUES (:profile_name, :filename, :json_payload, :created_at)");
            $stmt->execute([
                ':profile_name' => $profileName,
                ':filename' => $filename,
                ':json_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ':created_at' => $payload['created_at']
            ]);
            $profileId = (int)$pdo->lastInsertId();
            jsonResp(['status'=>'ok','filename'=>$filename,'filepath'=>$filepath,'payload'=>$payload,'profile_id'=>$profileId]);
        } catch (Exception $e) {
            if (isset($fileLogger) && is_callable([$fileLogger,'error'])) $fileLogger->error("style_profiles_api save_json DB insert failed: ".$e->getMessage());
            // still return success for file
            jsonResp(['status'=>'ok','filename'=>$filename,'filepath'=>$filepath,'payload'=>$payload]);
        }
    } else {
        jsonResp(['status'=>'ok','filename'=>$filename,'filepath'=>$filepath,'payload'=>$payload]);
    }
}

// action: save_db (insert or update). Expects JSON body with payload.axes and optional id.
if ($action === 'save_db') {
    if (!$body) jsonResp(['status'=>'error','message'=>'Empty request or invalid JSON']);
    $profileId = isset($body['id']) ? (int)$body['id'] : 0;
    $profileName = isset($body['profile_name']) && strlen(trim($body['profile_name'])) ? trim($body['profile_name']) : null;
    $axes = isset($body['axes']) && is_array($body['axes']) ? $body['axes'] : [];

    if (count($axes) === 0) jsonResp(['status'=>'error','message'=>'No axes provided']);

    try {
        // Validate axis ids present in DB
        $axisIds = array_map(function($a){ return (int)$a['id']; }, $axes);
        $axisIds = array_values(array_unique($axisIds));
        if (count($axisIds) === 0) throw new Exception('No axis IDs');

        $placeholders = implode(',', array_fill(0, count($axisIds), '?'));
        $stmt = $pdo->prepare("SELECT id FROM design_axes WHERE id IN ($placeholders)");
        $stmt->execute($axisIds);
        $found = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
        if (count($found) !== count($axisIds)) {
            $missing = array_diff($axisIds, $found);
            throw new Exception('Unknown axis ids: ' . implode(',', $missing));
        }

        $payload = [
            'id' => $profileId > 0 ? $profileId : null,
            'profile_name' => $profileName,
            'created_at' => $body['created_at'] ?? (new DateTime('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
            'axes' => $axes
        ];

        // save file copy
        $filenameSafe = $profileName ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $profileName) : 'style_profile';
        $filename = $filenameSafe . '_' . date('Y-m-d_H-i-s') . '.json';
        $filepath = $saveDir . '/' . $filename;
        @file_put_contents($filepath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $pdo->beginTransaction();

        if ($profileId > 0) {
            // update
            $update = $pdo->prepare("UPDATE style_profiles SET profile_name = :profile_name, filename = :filename, json_payload = :json_payload WHERE id = :id");
            $update->execute([
                ':profile_name' => $profileName,
                ':filename' => $filename,
                ':json_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ':id' => $profileId
            ]);
        } else {
            // insert
            $ins = $pdo->prepare("INSERT INTO style_profiles (profile_name, filename, json_payload, created_at, created_by) VALUES (:profile_name, :filename, :json_payload, :created_at, :created_by)");
            $ins->execute([
                ':profile_name' => $profileName,
                ':filename' => $filename,
                ':json_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ':created_at' => date('Y-m-d H:i:s'),
                ':created_by' => null
            ]);
            $profileId = (int)$pdo->lastInsertId();
        }

        // upsert axes
        $insStmt = $pdo->prepare("INSERT INTO style_profile_axes (profile_id, axis_id, value) VALUES (:profile_id, :axis_id, :value)
            ON DUPLICATE KEY UPDATE value = VALUES(value)");
        foreach ($axes as $a) {
            $axisId = (int)$a['id'];
            $value = (int)$a['value'];
            if ($value < 0) $value = 0;
            if ($value > 100) $value = 100;
            $insStmt->execute([':profile_id'=>$profileId, ':axis_id'=>$axisId, ':value'=>$value]);
        }

        $pdo->commit();

        $payload['id'] = $profileId;
        jsonResp(['status'=>'ok','profile_id'=>$profileId,'filename'=>$filename,'payload'=>$payload]);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if (isset($fileLogger) && is_callable([$fileLogger,'error'])) $fileLogger->error('style_profiles_api save_db error: '.$e->getMessage());
        jsonResp(['status'=>'error','message'=>$e->getMessage()]);
    }
}

// action: delete
if ($action === 'delete') {
    if (!$body || !isset($body['id'])) jsonResp(['status'=>'error','message'=>'Missing id']);
    $id = (int)$body['id'];
    if ($id <= 0) jsonResp(['status'=>'error','message'=>'Invalid id']);

    try {
        $stmt = $pdo->prepare("SELECT filename FROM style_profiles WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $filename = $row ? $row['filename'] : null;

        $del = $pdo->prepare("DELETE FROM style_profiles WHERE id = :id");
        $del->execute([':id' => $id]);

        if ($filename) {
            $filepath = $saveDir . '/' . $filename;
            if (is_file($filepath)) @unlink($filepath);
        }

        jsonResp(['status'=>'ok']);
    } catch (Exception $e) {
        if (isset($fileLogger) && is_callable([$fileLogger,'error'])) $fileLogger->error('style_profiles_api delete error: '.$e->getMessage());
        jsonResp(['status'=>'error','message'=>$e->getMessage()]);
    }
}

jsonResp(['status'=>'error','message'=>'Unhandled action']);
