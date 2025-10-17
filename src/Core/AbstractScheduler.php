<?php
namespace App\Core;

abstract class AbstractScheduler
{
    protected \App\Core\SpwBase $spw;
    protected \App\Core\SchedulerFileLogger $logger;

    public function __construct()
    {
        $this->spw = \App\Core\SpwBase::getInstance();
        $this->logger = $this->spw->getSchedulerFileLogger();
    }

    abstract public function run(): void;

    protected function log(string $level, array $data): void
    {
        $this->logger->log($level, $data);
    }
}


