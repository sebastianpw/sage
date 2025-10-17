<?php
// babylon_models.php
require __DIR__ . '/bootstrap.php'; // adjust to locate bootstrap.php
header('Content-Type: application/json; charset=utf-8');

$projectRoot = PROJECT_ROOT ?? dirname(__DIR__, 1); // fallback
$modelsRel = '/public/models';
$modelsDir = rtrim($projectRoot, '/') . $modelsRel;

$files = [];
if (is_dir($modelsDir)) {
    $dir = new DirectoryIterator($modelsDir);
    foreach ($dir as $file) {
        if ($file->isFile()) {
            $ext = strtolower(pathinfo($file->getFilename(), PATHINFO_EXTENSION));
            if (in_array($ext, ['glb','gltf'])) {
                $files[] = [
                    'id' => rawurlencode($file->getFilename()), // id used by viewer
                    'name' => $file->getFilename(),
                    'path' => '/models/' . $file->getFilename(), // relative URL
                    'url'  => '/models/' . $file->getFilename(),
                    'size' => $file->getSize(),
                    'mtime'=> $file->getMTime()
                ];
            }
        }
    }
}

// sort by name
usort($files, function($a,$b){ return strcasecmp($a['name'],$b['name']); });

echo json_encode($files, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
