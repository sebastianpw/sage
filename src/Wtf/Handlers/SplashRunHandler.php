<?php
// src/Wtf/Handlers/SplashRunHandler.php
// ─────────────────────────────────────────────────────────────────────────────
// WTF — Work The Flow
// Handler: 'splash_run'. Bridges WTF to a Splash Writers' Room pipeline.
// ─────────────────────────────────────────────────────────────────────────────
namespace App\Wtf\Handlers;

use App\Wtf\StepHandlerInterface;
use App\Service\SplashEngine;
use Exception;

class SplashRunHandler implements StepHandlerInterface
{
    public function execute(array $step, array $context): array
    {
        $config = $step['config'] ?? [];
        $templateId = (int)($config['template_id'] ?? 0);
        $sourceKey = $config['source_text_context_key'] ?? '';
        $writebackKey = $config['writeback_context_key'] ?? '';
        $userId = (int)($step['user_id'] ?? 0);

        if ($templateId <= 0) {
            return ['success' => false, 'error' => "splash_run step config requires 'template_id'."];
        }

        $engine = new SplashEngine();
        $pollState = $step['result'] ?? [];
        $splashRunId = $pollState['splash_run_id'] ?? null;

        try {
            // First time this step executes: Start the run
            if (!$splashRunId) {
                $sourceText = '';
                if ($sourceKey !== '') {
                    $sourceText = is_string($context[$sourceKey] ?? null) 
                        ? $context[$sourceKey] 
                        : json_encode($context[$sourceKey] ?? '');
                }

                $run = $engine->startRun($userId, $templateId, $sourceText, 'WTF Step: ' . ($step['label'] ?? 'Splash'));
                $splashRunId = $run['id'];
            }

            // Execute next step in the Splash chain
            $run = $engine->runNextRole($userId, $splashRunId);

            if ($run['status'] === 'failed') {
                return [
                    'success' => false,
                    'error' => "Splash Run #{$run['id']} failed: " . ($run['last_error'] ?? 'Unknown error'),
                    'data' => ['splash_run_id' => $run['id']]
                ];
            }

            if ($run['status'] === 'done') {
                $contextPatch = [];
                if ($writebackKey !== '') {
                    $contextPatch[$writebackKey] = $run['final_output_text'];
                }

                return [
                    'success' => true,
                    'summary' => "Splash Writers' Room finished successfully.",
                    'data' => ['splash_run_id' => $run['id']],
                    'context_patch' => $contextPatch
                ];
            }

            // Find current active role for summary
            $activeRole = 'Initializing...';
            foreach ($run['roles'] as $r) {
                if ($r['status'] === 'active' || $r['status'] === 'pending') {
                    $activeRole = $r['role_name'];
                    break;
                }
            }

            return [
                'in_progress' => true,
                'summary' => "Splash Writers' Room working: [{$activeRole}]...",
                'data' => ['splash_run_id' => $run['id']]
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => 'splash_run handler error: ' . $e->getMessage()];
        }
    }
}