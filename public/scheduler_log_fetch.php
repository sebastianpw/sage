<?php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php'; // same folder

$logsDir = __DIR__ . '/../logs';
$file    = basename($_GET['file'] ?? '');
$logfile = $logsDir . '/' . $file;

// ✅ Ensure the logs directory exists
if (!is_dir($logsDir)) {
    echo "[Error] Logs directory missing: " . htmlspecialchars($logsDir);
    exit;
}

// ✅ Verify the log file is a *regular file* (not dir or missing)
if (!is_file($logfile)) {
    echo "[Info] No valid log file found yet.";
    exit;
}

// ✅ Efficiently read last N lines, safely
function tailFile($filepath, $lines = 50) {
    $f = @fopen($filepath, "r");
    if (!$f) {
        return ["[Error] Cannot open log file: " . htmlspecialchars(basename($filepath))];
    }

    $buffer = '';
    $chunkSize = 4096;

    if (fseek($f, 0, SEEK_END) === -1) {
        fclose($f);
        return ["[Error] Unable to seek in file."];
    }

    $pos = ftell($f);
    $lineCount = 0;
    $linesInBuffer = [];

    while ($pos > 0 && $lineCount < $lines) {
        $read = min($chunkSize, $pos);
        $pos -= $read;

        if (fseek($f, $pos) === -1) break;
        $chunk = @fread($f, $read);
        if ($chunk === false) break;

        $buffer = $chunk . $buffer;
        $linesInBuffer = explode("\n", $buffer);
        $lineCount = count($linesInBuffer) - 1;
    }

    fclose($f);

    // ✅ Trim trailing empty line if exists
    $result = array_filter(array_slice($linesInBuffer, -$lines), fn($l) => trim($l) !== '');
    return $result ?: ["[Info] Log file is empty."];
}

$lines = tailFile($logfile, 50);
echo implode("\n", $lines);
