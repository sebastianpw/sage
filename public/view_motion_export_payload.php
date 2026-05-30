<?php
// public/view_motion_export_payload.php
// Exports a Motion Setup + Assets + Dummy Flight Data as a ZIP for Blender testing.

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

use App\Core\SpwBase;

// 1. Configuration
$animaticId = isset($_GET['id']) ? (int)$_GET['id'] : 530; 
$duration = 5.0; 
$fps = 30;

$spw = SpwBase::getInstance();
$pdo = $spw->getPDO();
$projectRoot = $spw->getProjectPath();
$publicRoot = $projectRoot . '/public/';

if ($animaticId <= 0) die("Invalid Animatic ID");

try {
    // 2. Fetch Data
    $stmtSetup = $pdo->prepare("SELECT * FROM motion_setups WHERE animatic_id = ? ORDER BY is_active DESC LIMIT 1");
    $stmtSetup->execute([$animaticId]);
    $setupRow = $stmtSetup->fetch(PDO::FETCH_ASSOC);

    if (!$setupRow) die("No Motion Setup found for Animatic #$animaticId. Please load it in the Motion Editor once to generate defaults.");

    $stmtLayers = $pdo->prepare("
        SELECT ml.*, 
               f.filename as frame_filename, 
               v.url as video_url,
               m.filename as mesh_filename
        FROM motion_layers ml
        LEFT JOIN frames f ON ml.frame_id = f.id
        LEFT JOIN videos v ON ml.video_id = v.id
        LEFT JOIN meshes m ON ml.mesh_id = m.id
        WHERE ml.motion_setup_id = ?
        ORDER BY ml.z_index ASC
    ");
    $stmtLayers->execute([$setupRow['id']]);
    $layers = $stmtLayers->fetchAll(PDO::FETCH_ASSOC);

    // 3. Construct JSON Payload
    $jsonPayload = [
        'animatic_id' => $animaticId,
        'setup' => [
            'environment' => json_decode($setupRow['environment_config'] ?? '{}', true),
            'layers' => []
        ],
        'flight_data' => []
    ];

    $filesToZip = [];

    foreach ($layers as $l) {
        $layerConfig = json_decode($l['layer_config'] ?? '{}', true);
        
        $filename = null;
        if ($l['frame_filename']) {
            $filename = $l['frame_filename'];
        } elseif ($l['video_url']) {
            $filename = $l['video_url'];
        } elseif ($l['mesh_filename']) {
            // FIX: Check if path already contains 'meshes/' to avoid double prefixing
            if (strpos($l['mesh_filename'], 'meshes/') === false && strpos($l['mesh_filename'], '/meshes/') === false) {
                $filename = 'meshes/' . $l['mesh_filename'];
            } else {
                $filename = $l['mesh_filename'];
            }
        }

        // Clean path (remove leading slashes to make it relative to public/)
        $cleanPath = $filename ? ltrim($filename, '/') : null;

        // Verify and Add to Zip List
        if ($cleanPath) {
            $fullPath = $publicRoot . $cleanPath;
            if (file_exists($fullPath)) {
                $filesToZip[$cleanPath] = $fullPath;
            } else {
                // Debug note in JSON if file missing
                $layerConfig['debug_error'] = "File missing on server: $fullPath";
            }
        }

        $jsonPayload['setup']['layers'][] = [
            'id' => (string)$l['id'],
            'role' => $l['role'],
            'z_index' => (int)$l['z_index'],
            'filename' => $cleanPath, 
            'config' => $layerConfig
        ];
    }

    // 4. Generate Dummy Flight Data
    $totalFrames = $duration * $fps;
    for ($i = 0; $i < $totalFrames; $i++) {
        $time = $i / $fps;
        $frameData = [
            'time' => $time,
            'layers' => []
        ];

        foreach ($layers as $l) {
            $lConf = json_decode($l['layer_config'] ?? '{}', true);
            $swaySpeed = $lConf['swaySpeed'] ?? 1.0;
            $swayAmp = $lConf['swayAmp'] ?? 0.0;
            
            $transform = [];

            if ($l['role'] === 'background') {
                $scrollSpeed = $jsonPayload['setup']['environment']['params']['scrollSpeed'] ?? 4.0;
                $transform['rotX'] = - ($time * $scrollSpeed * 0.01); // 0.01 factor from JS
            } else {
                $transform['x'] = sin($time * $swaySpeed) * $swayAmp;
                if ($swayAmp > 0) {
                    $transform['rotY'] = -sin($time * $swaySpeed) * 0.3;
                }
                $transform['rotZ'] = $lConf['rotZ'] ?? 0;
            }

            $frameData['layers'][(string)$l['id']] = $transform;
        }
        $jsonPayload['flight_data'][] = $frameData;
    }

    // 5. Create ZIP
    $zipFile = sys_get_temp_dir() . "/sage_export_{$animaticId}_" . time() . ".zip";
    $zip = new ZipArchive();
    
    if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
        die("Cannot create zip file");
    }

    // Add Payload JSON
    $zip->addFromString('payload.json', json_encode($jsonPayload, JSON_PRETTY_PRINT));

    // Add Assets
    foreach ($filesToZip as $zipPath => $fsPath) {
        $zip->addFile($fsPath, $zipPath);
    }

    $zip->close();

    // 6. Download
    if (file_exists($zipFile)) {
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="sage_motion_pkg_' . $animaticId . '.zip"');
        header('Content-Length: ' . filesize($zipFile));
        readfile($zipFile);
        unlink($zipFile);
        exit;
    } else {
        die("Error: Zip file was not created.");
    }

} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
