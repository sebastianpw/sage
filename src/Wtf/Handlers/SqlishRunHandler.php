<?php
// src/Wtf/Handlers/SqlishRunHandler.php
// ─────────────────────────────────────────────────────────────────────────────
// WTF — Work The Flow
// Handler: 'sqlish_run'. Bridges a WTF step to a Sqlish extraction operation.
//
// Config shape (step.config):
//   {
//     "template_id": 4,                    // required — sqlish_op_templates.id
//     "source_text_context_key": "lore"    // optional — key in wtf_runs.context_json
//                                          // containing the text to parse
//   }
//
// Behavior: Executes exactly one SqlishEngine::runOnce() pass. If the template
// defines a 'writeback_context_key', the resulting inserted IDs are merged
// directly back into the WTF run's context_json so subsequent steps can use them.
// ─────────────────────────────────────────────────────────────────────────────
namespace App\Wtf\Handlers;

use App\Wtf\StepHandlerInterface;
use App\Service\SqlishEngine;
use Exception;

class SqlishRunHandler implements StepHandlerInterface
{
    public function execute(array $step, array $context): array
    {
        $config = $step['config'] ?? [];
        $templateId = (int)($config['template_id'] ?? 0);
        $sourceKey = $config['source_text_context_key'] ?? '';
        $userId = (int)($step['user_id'] ?? 0);

        if ($templateId <= 0) {
            return ['success' => false, 'error' => "sqlish_run step config requires 'template_id'."];
        }

        // Extract the source text from the WTF run context if requested
        $sourceText = '';
        if ($sourceKey !== '') {
            $sourceText = is_string($context[$sourceKey] ?? null) 
                ? $context[$sourceKey] 
                : json_encode($context[$sourceKey] ?? '');
        }

        $engine = new SqlishEngine();

        try {
            // We pass the full WTF $context as $extraContext so the Sqlish template
            // can resolve any 'context_source_key' fields directly from WTF state.
            $run = $engine->runOnce(
                $userId, 
                $templateId, 
                $sourceText, 
                $context, 
                'WTF Step: ' . ($step['label'] ?? 'Sqlish Extraction')
            );

            if ($run['status'] === 'failed') {
                return [
                    'success' => false,
                    'error' => "Sqlish Run #{$run['id']} failed: " . ($run['last_error'] ?? 'Unknown error'),
                    'data' => ['sqlish_run_id' => $run['id']]
                ];
            }

            // SqlishEngine::runOnce updates its own run's context_json with the
            // writeback data (if configured). We return that mutated context 
            // as 'context_patch' so WTF persists those changes into wtf_runs.context_json.
            return [
                'success' => true,
                'summary' => "Sqlish extracted and wrote {$run['items_written']} items.",
                'data' => [
                    'sqlish_run_id' => $run['id'], 
                    'items_written' => $run['items_written'],
                    'items_failed' => $run['items_failed']
                ],
                'context_patch' => $run['context']
            ];

        } catch (Exception $e) {
            return ['success' => false, 'error' => 'sqlish_run handler error: ' . $e->getMessage()];
        }
    }
}