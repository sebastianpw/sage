<?php
// save_storyboard_order.php
require "error_reporting.php";
require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

$spw = \App\Core\SpwBase::getInstance();

header('Content-Type: application/json; charset=utf-8');

/**
 * Accepts POST param 'dir' which is a web-relative path under document root.
 * Uses $_SERVER['DOCUMENT_ROOT'] to translate into absolute filesystem paths.
 */

// default web-relative frames dir from bootstrap
$defaultFramesDirRel = '/' . $framesDirRel;
if ($defaultFramesDirRel === '') $defaultFramesDirRel = '/';

$requestedDir = isset($_POST['dir']) ? trim((string)$_POST['dir']) : '';
if ($requestedDir !== '') {
    $requestedDir = '/' . ltrim($requestedDir, '/');
    $requestedDir = rtrim($requestedDir, '/');
} else {
    $requestedDir = '';
}


// WE MAY NEVER EVER CHANGE ANYTHING IN THE MAIN PROJECT FRAMES DIR!!!
if ($requestedDir == $defaultFramesDirRel) {
    echo json_encode(['success'=>false, 'message'=>'write access denied']); exit;
}

if (str_contains($requestedDir, 'frames_starlightguardians')) {
    echo json_encode(['success'=>false, 'message'=>'write access denied']); exit;
}


// basic sanitize
if ($requestedDir !== '' && (strpos($requestedDir, '..') !== false || strpos($requestedDir, "\0") !== false)) {
    $requestedDir = '';
}

$docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
if ($docRoot === '') {
    // fallback; prefer not to rely on SpwBase in web context, but if docroot missing use fallback
    $docRoot = rtrim($spw->getProjectPath() . '/public', '/');
}

// choose final web-rel dir
$useFramesDirRel = $defaultFramesDirRel;
if ($requestedDir !== '') {
    $candidateAbs = realpath($docRoot . $requestedDir);
    if ($candidateAbs !== false && is_dir($candidateAbs)) {
        $docPrefix = rtrim(realpath($docRoot), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $candPrefix = rtrim($candidateAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (strpos($candPrefix, $docPrefix) === 0) {
            // set web-rel path preserving leading slash
            $useFramesDirRel = $requestedDir;
        }
    }
}

if ($useFramesDirRel === '') $useFramesDirRel = '/';
$useFramesDirAbs = rtrim($docRoot, '/') . $useFramesDirRel;
$useFramesDirAbs = rtrim($useFramesDirAbs, '/');

$orderFile = $useFramesDirAbs . '/storyboard_order.json';
$action = $_POST['action'] ?? '';

if ($action === 'save') {
    $orderJson = $_POST['order'] ?? '[]';
    $order = json_decode($orderJson, true);
    if (!is_array($order)) { echo json_encode(['success'=>false, 'message'=>'Invalid order data']); exit; }

    // whitelist filenames in the directory
    $allowed = [];
    if (is_dir($useFramesDirAbs)) {
        foreach (scandir($useFramesDirAbs) as $f) {
            if ($f === '.' || $f === '..') continue;
            if (is_file($useFramesDirAbs . '/' . $f)) $allowed[] = $f;
        }
    }

    $filtered = [];
    foreach ($order as $name) {
        if (in_array($name, $allowed, true)) $filtered[] = $name;
    }

    $res = @file_put_contents($orderFile, json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if ($res === false) {
        echo json_encode(['success'=>false, 'message'=>'Unable to write order file. Check filesystem permissions.']);
    } else {
        echo json_encode(['success'=>true]);
    }
    exit;
}

if ($action === 'delete') {
    $filename = $_POST['filename'] ?? '';
    if (!preg_match('/^[a-zA-Z0-9_\-\.\s\(\)]+$/', $filename)) {
        echo json_encode(['success'=>false, 'message'=>'Invalid filename']); exit;
    }
    $path = $useFramesDirAbs . '/' . $filename;
    if (!file_exists($path)) {
        echo json_encode(['success'=>false, 'message'=>'File not found']); exit;
    }
    $ok = @unlink($path);
    if ($ok) {
        if (file_exists($orderFile)) {
            $j = json_decode(file_get_contents($orderFile), true);
            if (is_array($j)) {
                $j = array_values(array_filter($j, function($v) use ($filename){ return $v !== $filename; }));
                @file_put_contents($orderFile, json_encode($j, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
            }
        }
        echo json_encode(['success'=>true]);
    } else {
        echo json_encode(['success'=>false, 'message'=>'Unable to delete file (permissions?)']);
    }
    exit;
}

if ($action === 'prefix') {
    $orderJson = $_POST['order'] ?? '[]';
    $order = json_decode($orderJson, true);
    if (!is_array($order)) { echo json_encode(['success'=>false, 'message'=>'Invalid order data']); exit; }

    $errors = [];
    $renamedFiles = [];
    
    foreach ($order as $idx => $name) {
        $src = $useFramesDirAbs . '/' . $name;
        if (!file_exists($src)) { 
            $errors[] = "missing: $name"; 
            continue; 
        }
        
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);
        
        // Remove any existing numeric prefix (1-3 digits followed by underscore)
        $cleanBase = preg_replace('/^\d{1,3}_/', '', $base);
        
        // Use three-digit prefix
        $prefix = str_pad($idx+1, 3, '0', STR_PAD_LEFT);
        $newName = $prefix . '_' . $cleanBase . '.' . $ext;
        $dst = $useFramesDirAbs . '/' . $newName;
        
        // Only use unique ID if there's an actual conflict with a different file
        if (file_exists($dst) && $src !== $dst) {
            $errors[] = "cannot rename: destination already exists: $newName";
            continue;
        }
        
        $ok = @rename($src, $dst);
        if (!$ok) {
            $errors[] = "rename failed: $name -> $newName";
        } else {
            $renamedFiles[] = $newName;
        }
    }

    if (count($errors)) {
        echo json_encode(['success'=>false, 'message'=>implode('; ', $errors)]);
    } else {
        // Update order file with the new filenames in the correct order
        file_put_contents($orderFile, json_encode($renamedFiles, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        echo json_encode(['success'=>true]);
    }
    exit;
}


echo json_encode(['success'=>false, 'message'=>'Unknown action']);
exit;
