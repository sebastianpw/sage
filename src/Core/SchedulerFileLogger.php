<?php

namespace App\Core;

class SchedulerFileLogger extends FileLogger
{
    protected function getLogFileName(): string
    {
        // Daily scheduler log file
        return $this->logDir . '/scheduler_' . date('Y-m-d') . '.log';
    }
}


