<?php

namespace App\Session;

abstract class AbstractSessionManager
{
    abstract public function startSession(?string $sessionId = null): string;
    abstract public function closeSession(): void;
    abstract public function get(string $key): mixed;
    abstract public function set(string $key, mixed $value): void;
}
