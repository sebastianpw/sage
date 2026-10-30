<?php
// src/Wtf/Handlers/ManualHandler.php
// ─────────────────────────────────────────────────────────────────────────────
// WTF — Work The Flow
// Built-in handler: 'manual'. Represents a step that only a human can
// complete (e.g. "review the draft", "approve the budget"). The executor
// never auto-completes this — it always comes back with requires_human=true
// so the step sits 'active' until the person marks it done/skipped/failed
// via the API.
// ─────────────────────────────────────────────────────────────────────────────
namespace App\Wtf\Handlers;

use App\Wtf\StepHandlerInterface;

class ManualHandler implements StepHandlerInterface
{
    public function execute(array $step, array $context): array
    {
        return [
            'success' => false,
            'requires_human' => true,
            'summary' => $step['config']['prompt'] ?? 'Waiting for human action.',
        ];
    }
}
