<?php
// load_root.php

// Path to the share file
$shareFile = __DIR__ . '/../.env.local';

if (!file_exists($shareFile)) {
    throw new RuntimeException(".env.local file not found at $shareFile");
}

// Read the file contents
$content = file_get_contents($shareFile);

// Parse PROJECT_ROOT
if (preg_match('/^\s*PROJECT_ROOT\s*=\s*(.+)$/m', $content, $matches)) {
    $root = trim($matches[1], "\"' \t\n\r");
    if (!defined('PROJECT_ROOT')) {
        define('PROJECT_ROOT', $root);
    }
} else {
    throw new RuntimeException("PROJECT_ROOT not found in .env.local");
}

// Parse FRAMES_ROOT (must exist)
if (preg_match('/^\s*FRAMES_ROOT\s*=\s*(.+)$/m', $content, $matches)) {
    $framesRoot = trim($matches[1], "\"' \t\n\r");
    if (!defined('FRAMES_ROOT')) {
        define('FRAMES_ROOT', $framesRoot);
    }
} else {
    throw new RuntimeException("FRAMES_ROOT not found in .env.local");
}

// Now PROJECT_ROOT and FRAMES_ROOT are available to any script that includes this file


