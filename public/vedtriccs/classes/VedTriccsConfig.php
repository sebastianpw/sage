<?php
// public/vedtriccs/classes/VedTriccsConfig.php

class VedTriccsConfig
{
    const DEFAULT_ZOOM     = 60;
    const DEFAULT_FPS      = 30;
    const DEFAULT_DURATION = 120;

    public static function posInt(mixed $val, int $default): int
    {
        $v = (int)$val;
        return $v > 0 ? $v : $default;
    }

    public static function resolveAnimaticId(mixed $candidate): int
    {
        return max(0, (int)$candidate);
    }

    public static function getPyApiUrl(): string
    {
        $script = __DIR__ . '/../../../bash/pyapi_echo.sh';
        $url    = trim((string)shell_exec('sh ' . escapeshellarg($script)));
        return $url !== '' ? rtrim($url, '/') : 'http://127.0.0.1:8009';
    }
}
