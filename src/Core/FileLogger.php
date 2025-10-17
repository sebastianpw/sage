<?php

namespace App\Core;
use \App\Core\SpwBase;

class FileLogger extends AbstractLogger
{
    protected string $logDir;
    protected ?SpwBase $spw = null;

    public function __construct(?string $logDir = null)
    {
        $this->spw = SpwBase::getInstance();

        // Default log dir
        $this->logDir = $this->spw?->getProjectPath() . '/logs';

        // Override if provided
        if ($logDir !== null) {
            $this->logDir = $logDir;
        }

        if (!is_dir($this->logDir)) {
            if (!mkdir($this->logDir, 0777, true) && !is_dir($this->logDir)) {
                throw new \RuntimeException("Failed to create log directory: {$this->logDir}");
            }
        }
    }

    public function getLogDir(): string
    {
        return $this->logDir;
    }

    /**
     * Returns the full path of the log file to use.
     * Subclasses can override this to provide a custom file.
     */
    protected function getLogFileName(): string
    {
        return $this->logDir . '/log_' . date('Y-m-d') . '.log';
    }

    public function log(string $level, array $data): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $message = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $logLine = "[$timestamp] [$level] $message\n";

        $filePath = $this->getLogFileName(); // <- use method
        file_put_contents($filePath, $logLine, FILE_APPEND);
    }
}



