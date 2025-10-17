<?php
// tabs_ajax.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php'; // provides $pdoSys

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Only POST allowed']);
    exit;
}

$action = $_POST['action'] ?? null;
if (!$action) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing action']);
    exit;
}

try {
    // ---------- save_top_order ----------
    if ($action === 'save_top_order') {
        $orderJson = $_POST['order'] ?? '[]';
        $order = json_decode($orderJson, true);
        if (!is_array($order)) { throw new Exception('invalid order'); }

        $pdoSys->beginTransaction();
        $pos = 0;
        $up = $pdoSys->prepare("UPDATE pages_dashboard SET position = :pos, level = 1, parent_id = NULL WHERE id = :id");
        foreach ($order as $id) {
            $id = (int)$id;
            if ($id <= 0) continue;
            $up->execute([':pos' => $pos, ':id' => $id]);
            $pos++;
        }
        $pdoSys->commit();

        echo json_encode(['ok' => true, 'updated' => $pos]);
        exit;
    }

    // ---------- save_nested ----------
    if ($action === 'save_nested') {
        $nestedJson = $_POST['nested'] ?? '[]';
        $nested = json_decode($nestedJson, true);
        if (!is_array($nested)) { throw new Exception('invalid nested payload'); }

        $pdoSys->beginTransaction();

        // prepared statements
        $up = $pdoSys->prepare("UPDATE pages_dashboard SET position = :pos, parent_id = :parent, level = 2 WHERE id = :id");
        $pselect = $pdoSys->prepare("SELECT id, level FROM pages_dashboard WHERE id = :pid LIMIT 1");
        $promote = $pdoSys->prepare("UPDATE pages_dashboard SET level = 1, parent_id = NULL WHERE id = :pid");

        $count = 0;
        foreach ($nested as $row) {
            // expected row: { id: int, parent_id: int|null, position: int }
            $id = isset($row['id']) ? (int)$row['id'] : null;
            $parent = isset($row['parent_id']) && $row['parent_id'] !== '' && $row['parent_id'] !== null ? (int)$row['parent_id'] : null;
            $pos = isset($row['position']) ? (int)$row['position'] : 0;
            if ($id === null || $id <= 0) continue;

            // If parent provided, validate it exists and is level=1. If not exists -> treat as NULL.
            if ($parent !== null) {
                $pselect->execute([':pid' => $parent]);
                $pinfo = $pselect->fetch(PDO::FETCH_ASSOC);
                if (!$pinfo) {
                    // invalid parent -> set to NULL
                    $parent = null;
                } else {
                    // If parent exists but isn't level 1, promote it to level = 1 (and clear its parent_id)
                    if ((int)$pinfo['level'] !== 1) {
                        $promote->execute([':pid' => $parent]);
                    }
                }
            }

            // perform update for child
            $up->execute([':pos' => $pos, ':parent' => $parent, ':id' => $id]);
            $count++;
        }

        $pdoSys->commit();
        echo json_encode(['ok' => true, 'updated' => $count]);
        exit;
    }

    // ---------- update_title ----------
    if ($action === 'update_title') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $title = isset($_POST['title']) ? (string)$_POST['title'] : '';
        $title = trim($title);

        if ($id <= 0) throw new Exception('invalid id');
        if ($title === '') throw new Exception('title cannot be empty');

        // Ensure the record exists and is level=1 (only allow editing level 1 items)
        $s = $pdoSys->prepare("SELECT id, level FROM pages_dashboard WHERE id = :id LIMIT 1");
        $s->execute([':id' => $id]);
        $row = $s->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception('record not found');
        if ((int)$row['level'] !== 1) {
            throw new Exception('only level=1 records may be renamed');
        }

        $u = $pdoSys->prepare("UPDATE pages_dashboard SET name = :title WHERE id = :id LIMIT 1");
        $ok = $u->execute([':title' => $title, ':id' => $id]);
        if (!$ok) throw new Exception('db update failed');

        echo json_encode(['ok' => true, 'id' => $id, 'title' => $title]);
        exit;
    }

    // Unknown action
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'unknown action']);
    exit;

} catch (Exception $e) {
    if (isset($pdoSys) && $pdoSys instanceof PDO && $pdoSys->inTransaction()) {
        $pdoSys->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    exit;
}
