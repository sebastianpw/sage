<?php

class SketchesAgent extends \App\Core\AiAgentModule {
    public function getModulePrefix(): string { return 'sketches'; }

    protected function getDbSchemaInstructions(): string {
        return <<<EOT
1. "add_sketch": requires `name` (string), `description` (string), `prompt` (string).
2. "delete_sketch": requires `id` (integer).
EOT;
    }

    public function getCurrentStateContext(string $sessionId, int $userId): string {
        $stmt = $this->pdo->query("SELECT id, name, description FROM sketches_data WHERE session_id = '$sessionId'");
        return json_encode($stmt->fetchAll(\PDO::FETCH_ASSOC));
    }

    public function executeOperations(array $operations, string $sessionId, int $userId): array {
        $log = [];
        foreach ($operations as $op) {
            if ($op['action'] === 'add_sketch') {
                $stmt = $this->pdo->prepare("INSERT INTO sketches_data (name, description, prompt, session_id) VALUES (?, ?, ?, ?)");
                $stmt->execute([$op['name'], $op['description'], $op['prompt'], $sessionId]);
                $log[] = "Created sketch: {$op['name']}";
            }
            // ... handle delete
        }
        return $log;
    }
}


