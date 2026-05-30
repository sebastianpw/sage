<?php

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\TaskCompletionTrackerDaemon;

$spw = \App\Core\SpwBase::getInstance();
$logger = $spw->getFileLogger();

$logger->log('INFO', ['Task completion tracker (one-shot) started']);

$daemon = new TaskCompletionTrackerDaemon(1);

// Run a single pass of comprehensive work (renew, release finished, cleanup)
$daemon->heartbeat->comprehensiveLockCleanup();
$daemon->checkRunningTasks();
$daemon->cleanupOldRecords();

$logger->log('INFO', ['Task completion tracker (one-shot) finished']);
