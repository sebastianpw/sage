<?php
namespace App\Core;

use PDO;
use Exception;

class AgentFactory {
    public static function create(string $modulePrefix, PDO $pdo): AiAgentModule {
        $prefix = strtolower(trim($modulePrefix));

        if ($prefix === 'sketches') {
            return new SketchesAgentModule($pdo);
        }
        if ($prefix === 'kg') {
            return new KgAgentModule($pdo);
        }

        // Future modules plug in here:
        // if ($prefix === 'characters') return new CharactersAgentModule($pdo);

        throw new Exception("Unknown agent module prefix: '{$modulePrefix}'");
    }
}