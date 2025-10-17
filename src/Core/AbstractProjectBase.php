<?php

namespace App\Core;

use App\Session\AbstractSessionManager;  // Make sure to import it here!

abstract class AbstractProjectBase
{
    private static ?self $instance = null;

    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();
        }
        return static::$instance;
    }

    abstract public function getProjectPath(): string;

    // Return type must be the same as in concrete class
    abstract public function getSessionManager(): AbstractSessionManager;
}
