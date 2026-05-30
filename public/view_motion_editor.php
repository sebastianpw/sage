<?php
// public/view_motion_editor.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\UI\Modules\MotionEditorModule;

$animaticId = (int)($_GET['animatic_id'] ?? 0);

if (!$animaticId) {
    die("Error: animatic_id is required.");
}

$module = new MotionEditorModule(['animatic_id' => $animaticId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0">
    <title>Motion Editor</title>
    <?= $eruda ?? '' ?>
    <style>body { margin: 0; overflow: hidden; background: #000; }</style>
</head>
<body>
    <?= $module->render() ?>
</body>
</html>