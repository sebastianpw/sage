<?php
// public/sketch_templates_forge_api.php
// Consolidates: sketch_templates_api.php, shot_types_api.php,
//               camera_angles_api.php, camera_perspectives_api.php, interactions_api.php
require_once __DIR__ . '/bootstrap.php';
$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

function jsonResp($arr) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr);
    exit;
}

$action = $_GET['action'] ?? $_REQUEST['action'] ?? null;
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

if (!$action) {
    jsonResp(['status' => 'error', 'message' => 'Action not specified']);
}

try {
    switch ($action) {

        // ══════════════════════════════════════════════════
        // SKETCH TEMPLATES
        // ══════════════════════════════════════════════════

        case 'templates_list':
            $stmt = $pdo->query("SELECT * FROM sketch_templates ORDER BY name ASC");
            jsonResp(['status' => 'ok', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'templates_load':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("SELECT * FROM sketch_templates WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) throw new Exception('Template not found');
            jsonResp(['status' => 'ok', 'data' => $data]);
            break;

        case 'templates_save':
            $id   = (int)($body['id'] ?? 0);
            $name = trim($body['name'] ?? '');
            if (empty($name)) throw new Exception('Name is required');

            json_decode($body['entity_slots'] ?? '[]');
            if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Entity Slots is not valid JSON.');
            json_decode($body['tags'] ?? '[]');
            if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('Tags is not valid JSON.');

            $params = [
                ':name'            => $name,
                ':core_idea'       => trim($body['core_idea'] ?? ''),
                ':shot_type'       => trim($body['shot_type'] ?? ''),
                ':camera_angle'    => trim($body['camera_angle'] ?? ''),
                ':perspective'     => trim($body['perspective'] ?? ''),
                ':entity_slots'    => $body['entity_slots'],
                ':tags'            => $body['tags'],
                ':example_prompt'  => trim($body['example_prompt'] ?? ''),
                ':entity_type'     => trim($body['entity_type'] ?? 'sketches'),
                ':active'          => (int)($body['active'] ?? 1),
            ];

            if ($id > 0) {
                $params[':id'] = $id;
                $sql = "UPDATE sketch_templates SET name=:name, core_idea=:core_idea, shot_type=:shot_type, camera_angle=:camera_angle, perspective=:perspective, entity_slots=:entity_slots, tags=:tags, example_prompt=:example_prompt, entity_type=:entity_type, active=:active WHERE id=:id";
            } else {
                $sql = "INSERT INTO sketch_templates (name, core_idea, shot_type, camera_angle, perspective, entity_slots, tags, example_prompt, entity_type, active) VALUES (:name, :core_idea, :shot_type, :camera_angle, :perspective, :entity_slots, :tags, :example_prompt, :entity_type, :active)";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $newId = $id > 0 ? $id : $pdo->lastInsertId();
            jsonResp(['status' => 'ok', 'id' => $newId]);
            break;

        case 'templates_delete':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            $pdo->prepare("DELETE FROM sketch_templates WHERE id = :id")->execute([':id' => $id]);
            jsonResp(['status' => 'ok']);
            break;

        case 'templates_toggle':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            $pdo->prepare("UPDATE sketch_templates SET active = NOT active WHERE id = :id")->execute([':id' => $id]);
            jsonResp(['status' => 'ok']);
            break;

        // ══════════════════════════════════════════════════
        // SHOT TYPES
        // ══════════════════════════════════════════════════

        case 'shot_types_list':
            $stmt = $pdo->query("SELECT * FROM shot_types ORDER BY name ASC");
            jsonResp(['status' => 'ok', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'shot_types_load':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("SELECT * FROM shot_types WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) throw new Exception('Shot Type not found');
            jsonResp(['status' => 'ok', 'data' => $data]);
            break;

        case 'shot_types_save':
            $id   = (int)($body['id'] ?? 0);
            $name = trim($body['name'] ?? '');
            if (empty($name)) throw new Exception('Name is required');
            if ($id > 0) {
                $pdo->prepare("UPDATE shot_types SET name = :name WHERE id = :id")->execute([':name' => $name, ':id' => $id]);
            } else {
                $pdo->prepare("INSERT INTO shot_types (name) VALUES (:name)")->execute([':name' => $name]);
            }
            jsonResp(['status' => 'ok']);
            break;

        case 'shot_types_delete':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("SELECT name FROM shot_types WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($item) {
                $u = $pdo->prepare("SELECT COUNT(*) FROM sketch_templates WHERE shot_type = :name");
                $u->execute([':name' => $item['name']]);
                if ($u->fetchColumn() > 0) throw new Exception('Cannot delete: Shot Type is currently in use by sketch templates.');
            }
            $pdo->prepare("DELETE FROM shot_types WHERE id = :id")->execute([':id' => $id]);
            jsonResp(['status' => 'ok']);
            break;

        // ══════════════════════════════════════════════════
        // CAMERA ANGLES
        // ══════════════════════════════════════════════════

        case 'camera_angles_list':
            $stmt = $pdo->query("SELECT * FROM camera_angles ORDER BY name ASC");
            jsonResp(['status' => 'ok', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'camera_angles_load':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("SELECT * FROM camera_angles WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) throw new Exception('Angle not found');
            jsonResp(['status' => 'ok', 'data' => $data]);
            break;

        case 'camera_angles_save':
            $id   = (int)($body['id'] ?? 0);
            $name = trim($body['name'] ?? '');
            if (empty($name)) throw new Exception('Name is required');
            if ($id > 0) {
                $pdo->prepare("UPDATE camera_angles SET name = :name WHERE id = :id")->execute([':name' => $name, ':id' => $id]);
            } else {
                $pdo->prepare("INSERT INTO camera_angles (name) VALUES (:name)")->execute([':name' => $name]);
            }
            jsonResp(['status' => 'ok']);
            break;

        case 'camera_angles_delete':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("SELECT name FROM camera_angles WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $angle = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($angle) {
                $u = $pdo->prepare("SELECT COUNT(*) FROM sketch_templates WHERE camera_angle = :name");
                $u->execute([':name' => $angle['name']]);
                if ($u->fetchColumn() > 0) throw new Exception('Cannot delete: Angle is currently in use by sketch templates.');
            }
            $pdo->prepare("DELETE FROM camera_angles WHERE id = :id")->execute([':id' => $id]);
            jsonResp(['status' => 'ok']);
            break;

        // ══════════════════════════════════════════════════
        // CAMERA PERSPECTIVES
        // ══════════════════════════════════════════════════

        case 'camera_perspectives_list':
            $stmt = $pdo->query("SELECT * FROM camera_perspectives ORDER BY name ASC");
            jsonResp(['status' => 'ok', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'camera_perspectives_load':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("SELECT * FROM camera_perspectives WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) throw new Exception('Perspective not found');
            jsonResp(['status' => 'ok', 'data' => $data]);
            break;

        case 'camera_perspectives_save':
            $id   = (int)($body['id'] ?? 0);
            $name = trim($body['name'] ?? '');
            if (empty($name)) throw new Exception('Name is required');
            if ($id > 0) {
                $pdo->prepare("UPDATE camera_perspectives SET name = :name WHERE id = :id")->execute([':name' => $name, ':id' => $id]);
            } else {
                $pdo->prepare("INSERT INTO camera_perspectives (name) VALUES (:name)")->execute([':name' => $name]);
            }
            jsonResp(['status' => 'ok']);
            break;

        case 'camera_perspectives_delete':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("SELECT name FROM camera_perspectives WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $perspective = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($perspective) {
                $u = $pdo->prepare("SELECT COUNT(*) FROM sketch_templates WHERE perspective = :name");
                $u->execute([':name' => $perspective['name']]);
                if ($u->fetchColumn() > 0) throw new Exception('Cannot delete: Perspective is currently in use by sketch templates.');
            }
            $pdo->prepare("DELETE FROM camera_perspectives WHERE id = :id")->execute([':id' => $id]);
            jsonResp(['status' => 'ok']);
            break;

        // ══════════════════════════════════════════════════
        // INTERACTIONS
        // ══════════════════════════════════════════════════

        case 'interactions_list':
            $stmt = $pdo->query("SELECT * FROM interactions ORDER BY interaction_group ASC, category ASC, name ASC");
            jsonResp(['status' => 'ok', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            break;

        case 'interactions_load':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            $stmt = $pdo->prepare("SELECT * FROM interactions WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$data) throw new Exception('Interaction not found');
            jsonResp(['status' => 'ok', 'data' => $data]);
            break;

        case 'interactions_save':
            $id   = (int)($body['id'] ?? 0);
            $name = trim($body['name'] ?? '');
            if (empty($name)) throw new Exception('Name is required');

            $params = [
                ':name'              => $name,
                ':description'       => trim($body['description'] ?? ''),
                ':interaction_group' => trim($body['interaction_group'] ?? ''),
                ':category'          => trim($body['category'] ?? '') ?: null,
                ':example_prompt'    => trim($body['example_prompt'] ?? ''),
                ':active'            => (int)($body['active'] ?? 1),
            ];
            if (empty($params[':interaction_group'])) throw new Exception('Group is required');

            if ($id > 0) {
                $params[':id'] = $id;
                $sql = "UPDATE interactions SET name=:name, description=:description, interaction_group=:interaction_group, category=:category, example_prompt=:example_prompt, active=:active WHERE id=:id";
            } else {
                $sql = "INSERT INTO interactions (name, description, interaction_group, category, example_prompt, active) VALUES (:name, :description, :interaction_group, :category, :example_prompt, :active)";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $newId = $id > 0 ? $id : $pdo->lastInsertId();
            jsonResp(['status' => 'ok', 'id' => $newId]);
            break;

        case 'interactions_delete':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            $pdo->prepare("DELETE FROM interactions WHERE id = :id")->execute([':id' => $id]);
            jsonResp(['status' => 'ok']);
            break;

        case 'interactions_toggle':
            $id = (int)($body['id'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid ID');
            $pdo->prepare("UPDATE interactions SET active = NOT active WHERE id = :id")->execute([':id' => $id]);
            jsonResp(['status' => 'ok']);
            break;

        default:
            throw new Exception('Unknown action: ' . htmlspecialchars($action));
    }
} catch (Exception $e) {
    http_response_code(400);
    jsonResp(['status' => 'error', 'message' => $e->getMessage()]);
}
