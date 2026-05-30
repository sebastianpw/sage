<?php
// public/view_motani_add.php
// Helper tool to link Frames, Videos, or Meshes to Animatics manually.
// Populates: animatic_frames, animatic_videos, animatic_meshes

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $animaticId = (int)($_POST['animatic_id'] ?? 0);
    $frameId = (int)($_POST['frame_id'] ?? 0);
    $videoId = (int)($_POST['video_id'] ?? 0);
    $meshId  = (int)($_POST['mesh_id'] ?? 0);

    if ($animaticId > 0) {
        
        // 1. Link Frame
        if ($frameId > 0) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM frames WHERE id = ?");
                $stmt->execute([$frameId]);
                if ($stmt->fetch()) {
                    $stmtIns = $pdo->prepare("INSERT IGNORE INTO animatic_frames (animatic_id, frame_id, created_at) VALUES (?, ?, NOW())");
                    $stmtIns->execute([$animaticId, $frameId]);
                    if ($stmtIns->rowCount() > 0) $message .= "Linked Frame #$frameId.<br>";
                    else $message .= "Frame #$frameId already linked.<br>";
                } else $error .= "Frame #$frameId not found.<br>";
            } catch (Exception $e) { $error .= "Frame Error: " . $e->getMessage() . "<br>"; }
        }

        // 2. Link Video
        if ($videoId > 0) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM videos WHERE id = ?");
                $stmt->execute([$videoId]);
                if ($stmt->fetch()) {
                    $stmtIns = $pdo->prepare("INSERT IGNORE INTO animatic_videos (animatic_id, video_id, created_at) VALUES (?, ?, NOW())");
                    $stmtIns->execute([$animaticId, $videoId]);
                    if ($stmtIns->rowCount() > 0) $message .= "Linked Video #$videoId.<br>";
                    else $message .= "Video #$videoId already linked.<br>";
                } else $error .= "Video #$videoId not found.<br>";
            } catch (Exception $e) { $error .= "Video Error: " . $e->getMessage() . "<br>"; }
        }

        // 3. Link Mesh (3D Model)
        if ($meshId > 0) {
            try {
                $stmt = $pdo->prepare("SELECT id FROM meshes WHERE id = ?");
                $stmt->execute([$meshId]);
                if ($stmt->fetch()) {
                    // Check if link table exists first (sanity check)
                    $tblCheck = $pdo->query("SHOW TABLES LIKE 'animatic_meshes'");
                    if ($tblCheck->rowCount() > 0) {
                        $stmtIns = $pdo->prepare("INSERT IGNORE INTO animatic_meshes (animatic_id, mesh_id, created_at) VALUES (?, ?, NOW())");
                        $stmtIns->execute([$animaticId, $meshId]);
                        if ($stmtIns->rowCount() > 0) $message .= "Linked Mesh #$meshId.<br>";
                        else $message .= "Mesh #$meshId already linked.<br>";
                    } else {
                        $error .= "Table 'animatic_meshes' missing. Run database update.<br>";
                    }
                } else $error .= "Mesh #$meshId not found.<br>";
            } catch (Exception $e) { $error .= "Mesh Error: " . $e->getMessage() . "<br>"; }
        }
        
        if ($frameId === 0 && $videoId === 0 && $meshId === 0) {
            $error .= "Please provide at least one ID (Frame, Video, or Mesh).<br>";
        }
    } else {
        $error .= "Animatic ID is required.<br>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Animatic Asset Linker</title>
    <link rel="stylesheet" href="/css/base.css">
    <style>
        body { padding: 20px; max-width: 600px; margin: 0 auto; }
        .success { color: #4caf50; background: rgba(76,175,80,0.1); padding: 10px; border-radius: 4px; margin-bottom: 10px; }
        .error { color: #f44336; background: rgba(244,67,54,0.1); padding: 10px; border-radius: 4px; margin-bottom: 10px; }
    </style>
</head>
<body>

    <h1>Link Assets to Animatic</h1>
    <p class="text-muted">Manually populate source tables for the Motion Module.</p>

    <?php if ($message): ?>
        <div class="success"><?= $message ?></div>
    <?php endif; ?>
    
    <?php if ($error): ?>
        <div class="error"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST" class="form-container">
        <div class="form-group">
            <label>Animatic ID (Target)</label>
            <input type="number" name="animatic_id" class="form-control" placeholder="ID" required>
        </div>

        <div class="form-group">
            <label>Frame ID (Image)</label>
            <input type="number" name="frame_id" class="form-control" placeholder="ID (Background/Sprite)">
        </div>

        <div class="form-group">
            <label>Video ID (Video)</label>
            <input type="number" name="video_id" class="form-control" placeholder="ID (Character/Plane)">
        </div>

        <div class="form-group">
            <label>Mesh ID (3D Model)</label>
            <input type="number" name="mesh_id" class="form-control" placeholder="ID (GLB/GLTF)">
            <small class="text-muted">Requires 'animatic_meshes' table.</small>
        </div>

        <button type="submit" class="btn btn-primary">Link Assets</button>
    </form>
    
    <div style="margin-top: 20px;">
        <a href="/view_video_admin.php" class="btn btn-secondary">Back to Video Admin</a>
    </div>

</body>
</html>