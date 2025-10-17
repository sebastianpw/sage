<?php

namespace App\Session;

class CliSessionManager extends AbstractSessionManager
{
    private string $sessionSavePath;

    public function __construct(string $path)
    {
        $this->sessionSavePath = $path;

        if (!is_dir($this->sessionSavePath)) {
            mkdir($this->sessionSavePath, 0777, true);
        }

        session_save_path($this->sessionSavePath);
    }

    public function startSession(?string $sessionId = null): string
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if ($sessionId !== null) {
            session_id($sessionId);
        }

        session_start();
        return session_id();
    }

    public function closeSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public function get(string $key): mixed
    {
        return $_SESSION[$key] ?? null;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }
}
