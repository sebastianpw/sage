<?php
// public/style_profiles_api.php
// Consolidated API for style profiles: actions: list, load, save_json, save_db, delete, download, get_config, save_config, save_convert_result
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
        $stmt = $pdo->prepare("SELECT filename, json_payload, name FROM style_profiles WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
            echo "Profile not found";
            exit;
        }

        $filename = $row['filename'];
        $filepath = $saveDir . '/' . $filename;
        $downloadName = ($row['name'] ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $row['name']) : 'style_profile') . '_' . $id . '.json';

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

// for JSON-based actions
if (!in_array($action, ['list','load','save_json','save_db','delete','convert_proxy','get_config','save_config','get_generator_configs', 'save_convert_result'])) {
    jsonResp(['status'=>'error','message'=>'Unknown action']);
}

// read input body if POST for relevant actions
$rawBody = file_get_contents('php://input');
$body = null;
if ($rawBody) {
    $body = json_decode($rawBody, true);
}

// action: get_config - retrieve generator config IDs
if ($action === 'get_config') {
    try {

        
        
        
        $stmt = $pdo->query("SELECT config_key, config_value FROM style_profile_config WHERE config_key IN ('axes_generator_config_id', 'polish_generator_config_id')");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        
        
        
        $config = [];
        foreach ($rows as $r) {
            $config[$r['config_key']] = $r['config_value'];
        }
        jsonResp(['status'=>'ok','config'=>$config]);
        
        

    } catch (Exception $e) {
        jsonResp(['status'=>'error','message'=>$e->getMessage()]);
    }
}

// action: save_config - update generator config IDs
if ($action === 'save_config') {
    if (!$body) jsonResp(['status'=>'error','message'=>'Empty request or invalid JSON']);
    try {
        $pdo->beginTransaction();
        
        if (isset($body['axes_generator_config_id'])) {
            $stmt = $pdo->prepare("INSERT INTO style_profile_config (config_key, config_value) VALUES ('axes_generator_config_id', :val) ON DUPLICATE KEY UPDATE config_value = :val");
            $stmt->execute([':val' => $body['axes_generator_config_id']]);
        }
        
        if (isset($body['polish_generator_config_id'])) {
            $stmt = $pdo->prepare("INSERT INTO style_profile_config (config_key, config_value) VALUES ('polish_generator_config_id', :val) ON DUPLICATE KEY UPDATE config_value = :val");
            $stmt->execute([':val' => $body['polish_generator_config_id']]);
        }
        
        $pdo->commit();
        jsonResp(['status'=>'ok']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        jsonResp(['status'=>'error','message'=>$e->getMessage()]);
    }
}

// action: get_generator_configs - list all available generator configs for dropdown
if ($action === 'get_generator_configs') {
    try {
        
        
        /*
        $stmt = $pdo->query("SELECT config_id, title FROM generator_config WHERE active = 1 ORDER BY title ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        */
        
        
        
        
        
              

// Assume $pdo is your configured PDO connection object

// The area keys you want to find configs for
$areaKeys = [
    'style-profiles-axes-translation',
    'style-profiles-prompt-polish'
];

// 1. Create the correct number of '?' placeholders for the SQL IN clause
// This will result in a string like "?,?"
$placeholders = implode(',', array_fill(0, count($areaKeys), '?'));

// 2. Construct the final SQL query using the correct table and column names
$sql = "
    SELECT DISTINCT
        g.config_id,
        g.title
    FROM
        generator_config g
    JOIN
        generator_config_to_display_area gctda ON g.id = gctda.generator_config_id
    JOIN
        generator_config_display_area gcda ON gctda.display_area_id = gcda.id
    WHERE
        g.active = 1
        AND gcda.area_key IN ($placeholders)
    ORDER BY
        g.title ASC
";

// 3. Prepare the SQL statement to prevent SQL injection
$stmt = $pdo->prepare($sql);

// 4. Execute the statement, passing the array of area keys.
// PDO will safely bind each value in the array to a placeholder.
$stmt->execute($areaKeys);

// 5. Fetch all the matching rows
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// The $rows variable now holds the desired list of generators
// e.g., print_r($rows);


    
        
        
        
        
        
        
        
        jsonResp(['status'=>'ok','configs'=>$rows]);
    } catch (Exception $e) {
        jsonResp(['status'=>'error','message'=>$e->getMessage()]);
    }
}

// action: list
if ($action === 'list') {
    try {
        $axisGroup = isset($_GET['axis_group']) ? trim($_GET['axis_group']) : null;
        
        if ($axisGroup) {
            $stmt = $pdo->prepare("SELECT id, name, description, axis_group, filename, created_at FROM style_profiles WHERE axis_group = :group ORDER BY created_at DESC LIMIT 500");
            $stmt->execute([':group' => $axisGroup]);
        } else {
            $stmt = $pdo->query("SELECT id, name, description, axis_group, filename, created_at FROM style_profiles ORDER BY created_at DESC LIMIT 500");
        }
        
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
        // Updated query to fetch convert_result
        $stmt = $pdo->prepare("
            SELECT sp.id AS profile_id, sp.name, sp.description, sp.axis_group, sp.created_at, sp.convert_result,
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

        // Updated profile structure to include convert_result
        $profile = [
            'id' => (int)$rows[0]['profile_id'],
            'name' => $rows[0]['name'],
            'description' => $rows[0]['description'],
            'axis_group' => $rows[0]['axis_group'] ?? 'default',
            'created_at' => $rows[0]['created_at'],
            'convert_result' => $rows[0]['convert_result'],
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

// action: save_db
if ($action === 'save_db') {
    if (!$body) jsonResp(['status'=>'error','message'=>'Empty request or invalid JSON']);
    $profileId = isset($body['id']) ? (int)$body['id'] : 0;
    $name = isset($body['name']) && strlen(trim($body['name'])) ? trim($body['name']) : null;
    $description = isset($body['description']) ? trim($body['description']) : null;
    $axis_group = isset($body['axis_group']) && !empty(trim($body['axis_group'])) ? trim($body['axis_group']) : 'default';
    $axes = isset($body['axes']) && is_array($body['axes']) ? $body['axes'] : [];

    if (count($axes) === 0) jsonResp(['status'=>'error','message'=>'No axes provided']);

    try {
        // Validate axis ids
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
            'name' => $name,
            'description' => $description,
            'axis_group' => $axis_group,
            'created_at' => $body['created_at'] ?? (new DateTime('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
            'axes' => $axes
        ];

        // save file copy
        $filenameSafe = $name ? preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $name) : 'style_profile';
        $filename = $filenameSafe . '_' . date('Y-m-d_H-i-s') . '.json';
        $filepath = $saveDir . '/' . $filename;
        @file_put_contents($filepath, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $pdo->beginTransaction();

        if ($profileId > 0) {
            // update
            $update = $pdo->prepare("UPDATE style_profiles SET name = :name, description = :description, axis_group = :axis_group, filename = :filename, json_payload = :json_payload WHERE id = :id");
            $update->execute([
                ':name' => $name,
                ':description' => $description,
                ':axis_group' => $axis_group,
                ':filename' => $filename,
                ':json_payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                ':id' => $profileId
            ]);
        } else {
            // insert
            $ins = $pdo->prepare("INSERT INTO style_profiles (name, description, axis_group, filename, json_payload, created_at, created_by) VALUES (:name, :description, :axis_group, :filename, :json_payload, :created_at, :created_by)");
            $ins->execute([
                ':name' => $name,
                ':description' => $description,
                ':axis_group' => $axis_group,
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

// New action: save_convert_result
if ($action === 'save_convert_result') {
    if (!$body || !isset($body['id']) || !isset($body['result'])) {
        jsonResp(['status'=>'error','message'=>'Missing id or result']);
    }
    $id = (int)$body['id'];
    $result = is_string($body['result']) ? trim($body['result']) : '';
    if ($id <= 0) {
        jsonResp(['status'=>'error','message'=>'Invalid id']);
    }

    try {
        $stmt = $pdo->prepare("UPDATE style_profiles SET convert_result = :result WHERE id = :id");
        $stmt->execute([':result' => $result, ':id' => $id]);
        jsonResp(['status'=>'ok', 'message'=>'Result saved.']);
    } catch (Exception $e) {
        if (isset($fileLogger) && is_callable([$fileLogger,'error'])) {
            $fileLogger->error('style_profiles_api save_convert_result error: '.$e->getMessage());
        }
        jsonResp(['status'=>'error','message'=>$e->getMessage()]);
    }
}


if ($action === 'convert_proxy') {
    $raw = file_get_contents('php://input');
    header('Content-Type: application/json');

    $forwardPayload = $raw ?: '{}';

    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        if (array_key_exists('profile', $decoded) && !array_key_exists('profiles', $decoded)) {
            $decoded['profiles'] = [ $decoded['profile'] ];
            unset($decoded['profile']);
        }

        if (array_key_exists('profiles', $decoded)) {
            $p = $decoded['profiles'];
            if (!is_array($p) || array_keys($p) !== range(0, count($p) - 1)) {
                $decoded['profiles'] = [ $p ];
            }
        } else {
            if (isset($decoded['axes']) || isset($decoded['name']) || isset($decoded['id'])) {
                $decoded = ['profiles' => [ $decoded ]];
            }
        }
        
        // Load generator config IDs from database
        try {
            $configStmt = $pdo->query("SELECT config_key, config_value FROM style_profile_config WHERE config_key IN ('axes_generator_config_id', 'polish_generator_config_id')");
            $configRows = $configStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            if (isset($configRows['axes_generator_config_id'])) {
                $decoded['generator_config_id'] = $configRows['axes_generator_config_id'];
            }
            if (isset($configRows['polish_generator_config_id'])) {
                $decoded['polish_generator_config_id'] = $configRows['polish_generator_config_id'];
            }
        } catch (Exception $e) {
            // fallback to defaults if config fetch fails
            $decoded['generator_config_id'] = '777af2baa9d8360fb01e6337368880c9';
            $decoded['polish_generator_config_id'] = '623ce189ac2ede98c2d60c24c0d814e2';
        }

        $forwardPayload = json_encode($decoded);
    }

    $target = 'http://127.0.0.1:8009/style/convert';
    $ch = curl_init($target);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $forwardPayload);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);

    $resp = curl_exec($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        http_response_code(502);
        echo json_encode(['error' => 'proxy_curl_failed', 'message' => $curlErr]);
        exit;
    }

    http_response_code($httpcode ?: 200);
    echo $resp;
    exit;
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
