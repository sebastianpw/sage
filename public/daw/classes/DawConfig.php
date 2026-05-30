<?php
// daw/classes/DawConfig.php
// Static configuration for SAGE DAW

class DawConfig
{
    /**
     * All valid audio entity types.
     */
    public static array $audioEntities = [
        'audio_ambiences',
        'audio_cues',
        'audio_dialogue_lines',
        'audio_foleys',
        'audio_fxsounds',
        'audio_themes',
    ];

    /**
     * Validate and normalise an entity type string.
     * Returns a safe entity name or the default fallback.
     */
    public static function resolveEntity(string $candidate, string $default = 'audio_cues'): string
    {
        return in_array($candidate, self::$audioEntities, true) ? $candidate : $default;
    }
}
