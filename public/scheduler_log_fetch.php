<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php'; // same folder

$logsDir = __DIR__ . '/../logs';
$file    = basename($_GET['file'] ?? '');
$logfile = $logsDir . '/' . $file;

if (!file_exists($logfile)) {
    echo "[Error] Log file not found: " . htmlspecialchars($file);
    exit;
}

// Efficiently read last 50 lines
function tailFile($filepath, $lines = 50) {
    $f = fopen($filepath, "r");
    if (!$f) return [];

    $buffer = '';
    $chunkSize = 4096;
    fseek($f, 0, SEEK_END);
    $pos = ftell($f);
    $lineCount = 0;
    $linesInBuffer = [];

    while ($pos > 0 && $lineCount < $lines) {
        $read = min($chunkSize, $pos);
        $pos -= $read;
        fseek($f, $pos);
        $chunk = fread($f, $read);
        $buffer = $chunk . $buffer;
        $linesInBuffer = explode("\n", $buffer);
        $lineCount = count($linesInBuffer) - 1;
    }

    fclose($f);
    return array_slice($linesInBuffer, -$lines);
}

$lines = tailFile($logfile, 50);
echo implode("\n", $lines);


