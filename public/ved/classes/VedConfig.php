<?php
// public/ved/classes/VedConfig.php
// Static configuration for SAGE VED — Video Edit DAW

class VedConfig
{
    /**
     * Timeline unit: seconds (not beats).
     * Default pixels-per-second zoom.
     */
    const DEFAULT_ZOOM    = 60;   // px per second
    const DEFAULT_FPS     = 30;
    const DEFAULT_DURATION = 120; // seconds

    /**
     * Validate and normalise a positive integer, returning a safe fallback.
     */
    public static function posInt(mixed $val, int $default): int
    {
        $v = (int)$val;
        return $v > 0 ? $v : $default;
    }

    /**
     * Sanitise animatic_id from request.
     */
    public static function resolveAnimaticId(mixed $candidate): int
    {
        return max(0, (int)$candidate);
    }
}