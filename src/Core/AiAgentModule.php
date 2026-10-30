<?php
namespace App\Core;

abstract class AiAgentModule {
    protected \PDO $pdo;

    public function __construct(\PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * E.g., 'aim', 'sketches', 'kg'
     * Used to identify this module in the unified agent_chat_sessions table.
     */
    abstract public function getModulePrefix(): string;

    /**
     * Returns the specific DB schema, available operations, and rules.
     */
    abstract protected function getDbSchemaInstructions(): string;

    /**
     * Returns the current state of the database for this session to feed the AI's context.
     */
    abstract public function getCurrentStateContext(string $sessionId, int $userId): string;

    /**
     * Parses and executes the operations array returned by the AI.
     * @return array Log of executed operations to display in the UI.
     */
    abstract public function executeOperations(array $operations, string $sessionId, int $userId): array;

    /**
     * The universal system prompt wrapper.
     */
    public function getSystemPrompt(string $customLore = ''): string {
        $schema = $this->getDbSchemaInstructions();
        return <<<EOT
You are an AI Database Agent handling a specific module.
--- CONTEXT / LORE ---
{$customLore}

--- DATABASE SCHEMA & RULES ---
You MUST manipulate the database by outputting a SINGLE, VALID JSON object at the end of your response.
Your entire conversational reply MUST be inside the "message" key. 
Do NOT output any markdown text outside of the JSON block.

REQUIRED JSON FORMAT:
```json
{
  "message": "Your conversational response addressing the user. Talk here, nowhere else.",
  "operations": [
    { "action": "OPERATION_NAME", "param1": "value1" }
  ]
}
```

Rules:
- You MUST output the JSON block containing "message" and "operations". 
- If no DB changes are needed, leave "operations" as an empty array [].
- If the user asks to find, search, or show existing items, use the search operation!
- Every object in the operations array MUST have an "action" key.

AVAILABLE OPERATIONS:
{$schema}
EOT;
    }
}