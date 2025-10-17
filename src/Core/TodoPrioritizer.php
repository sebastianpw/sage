<?php

namespace App\Core;

/**
 * TodoPrioritizer (Refactored - Decoupled)
 * 
 * Now uses AIProvider for all AI communication.
 * Focuses purely on task analysis and prioritization logic.
 */
class TodoPrioritizer
{
    private $spw;
    private $pdo;
    private AIProvider $aiProvider;
    private $fileLogger;
    
    // Default model for task prioritization
    private const DEFAULT_MODEL = 'qwen/qwen3-32b';//'qwen2.5-coder-32b-instruct';
    
    public function __construct(?AIProvider $aiProvider = null, $fileLogger = null)
    {
        $this->spw = \App\Core\SpwBase::getInstance();
        $this->pdo = $this->spw->getSysPDO();
        $this->aiProvider = $aiProvider ?? new AIProvider($fileLogger);
        $this->fileLogger = $fileLogger;
    }

    /**
     * Analyze all tasks and suggest priority reordering
     */
    public function analyzeTasks(): array
    {
        try {
            // Get all tasks
            $stmt = $this->pdo->query("SELECT * FROM sage_todos ORDER BY `order` ASC");
            $tasks = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            if (empty($tasks)) {
                return ['error' => 'No tasks found'];
            }

            // Build analysis prompt
            $prompt = $this->buildAnalysisPrompt($tasks);
            
            // Get model from env or use default
            $model = getenv('POLLINATIONS_MODEL') ?: self::DEFAULT_MODEL;
            
            // Call AI via AIProvider
            $systemPrompt = 'You are a technical project manager specializing in AI/ML systems. Always return valid JSON responses.';
            $response = $this->aiProvider->sendPrompt($model, $prompt, $systemPrompt);
            
            // Parse response
            $analysis = $this->parseAnalysisResponse($response);
            
            if ($this->fileLogger) {
                $this->fileLogger->info(['Task prioritization completed' => [
                    'task_count' => count($tasks),
                    'suggestions' => count($analysis['suggestions'] ?? [])
                ]]);
            }
            
            return $analysis;
            
        } catch (\Exception $e) {
            if ($this->fileLogger) {
                $this->fileLogger->error(['Task prioritization failed' => [
                    'error' => $e->getMessage()
                ]]);
            }
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Apply priority changes suggested by AI
     */
    public function applyPriorityChanges(array $suggestions): array
    {
        $results = [];
        $applied = 0;

        try {
            $this->pdo->beginTransaction();

            foreach ($suggestions as $idx => $suggestion) {
                $targetId = null;
                $newOrder = null;

                // validate new_order
                if (isset($suggestion['new_order'])) {
                    $newOrder = (int)$suggestion['new_order'];
                    if ($newOrder < 0) {
                        $results[] = [
                            'index' => $idx,
                            'status' => 'failed',
                            'reason' => 'invalid new_order'
                        ];
                        continue;
                    }
                } else {
                    $results[] = [
                        'index' => $idx,
                        'status' => 'failed',
                        'reason' => 'missing new_order'
                    ];
                    continue;
                }

                // Preferred: explicit id
                if (!empty($suggestion['id'])) {
                    $candidateId = (int)$suggestion['id'];
                    $check = $this->pdo->prepare("SELECT id FROM sage_todos WHERE id = ?");
                    $check->execute([$candidateId]);
                    $found = $check->fetchColumn();
                    if ($found) {
                        $targetId = $candidateId;
                    } else {
                        // Log and fallback later
                        if ($this->fileLogger) {
                            $this->fileLogger->warning(['applyPriorityChanges' => [
                                'msg' => 'suggested id not found, will attempt fallback by order',
                                'suggested_id' => $candidateId
                            ]]);
                        }
                    }
                }

                // Fallback: if 'old_order' is provided, map it -> id
                if ($targetId === null && isset($suggestion['old_order'])) {
                    $oldOrder = (int)$suggestion['old_order'];
                    $map = $this->pdo->prepare("SELECT id FROM sage_todos WHERE `order` = ? LIMIT 1");
                    $map->execute([$oldOrder]);
                    $targetId = $map->fetchColumn() ?: null;
                    if ($targetId === null) {
                        $results[] = [
                            'index' => $idx,
                            'status' => 'failed',
                            'reason' => "no task with order={$oldOrder}"
                        ];
                        continue;
                    }
                }

                // Second fallback: maybe the AI used order number where it intended id
                if ($targetId === null && !empty($suggestion['id'])) {
                    $maybeOrder = (int)$suggestion['id'];
                    $map = $this->pdo->prepare("SELECT id FROM sage_todos WHERE `order` = ? LIMIT 1");
                    $map->execute([$maybeOrder]);
                    $targetId = $map->fetchColumn() ?: null;
                    if ($targetId !== null && $this->fileLogger) {
                        $this->fileLogger->info(['applyPriorityChanges' => [
                            'msg' => 'interpreted suggestion id as order and mapped to real id',
                            'input_value' => $suggestion['id'],
                            'mapped_id' => $targetId
                        ]]);
                    }
                }

                if ($targetId === null) {
                    $results[] = [
                        'index' => $idx,
                        'status' => 'failed',
                        'reason' => 'could not determine target id'
                    ];
                    continue;
                }

                // Lock and read current order of the target row
                $sel = $this->pdo->prepare("SELECT `order` FROM sage_todos WHERE id = ? FOR UPDATE");
                $sel->execute([$targetId]);
                $currentOrder = $sel->fetchColumn();

                // If row disappeared or no order found (shouldn't happen)
                if ($currentOrder === false) {
                    $results[] = [
                        'id' => (int)$targetId,
                        'status' => 'failed',
                        'reason' => 'target row not found'
                    ];
                    continue;
                }
                $currentOrder = (int)$currentOrder;

                // If nothing to do
                if ($currentOrder === $newOrder) {
                    $results[] = [
                        'id' => (int)$targetId,
                        'status' => 'skipped',
                        'reason' => 'already at desired order',
                        'current_order' => $currentOrder
                    ];
                    continue;
                }

                // Make room: shift the affected range
                if ($newOrder < $currentOrder) {
                    // Moving up: increment others in [newOrder, currentOrder-1]
                    $shiftStmt = $this->pdo->prepare(
                        "UPDATE sage_todos
                         SET `order` = `order` + 1
                         WHERE `order` >= ? AND `order` < ?"
                    );
                    $shiftStmt->execute([$newOrder, $currentOrder]);
                } else {
                    // Moving down: decrement others in (currentOrder, newOrder]
                    $shiftStmt = $this->pdo->prepare(
                        "UPDATE sage_todos
                         SET `order` = `order` - 1
                         WHERE `order` <= ? AND `order` > ?"
                    );
                    $shiftStmt->execute([$newOrder, $currentOrder]);
                }

                // Now set the target to newOrder
                $update = $this->pdo->prepare("UPDATE sage_todos SET `order` = ? WHERE id = ?");
                $success = $update->execute([$newOrder, $targetId]);

                if ($success) {
                    $applied++;
                    $results[] = [
                        'id' => (int)$targetId,
                        'status' => 'updated',
                        'old_order' => $currentOrder,
                        'new_order' => $newOrder
                    ];
                } else {
                    $results[] = [
                        'id' => (int)$targetId,
                        'status' => 'failed',
                        'reason' => 'update statement failed'
                    ];
                }
            } // foreach suggestions

            $this->pdo->commit();

            if ($this->fileLogger) {
                $this->fileLogger->info(['Priority changes applied' => [
                    'total_suggestions' => count($suggestions),
                    'applied' => $applied
                ]]);
            }

            return [
                'success' => true,
                'applied' => $applied,
                'total' => count($suggestions),
                'results' => $results
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            if ($this->fileLogger) {
                $this->fileLogger->error(['Failed to apply priority changes' => [
                    'error' => $e->getMessage()
                ]]);
            }
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get immediate priority tasks (order 1-10)
     */
    public function getImmediateTasks(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM sage_todos WHERE `order` <= 10 ORDER BY `order` ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Identify blocking tasks
     */
    public function identifyBlockingTasks(): array
    {
        $tasks = $this->getAllTasks();
        
        $prompt = "Analyze these tasks and identify which ones are blocking others or are critical infrastructure issues. Return only task IDs as JSON array:\n\n";
        $prompt .= $this->formatTasksForPrompt($tasks);
        $prompt .= "\n\nReturn format: {\"blocking_tasks\": [1, 5, 67], \"reason\": \"These tasks prevent other work\"}";
        
        $model = getenv('POLLINATIONS_MODEL') ?: self::DEFAULT_MODEL;
        $systemPrompt = 'You are a technical project manager specializing in AI/ML systems. Always return valid JSON responses.';
        
        try {
            $response = $this->aiProvider->sendPrompt($model, $prompt, $systemPrompt);
            return $this->parseBlockingResponse($response);
        } catch (\Exception $e) {
            if ($this->fileLogger) {
                $this->fileLogger->error(['identifyBlockingTasks failed' => [
                    'error' => $e->getMessage()
                ]]);
            }
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Suggest next 5 tasks to work on
     */
    public function suggestNextTasks(int $count = 5): array
    {
        $tasks = $this->getAllTasks();
        
        $prompt = "Based on these tasks, suggest the top {$count} tasks to work on next, considering dependencies, impact, and urgency:\n\n";
        $prompt .= $this->formatTasksForPrompt($tasks);
        $prompt .= "\n\nReturn format: {\"suggested_tasks\": [{\"id\": 1, \"title\": \"task name\", \"reason\": \"why this task\"}]}";
        
        $model = getenv('POLLINATIONS_MODEL') ?: self::DEFAULT_MODEL;
        $systemPrompt = 'You are a technical project manager specializing in AI/ML systems. Always return valid JSON responses.';
        
        try {
            $response = $this->aiProvider->sendPrompt($model, $prompt, $systemPrompt);
            return $this->parseNextTasksResponse($response);
        } catch (\Exception $e) {
            if ($this->fileLogger) {
                $this->fileLogger->error(['suggestNextTasks failed' => [
                    'error' => $e->getMessage()
                ]]);
            }
            return ['error' => $e->getMessage()];
        }
    }

    // --- Private methods ---

    private function getAllTasks(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM sage_todos ORDER BY `order` ASC");
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function buildAnalysisPrompt(array $tasks): string
    {
        // Prepare a structured tasks array (so the LLM can unambiguously see id and order)
        $taskList = [];
        foreach ($tasks as $task) {
            $taskList[] = [
                'id' => (int)$task['id'],
                'order' => (int)$task['order'],
                'title' => $task['name'],
                'description' => $task['description'] ?? ''
            ];
        }

        $prompt = "";
        $prompt .= "You are an AI project manager analyzing a task backlog for an AI image generation system.\n";
        $prompt .= "Below is a JSON array of tasks. EACH task object contains an immutable 'id' (primary key) and a mutable 'order' (priority number).\n\n";
        $prompt .= "IMPORTANT INSTRUCTIONS (READ CAREFULLY):\n";
        $prompt .= "1) When you refer to tasks in your output, ALWAYS use the 'id' field to identify tasks. Do NOT use the 'order' value as an identifier.\n";
        $prompt .= "2) Respond ONLY with valid JSON. Do not include any additional text, explanation, or markup. The top-level JSON object must follow the Return Format described below.\n";
        $prompt .= "3) If you suggest re-ordering, return precise integers for 'new_order'. Do not suggest non-integer orders.\n";
        $prompt .= "4) If you identify blocking tasks, return their ids in the 'blocking_tasks' array.\n\n";

        $prompt .= "Tasks JSON:\n";
        $prompt .= json_encode($taskList, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
        $prompt .= "\n\nReturn Format (strict JSON):\n";
        $prompt .= <<<JSON
{
  "analysis": "Short overall assessment string",
  "suggestions": [
    {"id": 67, "current_order": 32, "new_order": 1, "reason": "Why this should move"}
  ],
  "blocking_tasks": [67, 5]
}
JSON;
        $prompt .= "\n\nNow analyze the tasks and produce JSON exactly matching the Return Format. Use 'id' values from the Tasks JSON above for any references.\n";

        return $prompt;
    }

    private function formatTasksForPrompt(array $tasks): string
    {
        $formatted = "";
        foreach ($tasks as $task) {
            $desc = !empty($task['description']) ? ' - ' . substr($task['description'], 0, 80) : '';
            $formatted .= "ID {$task['id']}: {$task['name']}{$desc}\n";
        }
        return $formatted;
    }

    /**
     * Parse the LLM analysis response. Accepts either:
     * - a JSON string (the LLM returned JSON directly), or
     * - an assistant content which might contain JSON embedded in text.
     * Returns structured array; on parse failure returns fallback with raw analysis text.
     */
    private function parseAnalysisResponse(string $response): array
    {
        // If response is likely already a JSON object string, decode it directly
        $json = json_decode($response, true);
        if (is_array($json)) {
            return $json;
        }

        // Try to extract the first JSON object from noisy text
        $candidate = $this->extractFirstJsonObject($response);
        if ($candidate !== null) {
            $json2 = json_decode($candidate, true);
            if (is_array($json2)) {
                return $json2;
            }
        }

        // Fallback: return the raw response (so the UI shows it) and an empty suggestions array
        if ($this->fileLogger) {
            $this->fileLogger->warning(['parseAnalysisResponse fallback' => [
                'reason' => 'Could not extract JSON from LLM response',
                'response_snippet' => mb_substr($response, 0, 2000)
            ]]);
        }

        return [
            'analysis' => $response,
            'suggestions' => []
        ];
    }

    private function parseBlockingResponse(string $response): array
    {
        $json = json_decode($response, true);
        return $json ?: ['blocking_tasks' => [], 'reason' => 'Could not parse response'];
    }

    private function parseNextTasksResponse(string $response): array
    {
        $json = json_decode($response, true);
        return $json ?: ['suggested_tasks' => []];
    }

    /**
     * Extract the first balanced JSON object from a string. Returns the JSON string or null.
     */
    private function extractFirstJsonObject(string $text): ?string
    {
        // Balanced-braces capture using recursion
        if (preg_match('/\{(?:[^{}]|(?R))*\}/s', $text, $m)) {
            $candidate = $m[0];
            json_decode($candidate);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $candidate;
            }
        }
        return null;
    }
    
    /**
     * Get the AIProvider instance (useful for direct access if needed)
     */
    public function getAIProvider(): AIProvider
    {
        return $this->aiProvider;
    }
}
