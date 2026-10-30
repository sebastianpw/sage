<?php
namespace App\Wtf\Handlers;

use App\Wtf\StepHandlerInterface;
use App\Service\ContinuityEngine;
use Exception;

class ContinuityIngestHandler implements StepHandlerInterface
{
    public function execute(array $step, array $context): array
    {
        $config = $step['config'] ?? [];
        $sourceKey = $config['source_text_context_key'] ?? '';
        $writebackKey = $config['writeback_context_key'] ?? '';

        if ($sourceKey === '' || !isset($context[$sourceKey])) {
            return ['success' => false, 'error' => "continuity_ingest requires a valid 'source_text_context_key' containing JSON."];
        }

        $raw = $context[$sourceKey];
        $data = null;

        // Smart parse in case it was written as a raw markdown string by another AI output
        if (is_string($raw)) {
            $cleaned = trim($raw);
            $cleaned = preg_replace('/^```(?:json)?\s*/i', '', $cleaned);
            $cleaned = preg_replace('/\s*```$/', '', $cleaned);
            $data = json_decode($cleaned, true);
        } elseif (is_array($raw)) {
            $data = $raw;
        }

        if (!is_array($data)) {
            return ['success' => false, 'error' => "Data at context key '{$sourceKey}' could not be parsed as a JSON object or array."];
        }

        require_once PROJECT_ROOT . '/src/Service/ContinuityEngine.php';
        $engine = new ContinuityEngine();

        try {
            $stats = $engine->ingestPayload($data);
            
            $result = [
                'success' => true,
                'summary' => "Ingested {$stats['tags_inserted']} tags and {$stats['sketches_inserted']} sketches.",
                'data' => $stats
            ];

            // If a writeback key is defined, merge the results back into the WTF context
            if ($writebackKey !== '') {
                $sketchIds = $stats['sketch_ids'] ?? [];
                
                $result['context_patch'] = [
                    $writebackKey => [
                        'min_id'         => !empty($sketchIds) ? min($sketchIds) : null,
                        'max_id'         => !empty($sketchIds) ? max($sketchIds) : null,
                        'count'          => count($sketchIds),
                        'ids'            => $sketchIds,
                        'has_new_tags'   => $stats['tags_inserted'] > 0,
                        'processed_tags' => $stats['processed_tags'] ?? []
                    ]
                ];
            }

            return $result;
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}