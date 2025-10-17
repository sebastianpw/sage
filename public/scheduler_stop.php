<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php'; 
require __DIR__ . '/env_locals.php';

// Path to PID file
$pidFile = __DIR__ . '/scheduler.pid';

// Function to stop a process by PID
function stopProcess(int $pid): bool {
    // Check if process exists
    if (posix_kill($pid, 0)) {
        echo "Stopping scheduler process with PID: $pid\n";
        exec("kill $pid");
        return true;
    }
    return false;
}

// Try stopping via PID file first
if (file_exists($pidFile)) {
    $pid = (int)file_get_contents($pidFile);
    if ($pid > 0) {
        if (stopProcess($pid)) {
            unlink($pidFile); // remove PID file after stopping
            echo "Scheduler stopped via PID file.\n";
            exit(0);
        } else {
            echo "PID file found but process not running: $pid\n";
            unlink($pidFile);
        }
    }
}

// Fallback: search via pgrep
echo "Trying to find scheduler via pgrep...\n";
exec("pgrep -f 'scheduler_start.php'", $pids);

if (!empty($pids)) {
    foreach ($pids as $pid) {
        stopProcess((int)$pid);
    }
    echo "Scheduler stopped via pgrep.\n";
} else {
    echo "No scheduler process found.\n";
}


