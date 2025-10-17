<?php

namespace App\Core;

abstract class AbstractLogger implements ILogger
{
    public const LEVEL_DEBUG   = 0;
    public const LEVEL_INFO    = 1;
    public const LEVEL_WARNING = 2;
    public const LEVEL_ERROR   = 3;

    protected int $appLogLevel = self::LEVEL_DEBUG;

    public function setAppLogLevel(int $level): void
    {
        $this->appLogLevel = $level;
    }

    protected function shouldLog(int $level): bool
    {
        return $level >= $this->appLogLevel;
    }

    public function debug(array $data): void
    {
        if ($this->shouldLog(self::LEVEL_DEBUG)) {
            $this->log('DEBUG', $data);
        }
    }

    public function info(array $data): void
    {
        if ($this->shouldLog(self::LEVEL_INFO)) {
            $this->log('INFO', $data);
        }
    }

    public function warning(array $data): void
    {
        if ($this->shouldLog(self::LEVEL_WARNING)) {
            $this->log('WARNING', $data);
        }
    }

    public function error(array $data): void
    {
        if ($this->shouldLog(self::LEVEL_ERROR)) {
            $this->log('ERROR', $data);
        }
    }

    abstract public function log(string $level, array $data): void;
}



