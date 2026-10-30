<?php
// public/meshes_api.php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

use App\Core\SpwBase;
use App\Core\FramesManager;

header('Content-Type: application/json; charset=utf-8');

global $pdo, $projectPath;

$spw = SpwBase::getInstance();
$fm  = FramesManager::getInstance();
$projectRoot = PROJECT_ROOT ?? ($projectPath ?? dirname(__DIR__, 1));

/**
 * Smart resolver to find the actual GLB file and its public URL
 */
function resolveMeshFile(string $rawFilename, string $projectRoot): array
{
    $clean = ltrim($rawFilename, '/');
    $base  = basename($clean);

    $candidates = [
        ['fs' => rtrim($projectRoot, '/') . '/public/' . $clean, 'url' => '/' . $clean],
        ['fs' => rtrim($projectRoot, '/') . '/' . $clean, 'url' => '/' . preg_replace('#^public/#', '', $clean)],
        ['fs' => rtrim($projectRoot, '/') . '/public/meshes/' . $base, 'url' => '/meshes/' . $base],
        ['fs' => rtrim($projectRoot, '/') . '/public/models/' . $base, 'url' => '/models/' . $base],
        ['fs' => rtrim($projectRoot, '/') . '/public/' . $base, 'url' => '/' . $base],
    ];

    foreach ($candidates as $c) {
        if (file_exists($c['fs']) && is_file($c['fs'])) {
            return [
                'fsPath' => $c['fs'],
                'webUrl' => $c['url'],
                'size'   => filesize($c['fs']),
                'mtime'  => filemtime($c['fs'])
            ];
        }
    }

    $fallbackUrl = (str_starts_with($clean, 'meshes/') || str_starts_with($clean, 'models/')) 
        ? '/' . $clean 
        : '/models/' . $clean;

    return [
        'fsPath' => rtrim($projectRoot, '/') . '/public' . $fallbackUrl,
        'webUrl' => $fallbackUrl,
        'size'   => 0,
        'mtime'  => time()
    ];
}

/**
 * Helper to decode base64 or DataURL strings
 */
function decodeBase64Payload(?string $str): ?string
{
    if (!$str || !is_string($str)) return null;
    $str = trim($str);

    if (str_contains($str, '%')) {
        $str = rawurldecode($str);
    }
    if (str_contains($str, 'base64,')) {
        $parts = explode('base64,', $str);
        $str = end($parts);
    }

    $decoded = base64_decode($str, true);
    if ($decoded !== false && strlen($decoded) > 50) {
        return $decoded;
    }
    return null;
}

// Normalize query string if client appended '?id=' or '?mesh_id='
if (!empty($_SERVER['QUERY_STRING'])) {
    if (substr_count($_SERVER['QUERY_STRING'], '?') > 0) {
        $normalizedQuery = str_replace('?', '&', $_SERVER['QUERY_STRING']);
        parse_str($normalizedQuery, $extraParams);
        foreach ($extraParams as $k => $v) {
            $_GET[$k] = $v;
            $_REQUEST[$k] = $v;
        }
    }
}

// Action routing
$action = $_REQUEST['action'] ?? '';
if (str_starts_with($action, 'get')) {
    $action = 'get';
}
if (empty($action)) {
    if (!empty($_GET['id']) || !empty($_REQUEST['id'])) {
        $action = 'get';
    } else {
        $action = 'list';
    }
}

try {
    // ---------------------------------------------------------
    // ACTION: get
    // ---------------------------------------------------------
    if ($action === 'get') {
        $id = $_GET['id'] ?? ($_REQUEST['id'] ?? null);

        if (!$id && !empty($_SERVER['QUERY_STRING'])) {
            if (preg_match('/[?&]id=([^&]+)/', $_SERVER['QUERY_STRING'], $m)) {
                $id = rawurldecode($m[1]);
            }
        }

        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing mesh id']);
            exit;
        }

        $row = null;
        if (is_numeric($id)) {
            $stmt = $pdo->prepare("SELECT * FROM meshes WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$row) {
            $fname = rawurldecode($id);
            $stmt = $pdo->prepare("SELECT * FROM meshes WHERE filename = ? OR filename LIKE ? LIMIT 1");
            $stmt->execute([$fname, '%' . $fname]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if ($row) {
            $resolved = resolveMeshFile($row['filename'], $projectRoot);
            $displayName = '#' . $row['id'] . ' - ' . ($row['name'] ?: basename($row['filename']));

            echo json_encode([
                'id'          => (string)$row['id'],
                'mesh_id'     => (int)$row['id'],
                'name'        => $displayName,
                'url'         => $resolved['webUrl'],
                'size'        => $resolved['size'],
                'mtime'       => $resolved['mtime'],
                'entity_type' => $row['entity_type'],
                'entity_id'   => $row['entity_id']
            ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            exit;
        }

        $rawFile = rawurldecode($id);
        $resolved = resolveMeshFile($rawFile, $projectRoot);
        if ($resolved['size'] > 0) {
            echo json_encode([
                'id'    => rawurlencode($rawFile),
                'name'  => $rawFile,
                'url'   => $resolved['webUrl'],
                'size'  => $resolved['size'],
                'mtime' => $resolved['mtime'],
            ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
            exit;
        }

        http_response_code(404);
        echo json_encode(['error' => 'Mesh not found for id: ' . htmlspecialchars($id)]);
        exit;
    }

    // ---------------------------------------------------------
    // ACTION: list
    // ---------------------------------------------------------
    if ($action === 'list') {
        $animaticId = (int)($_GET['animatic_id'] ?? 0);
        $sketchId   = (int)($_GET['sketch_id'] ?? 0);

        if ($animaticId > 0) {
            $stmt = $pdo->prepare("
                SELECT m.*, am.animatic_id 
                FROM meshes m
                INNER JOIN animatic_meshes am ON m.id = am.mesh_id
                WHERE am.animatic_id = ?
                ORDER BY m.id DESC
            ");
            $stmt->execute([$animaticId]);
        } elseif ($sketchId > 0) {
            $stmt = $pdo->prepare("
                SELECT m.* 
                FROM meshes m
                WHERE m.entity_type = 'sketches' AND m.entity_id = ?
                ORDER BY m.id DESC
            ");
            $stmt->execute([$sketchId]);
        } else {
            $stmt = $pdo->query("SELECT * FROM meshes ORDER BY id DESC");
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $results = [];

        foreach ($rows as $row) {
            $resolved = resolveMeshFile($row['filename'], $projectRoot);

            $displayName = '#' . $row['id'] . ' - ' . ($row['name'] ?: basename($row['filename']));
            if (!empty($row['entity_type'])) {
                $displayName .= ' (' . $row['entity_type'] . ' #' . $row['entity_id'] . ')';
            }

            $results[] = [
                'id'          => (string)$row['id'],
                'mesh_id'     => (int)$row['id'],
                'name'        => $displayName,
                'path'        => $resolved['webUrl'],
                'url'         => $resolved['webUrl'],
                'size'        => $resolved['size'],
                'mtime'       => $resolved['mtime'],
                'entity_type' => $row['entity_type'],
                'entity_id'   => $row['entity_id'],
                'map_run_id'  => $row['map_run_id'],
                'created_at'  => $row['created_at']
            ];
        }

        $modelsDirFs = rtrim($projectRoot, '/') . '/public/models';
        if (empty($results) && is_dir($modelsDirFs)) {
            $dir = new DirectoryIterator($modelsDirFs);
            foreach ($dir as $file) {
                if ($file->isFile()) {
                    $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
                    if (in_array($ext, ['glb', 'gltf'])) {
                        $results[] = [
                            'id'    => rawurlencode($file->getFilename()),
                            'name'  => $file->getFilename(),
                            'path'  => '/models/' . $file->getFilename(),
                            'url'   => '/models/' . $file->getFilename(),
                            'size'  => $file->getSize(),
                            'mtime' => $file->getMTime(),
                            'entity_type' => 'file',
                            'entity_id'   => 0
                        ];
                    }
                }
            }
            usort($results, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        }

        echo json_encode($results, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    // ---------------------------------------------------------
    // ACTION: save_snapshot
    // Obeying all rules of the SAGE frames system:
    // 1. Accepts snapshot via ANY payload format ($_FILES, $_POST, php://input)
    // 2. Allocates next canonical basename via frame_counter mutex
    // 3. Creates a map_runs row for the associated entity
    // 4. Writes image into canonical framesDir
    // 5. Inserts row into `frames` table
    // 6. Inserts relational mapping into `frames_2_{entity_type}`
    // ---------------------------------------------------------
    if ($action === 'save_snapshot') {
        $rawBinary = null;

        // 1a. Inspect any uploaded file in $_FILES
        if (!empty($_FILES)) {
            foreach ($_FILES as $f) {
                if (isset($f['error']) && $f['error'] === UPLOAD_ERR_OK && is_uploaded_file($f['tmp_name'])) {
                    $rawBinary = file_get_contents($f['tmp_name']);
                    if ($rawBinary) break;
                }
            }
        }

        // 1b. Check $_POST for known or arbitrary base64/dataURL fields
        if (!$rawBinary && !empty($_POST)) {
            $knownKeys = ['data', 'dataUrl', 'data_url', 'image', 'imageData', 'image_data', 'file', 'base64', 'snapshot'];
            foreach ($knownKeys as $k) {
                if (!empty($_POST[$k])) {
                    $rawBinary = decodeBase64Payload($_POST[$k]);
                    if ($rawBinary) break;
                }
            }
            if (!$rawBinary) {
                foreach ($_POST as $v) {
                    if (is_string($v) && (str_starts_with($v, 'data:image') || strlen($v) > 200)) {
                        $rawBinary = decodeBase64Payload($v);
                        if ($rawBinary) break;
                    }
                }
            }
        }

        // 1c. Check $_GET / $_REQUEST query parameters
        if (!$rawBinary && !empty($_GET)) {
            foreach (['data', 'dataUrl', 'data_url', 'image', 'imageData', 'base64'] as $k) {
                if (!empty($_GET[$k])) {
                    $rawBinary = decodeBase64Payload($_GET[$k]);
                    if ($rawBinary) break;
                }
            }
        }

        // 1d. Check php://input (raw body: binary, JSON, or raw base64 string)
        if (!$rawBinary) {
            $rawInput = file_get_contents('php://input');
            if (!empty($rawInput)) {
                // If it is directly binary image
                if (str_starts_with($rawInput, "\xFF\xD8\xFF") || str_starts_with($rawInput, "\x89PNG")) {
                    $rawBinary = $rawInput;
                } elseif ($decodedJson = json_decode($rawInput, true)) {
                    $jsonKeys = ['data', 'dataUrl', 'data_url', 'image', 'imageData', 'image_data', 'file', 'base64', 'snapshot'];
                    foreach ($jsonKeys as $k) {
                        if (!empty($decodedJson[$k])) {
                            $rawBinary = decodeBase64Payload($decodedJson[$k]);
                            if ($rawBinary) break;
                        }
                    }
                    if (!$rawBinary) {
                        foreach ($decodedJson as $v) {
                            if (is_string($v) && (str_starts_with($v, 'data:image') || strlen($v) > 200)) {
                                $rawBinary = decodeBase64Payload($v);
                                if ($rawBinary) break;
                            }
                        }
                    }
                } else {
                    $rawBinary = decodeBase64Payload($rawInput);
                }
            }
        }

        if (!$rawBinary) {
            throw new Exception("No snapshot image data provided.");
        }

        // 2. Resolve associated entity context from active mesh
        $meshId     = (int)($_REQUEST['mesh_id'] ?? 0);
        $entityType = trim($_REQUEST['entity_type'] ?? '');
        $entityId   = (int)($_REQUEST['entity_id'] ?? 0);

        if ($meshId > 0 && (empty($entityType) || !$entityId)) {
            $stmtM = $pdo->prepare("SELECT entity_type, entity_id, name, filename FROM meshes WHERE id = ? LIMIT 1");
            $stmtM->execute([$meshId]);
            $meshRow = $stmtM->fetch(PDO::FETCH_ASSOC);
            if ($meshRow) {
                if (empty($entityType)) $entityType = $meshRow['entity_type'] ?? '';
                if (!$entityId)   $entityId   = (int)($meshRow['entity_id'] ?? 0);
                $meshName = $meshRow['name'] ?: basename($meshRow['filename']);
            }
        }

        if (empty($entityType)) {
            $entityType = 'sketches';
        }

        // 3. Detect file format from binary magic bytes
        $ext = 'jpg';
        if (str_starts_with($rawBinary, "\x89PNG")) {
            $ext = 'png';
        } elseif (str_starts_with($rawBinary, "\xFF\xD8\xFF")) {
            $ext = 'jpg';
        }

        // 4. Obtain next frame basename from frame_counter mutex
        $basename  = $fm->getNextFrameBasenameFromDB();
        $filename  = $basename . '.' . $ext;

        $framesDirRel = rtrim($spw->getFramesDirRel(), '/');
        $relPath      = $framesDirRel . '/' . $filename;
        $absPath      = rtrim($projectRoot, '/') . '/public/' . ltrim($relPath, '/');

        $dir = dirname($absPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        if (file_put_contents($absPath, $rawBinary) === false) {
            throw new Exception("Failed to write snapshot to: {$absPath}");
        }

        // 5. Create formal map_run for this entity
        $note = "3D Snapshot from Mesh #" . ($meshId ?: 'custom');
        $mapRunId = $fm->createMapRun($entityType, $note);
        if (!$mapRunId) {
            throw new Exception("Failed to create map_run: " . $fm->getLastError());
        }

        // 6. Insert row into `frames` table
        $frameName = "3D Snapshot " . ($meshName ?? "Mesh #$meshId");
        $prompt    = "3D Viewport snapshot of mesh #" . ($meshId ?: '0');

        $stmtFrame = $pdo->prepare("
            INSERT INTO frames (map_run_id, name, filename, prompt, entity_type, entity_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmtFrame->execute([
            $mapRunId,
            $frameName,
            $relPath,
            $prompt,
            $entityType,
            $entityId ?: null
        ]);
        $newFrameId = (int)$pdo->lastInsertId();

        // 7. Map to the entity table via frames_2_{entity_type}
        if ($entityId > 0 && !empty($entityType)) {
            $mapTable = 'frames_2_' . $entityType;
            $tblCheck = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($mapTable));
            if ($tblCheck && $tblCheck->rowCount() > 0) {
                $stmtMap = $pdo->prepare("INSERT IGNORE INTO `{$mapTable}` (from_id, to_id) VALUES (?, ?)");
                $stmtMap->execute([$newFrameId, $entityId]);
            }
        }

        // Link to animatic_frames if requested from an animatic context
        $animaticId = (int)($_REQUEST['animatic_id'] ?? 0);
        if ($animaticId > 0) {
            $pdo->prepare("INSERT IGNORE INTO animatic_frames (animatic_id, frame_id, created_at) VALUES (?, ?, NOW())")
                ->execute([$animaticId, $newFrameId]);
        }

        echo json_encode([
            'success'      => true,
            'message'      => "Saved as SAGE Frame #{$newFrameId}",
            'frame_id'     => $newFrameId,
            'map_run_id'   => $mapRunId,
            'entity_type'  => $entityType,
            'entity_id'    => $entityId,
            'filename'     => $relPath,
            'url'          => '/' . $relPath
        ]);
        exit;
    }

    // ---------------------------------------------------------
    // ACTION: link_animatic
    // ---------------------------------------------------------
    if ($action === 'link_animatic') {
        $animaticId = (int)($_POST['animatic_id'] ?? 0);
        $meshId     = (int)($_POST['mesh_id'] ?? 0);

        if (!$animaticId || !$meshId) {
            throw new Exception("Both animatic_id and mesh_id are required.");
        }

        $stmt = $pdo->prepare("INSERT IGNORE INTO animatic_meshes (animatic_id, mesh_id, created_at) VALUES (?, ?, NOW())");
        $stmt->execute([$animaticId, $meshId]);

        echo json_encode(['success' => true, 'linked' => true]);
        exit;
    }

    throw new Exception("Unknown action: " . htmlspecialchars($action));

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
    exit;
}