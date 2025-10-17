<?php
declare(strict_types=1);

/**
 * Task Completion Tracker Daemon - Start Script
 * 
 * Starts the completion tracker daemon that monitors running tasks
 * and finalizes them when they complete.
 */

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\TaskCompletionTrackerDaemon;

// Save PID to a file for foolproof stopping
$pidFile = __DIR__ . '/completion_tracker.pid';
file_put_contents($pidFile, getmypid());

// Instantiate and run the daemon
$tracker = new TaskCompletionTrackerDaemon(1); // Check every 1 second (aggressive)
$tracker->run(); // enters infinite loop
