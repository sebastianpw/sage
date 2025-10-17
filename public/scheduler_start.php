<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\TaskRunnerScheduler;
use App\Core\TaskHeartbeat;

// Optional: instantiate heartbeat (used inside scheduler loop)
$heartbeat = new TaskHeartbeat();

// Save PID to a file for foolproof stopping
$pidFile = __DIR__ . '/scheduler.pid';
file_put_contents($pidFile, getmypid());

// Instantiate scheduler
$scheduler = new TaskRunnerScheduler();
$scheduler->run(); // enters infinite loop


