<?php
// public/design_axes_api.php
// API for design_axes CRUD: actions: load, save, delete
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

function jsonResp($arr) {
    header('Content-Type: application/json; charset=utf--8');
    echo json_encode($arr);
    exit;
}

// Check if axis_group and category columns exist for compatibility
$groupColumnExists = false;
$categoryColumnExists = false;
try {
    $checkGroupCol = $pdo->query("SHOW COLUMNS FROM design_axes LIKE 'axis_group'");
    $groupColumnExists = $checkGroupCol->rowCount() > 0;
    
    $checkCatCol = $pdo->query("SHOW COLUMNS FROM design_axes LIKE 'category'");
    $categoryColumnExists = $checkCatCol->rowCount() > 0;
} catch (Exception $e) {
    // A column probably doesn't exist, flags will remain false
}

$action = isset($_GET['action']) ? strtolower(trim($_GET['action'])) : (isset($_REQUEST['action']) ? strtolower(trim($_REQUEST['action'])) : null);

if (!in_array($action, ['load','save','delete'])) {
    jsonResp(['status'=>'error','message'=>'Unknown action']);
}

// read input body if POST for relevant actions
$rawBody = file_get_contents('php://input');
$body = null;
if ($rawBody) {
    $body = json_decode($rawBody, true);
}

// action: load
if ($action === 'load') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) jsonResp(['status'=>'error','message'=>'Missing id']);
    
    try {
        // Dynamically build SELECT statement
        $selectFields = "id, axis_name, pole_left, pole_right, notes";
        if ($groupColumnExists) {
            $selectFields .= ", COALESCE(axis_group, 'default') as axis_group";
        }
        if ($categoryColumnExists) {
            $selectFields .= ", COALESCE(category, '') as category";
        }
        
        $stmt = $pdo->prepare("SELECT $selectFields FROM design_axes WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $axis = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$axis) jsonResp(['status'=>'error','message'=>'Axis not found']);
        
        jsonResp(['status'=>'ok','axis'=>$axis]);
    } catch (Exception $e) {
        jsonResp(['status'=>'error','message'=>$e->getMessage()]);
    }
}

// action: save (insert or update)
if ($action === 'save') {
    if (!$body) jsonResp(['status'=>'error','message'=>'Empty request or invalid JSON']);
    
    $id = isset($body['id']) ? (int)$body['id'] : 0;
    $axisName = isset($body['axis_name']) ? trim($body['axis_name']) : '';
    $poleLeft = isset($body['pole_left']) ? trim($body['pole_left']) : '';
    $poleRight = isset($body['pole_right']) ? trim($body['pole_right']) : '';
    $notes = isset($body['notes']) ? trim($body['notes']) : '';
    
    if (!$axisName || !$poleLeft || !$poleRight) {
        jsonResp(['status'=>'error','message'=>'Missing required fields']);
    }
    
    try {
        if ($id > 0) {
            // update
            $sql = "UPDATE design_axes SET axis_name = :axis_name, pole_left = :pole_left, pole_right = :pole_right, notes = :notes";
            $params = [
                ':axis_name' => $axisName,
                ':pole_left' => $poleLeft,
                ':pole_right' => $poleRight,
                ':notes' => $notes,
                ':id' => $id
            ];

            if ($groupColumnExists) {
                $axisGroup = isset($body['axis_group']) ? trim($body['axis_group']) : 'default';
                $sql .= ", axis_group = :axis_group";
                $params[':axis_group'] = $axisGroup;
            }
            if ($categoryColumnExists) {
                $category = isset($body['category']) ? trim($body['category']) : null;
                $sql .= ", category = :category";
                $params[':category'] = $category;
            }

            $sql .= " WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            jsonResp(['status'=>'ok','axis_id'=>$id]);
        } else {
            // insert
            $columns = "axis_name, pole_left, pole_right, notes";
            $placeholders = ":axis_name, :pole_left, :pole_right, :notes";
            $params = [
                ':axis_name' => $axisName,
                ':pole_left' => $poleLeft,
                ':pole_right' => $poleRight,
                ':notes' => $notes
            ];

            if ($groupColumnExists) {
                $axisGroup = isset($body['axis_group']) ? trim($body['axis_group']) : 'default';
                $columns .= ", axis_group";
                $placeholders .= ", :axis_group";
                $params[':axis_group'] = $axisGroup;
            }
            if ($categoryColumnExists) {
                $category = isset($body['category']) ? trim($body['category']) : null;
                $columns .= ", category";
                $placeholders .= ", :category";
                $params[':category'] = $category;
            }

            $sql = "INSERT INTO design_axes ($columns) VALUES ($placeholders)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            $newId = (int)$pdo->lastInsertId();
            jsonResp(['status'=>'ok','axis_id'=>$newId]);
        }
    } catch (Exception $e) {
        if (isset($fileLogger) && is_callable([$fileLogger,'error'])) {
            $fileLogger->error('design_axes_api save error: '.$e->getMessage());
        }
        jsonResp(['status'=>'error','message'=>$e->getMessage()]);
    }
}

// action: delete
if ($action === 'delete') {
    if (!$body || !isset($body['id'])) jsonResp(['status'=>'error','message'=>'Missing id']);
    $id = (int)$body['id'];
    if ($id <= 0) jsonResp(['status'=>'error','message'=>'Invalid id']);
    
    try {
        // also delete related profile axes
        $delRelated = $pdo->prepare("DELETE FROM style_profile_axes WHERE axis_id = :id");
        $delRelated->execute([':id' => $id]);
        
        $del = $pdo->prepare("DELETE FROM design_axes WHERE id = :id");
        $del->execute([':id' => $id]);
        
        jsonResp(['status'=>'ok']);
    } catch (Exception $e) {
        if (isset($fileLogger) && is_callable([$fileLogger,'error'])) {
            $fileLogger->error('design_axes_api delete error: '.$e->getMessage());
        }
        jsonResp(['status'=>'error','message'=>$e->getMessage()]);
    }
}

jsonResp(['status'=>'error','message'=>'Unhandled action']);
