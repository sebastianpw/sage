<?php
// src/Wtf/Handlers/SubWorkflowHandler.php
// ─────────────────────────────────────────────────────────────────────────────
// WTF — Work The Flow
// Handler: 'sub_workflow'. Allows a WTF step to trigger and wait for an entirely
// separate WTF workflow run.
//
// Config shape (step.config):
//   {
//     "workflow_id": 12,
//     "input_mapping": { "child_key": "parent_key" },
//     "output_mapping": { "parent_key": "child_key" }
//   }
// ─────────────────────────────────────────────────────────────────────────────
namespace App\Wtf\Handlers;

use App\Wtf\StepHandlerInterface;
use App\Wtf\WorkflowExecutor;
use Exception;

class SubWorkflowHandler implements StepHandlerInterface
{
    public function execute(array $step, array $context): array
    {
        $config = $step['config'] ?? [];
        $childWorkflowId = (int)($config['workflow_id'] ?? 0);
        $userId = (int)($step['user_id'] ?? 0);

        if ($childWorkflowId <= 0) {
            return ['success' => false, 'error' => "sub_workflow step config requires 'workflow_id'."];
        }

        $pollState = $step['result'] ?? [];
        $childRunId = $pollState['sub_run_id'] ?? null;

        // Instantiate a fresh executor for the child run
        $executor = new WorkflowExecutor();

        try {
            // Phase A: Start the child workflow
            if (!$childRunId) {
                // Circular dependency safeguard
                $depth = (int)($context['_sub_workflow_depth'] ?? 0);
                if ($depth > 5) {
                    throw new Exception("Sub-workflow depth exceeded limit of 5. Circular dependency detected.");
                }

                // Map inputs from parent context to child context
                $childContext = ['_sub_workflow_depth' => $depth + 1];
                $inputMapping = $config['input_mapping'] ?? [];
                if (is_array($inputMapping)) {
                    foreach ($inputMapping as $childKey => $parentKey) {
                        $childContext[$childKey] = $context[$parentKey] ?? null;
                    }
                }

                $title = "Sub-Run for Step: " . ($step['label'] ?? 'Unknown');
                
                // Start the child run. We force auto_advance = true so the backend heartbeat drives it.
                $childRun = $executor->startRun($userId, $childWorkflowId, $title, $childContext, true);
                
                return [
                    'in_progress' => true,
                    'summary' => "Started Sub-Workflow (Run #{$childRun['id']}). Waiting for completion...",
                    'data' => ['sub_run_id' => $childRun['id']]
                ];
            }

            // Phase B: Poll the existing child workflow
            $childRun = $executor->getRun($userId, $childRunId);
            if (!$childRun) {
                throw new Exception("Child Run #{$childRunId} disappeared from database.");
            }

            $status = $childRun['status'];

            if ($status === 'done') {
                // Map outputs from child context back to parent context
                $contextPatch = [];
                $outputMapping = $config['output_mapping'] ?? [];
                if (is_array($outputMapping)) {
                    foreach ($outputMapping as $parentKey => $childKey) {
                        $contextPatch[$parentKey] = $childRun['context'][$childKey] ?? null;
                    }
                }

                return [
                    'success' => true,
                    'summary' => "Sub-Workflow (Run #{$childRunId}) completed successfully.",
                    'data' => ['sub_run_id' => $childRunId],
                    'context_patch' => $contextPatch
                ];
            }

            if ($status === 'failed') {
                return [
                    'success' => false,
                    'error' => "Sub-Workflow (Run #{$childRunId}) failed. Check its logs.",
                    'data' => ['sub_run_id' => $childRunId]
                ];
            }

            if ($status === 'cancelled') {
                return [
                    'success' => false,
                    'error' => "Sub-Workflow (Run #{$childRunId}) was cancelled.",
                    'data' => ['sub_run_id' => $childRunId]
                ];
            }

            // Still running, pending, or paused. Provide an informative summary.
            $activeStepLabel = 'Initializing...';
            foreach ($childRun['steps'] ?? [] as $s) {
                if ($s['status'] === 'active' || $s['status'] === 'pending') {
                    $activeStepLabel = $s['label'];
                    break;
                }
            }

            return [
                'in_progress' => true,
                'summary' => "Sub-Workflow running: [{$activeStepLabel}]...",
                'data' => ['sub_run_id' => $childRunId]
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => 'sub_workflow handler error: ' . $e->getMessage()];
        }
    }
}
