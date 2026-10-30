<?php
// src/Wtf/Handlers/NoopDelayHandler.php
// ─────────────────────────────────────────────────────────────────────────────
// WTF — Work The Flow
// Built-in handler: 'noop_delay'. Immediately succeeds. Exists so a
// workflow can be built and test-run end-to-end with zero external
// dependencies (no wrish, no image generation, nothing) before any
// real tool-specific handler is wired in. Also useful as a placeholder
// step while designing a workflow template.
// ─────────────────────────────────────────────────────────────────────────────
namespace App\Wtf\Handlers;

use App\Wtf\StepHandlerInterface;

class NoopDelayHandler implements StepHandlerInterface
{
    public function execute(array $step, array $context): array
    {
        $label = $step['label'] ?? $step['step_key'] ?? 'step';
        return [
            'success' => true,
            'summary' => "Auto-completed (noop_delay): {$label}",
            'data' => ['note' => 'This step has no real side effects. Replace handler_key with a real handler when ready.'],
        ];
    }
}
