<?php
// src/Wtf/Handlers/WrishRunHandler.php
// ─────────────────────────────────────────────────────────────────────────────
// WTF — Work The Flow
// Handler: 'wrish_run'. Bridges a WTF step to a Wrish automated run,
// following the exact same shape as SchedulerTaskHandler (trigger once,
// then poll to a terminal status via repeated execute() calls driven by
// advance()).
//
// Config shape (step.config):
//   {
//     "wrish_session_id": "abc-123",   // required — a wrish_chat_sessions.session_id
//     "initial_message": "Plan a full season...",  // optional, only used if
//                                                    // the session has no plan yet
//     "max_steps": 100,                 // optional, defaults to WrishEngine's
//                                        // own internal cap
//     "wrish_user_id": 7                // required — the SAGE user_id that
//                                        // owns this Wrish session (WTF runs
//                                        // and Wrish sessions both being
//                                        // user-scoped, these are not
//                                        // necessarily the same value as
//                                        // the WTF run's own user_id if a
//                                        // workflow is run on someone's
//                                        // behalf — pass explicitly rather
//                                        // than assuming)
//   }
//
// Behavior: on first execute(), starts (or resumes) a wrish_runs row via
// WrishEngine::startRun() and takes exactly one step via runStep() — never
// loops internally, exactly like SchedulerTaskHandler never launches a
// process itself. Every subsequent execute() call (via WTF's advance(),
// either from UI polling or a scheduled sweep) takes exactly one more
// step.
//
// Terminal mapping: wrish_runs.status 'done' -> step succeeds; 'cancelled'
// -> step fails (with the run's id attached to the step's result data for
// diagnosis); 'running' -> step stays in_progress and gets called again
// later.
//
// v3 CHANGE: no constructor parameters. Pulls $pdo and $spw from the
// global singleton directly, same as every other file in the app.
// ─────────────────────────────────────────────────────────────────────────────
namespace App\Wtf\Handlers;

use App\Wtf\StepHandlerInterface;
use App\Service\WrishEngine;
use PDO;
use Exception;

class WrishRunHandler implements StepHandlerInterface
{
    private PDO $pdo;
    private $spw;

    public function __construct()
    {
        global $spw;
        $this->spw = $spw;
        $this->pdo = $spw->getPDO();
    }
    
    
    /*
    public function execute(array $step, array $context): array
    {
        $config = $step['config'] ?? [];
        $sessionId = (string)($config['wrish_session_id'] ?? '');
        $wrishUserId = (int)($config['wrish_user_id'] ?? 0);

        if ($sessionId === '' || $wrishUserId <= 0) {
            return ['success' => false, 'error' => "wrish_run step config requires both 'wrish_session_id' and 'wrish_user_id'."];
        }

        $engine = new WrishEngine($this->pdo, $this->spw);

        try {
            $run = $engine->getActiveRun($wrishUserId, $sessionId);
            if (!$run) {
                $run = $engine->startRun(
                    $wrishUserId,
                    $sessionId,
                    false, // auto_advance stays false at the Wrish level — WTF itself is
                           // what's driving repeated calls here, one per execute(), so
                           // Wrish doesn't need its own internal loop on top of that
                    isset($config['max_steps']) ? (int)$config['max_steps'] : null
                );
            }

            if (!in_array($run['status'], ['running'], true)) {
                return $this->mapRunToResult($run);
            }

            $settings = [
                'depth' => 'standard',
                'frictionIntensity' => 'medium',
                'autoAdvance' => false,
                'sessionTopic' => '',
                'contextExtra' => '',
            ];

            $initialMessage = null;
            if ((int)$run['steps_completed'] === 0 && !empty($config['initial_message'])) {
                $initialMessage = $config['initial_message'];
            }

            // WrishEngine's runStep() signature is
            // ($userId, $sessionId, $userInput, $settings, $runId) — no
            // $protocol argument (turn kind is fully deterministic).
            $result = $engine->runStep($wrishUserId, $sessionId, $initialMessage, $settings, (int)$run['id']);

            $run = $result['run'] ?? $engine->getRun($wrishUserId, (int)$run['id']);
            return $this->mapRunToResult($run, $result['turn_type']);
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'wrish_run handler error: ' . $e->getMessage()];
        }
    }
    */
    
    
   public function execute(array $step, array $context): array
    {
        $config = $step['config'] ?? [];
        $sessionId = (string)($config['wrish_session_id'] ?? '');
        $wrishUserId = (int)($config['wrish_user_id'] ?? 0);

        if ($sessionId === '' || $wrishUserId <= 0) {
            return ['success' => false, 'error' => "wrish_run step config requires both 'wrish_session_id' and 'wrish_user_id'."];
        }

        $engine = new WrishEngine();

        try {
            $run = $engine->getActiveRun($wrishUserId, $sessionId);
            if (!$run) {
                $run = $engine->startRun(
                    $wrishUserId,
                    $sessionId,
                    false, // WTF drives the loop, keep false at Wrish level
                    isset($config['max_steps']) ? (int)$config['max_steps'] : null
                );
            }

            if (!in_array($run['status'], ['running'], true)) {
                // Pass $config and $context so we can read source & writeback keys if completed immediately
                return $this->mapRunToResult($run, null, $config, $context);
            }

            // ─── NEW: LOAD STAGED SETTINGS FROM DATABASE ───
            $setStmt = $this->pdo->prepare("SELECT * FROM wrish_session_settings WHERE user_id = ? AND session_id = ?");
            $setStmt->execute([$wrishUserId, $sessionId]);
            $sessSettingsRow = $setStmt->fetch(PDO::FETCH_ASSOC) ?: [];

            if (empty($sessSettingsRow)) {
                $setStmt = $this->pdo->prepare("SELECT * FROM wrish_settings WHERE user_id = ?");
                $setStmt->execute([$wrishUserId]);
                $sessSettingsRow = $setStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            }

            /*
            $settings = [
                'depth' => $sessSettingsRow['depth'] ?? 'standard',
                'frictionIntensity' => $sessSettingsRow['friction_intensity'] ?? 'medium',
                'autoAdvance' => false,
                'sessionTopic' => $sessSettingsRow['session_topic'] ?? '',
                'contextExtra' => $sessSettingsRow['context_extra'] ?? '',
            ];
            */
            
            /*
           $settings = [
                'depth' => $sessSettingsRow['depth'] ?? 'standard',
                'frictionIntensity' => $sessSettingsRow['friction_intensity'] ?? 'medium',
                'autoAdvance' => false,
                'sessionTopic' => $sessSettingsRow['session_topic'] ?? '',
                'contextExtra' => $sessSettingsRow['context_extra'] ?? '',
                'overwriteDone' => (bool)($sessSettingsRow['overwrite_done'] ?? 0), // <── Add this line
            ];
            */
            
            
            
            
            
            
            
            $sourceKey = $config['source_text_context_key'] ?? '';
            $contextExtra = $sessSettingsRow['context_extra'] ?? '';

            if ($sourceKey !== '' && isset($context[$sourceKey])) {
                $sourceText = is_string($context[$sourceKey]) 
                    ? $context[$sourceKey] 
                    : json_encode($context[$sourceKey], JSON_UNESCAPED_UNICODE);

                if ($sourceText !== '') {
                    $contextExtra = $contextExtra !== '' 
                        ? $contextExtra . "\n\n=== SOURCE CONTEXT ({$sourceKey}) ===\n" . $sourceText 
                        : "=== SOURCE CONTEXT ({$sourceKey}) ===\n" . $sourceText;
                }
            }

            $settings = [
                'depth' => $sessSettingsRow['depth'] ?? 'standard',
                'frictionIntensity' => $sessSettingsRow['friction_intensity'] ?? 'medium',
                'autoAdvance' => false,
                'sessionTopic' => $sessSettingsRow['session_topic'] ?? '',
                'contextExtra' => $contextExtra,
                'overwriteDone' => (bool)($sessSettingsRow['overwrite_done'] ?? 0),
            ];
            
            
            
            
            
            
            // ───────────────────────────────────────────────

            $initialMessage = null;
            if ((int)$run['steps_completed'] === 0 && !empty($config['initial_message'])) {
                $initialMessage = $config['initial_message'];
            }

            $result = $engine->runStep($wrishUserId, $sessionId, $initialMessage, $settings, (int)$run['id']);

            $run = $result['run'] ?? $engine->getRun($wrishUserId, (int)$run['id']);
            // Pass $config and $context to mapRunToResult
            return $this->mapRunToResult($run, $result['turn_type'], $config, $context);
            
            
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'wrish_run handler error: ' . $e->getMessage()];
        }
    }
    
    

    private function mapRunToResult(array $run, ?string $lastTurnType = null, array $config = [], array $context = []): array
    {
        $status = $run['status'];

        if ($status === 'running') {
            $label = $lastTurnType ? " (last turn: {$lastTurnType})" : '';
            return [
                'in_progress' => true,
                'summary' => "Wrish run #{$run['id']}: {$run['steps_completed']}/{$run['max_steps']} steps{$label}.",
                'data' => ['wrish_run_id' => $run['id'], 'steps_completed' => $run['steps_completed']],
            ];
        }

        if ($status === 'done') {
            $writebackKey = $config['writeback_context_key'] ?? '';
            $sourceKey = $config['source_text_context_key'] ?? '';
            $contextPatch = [];

            if ($writebackKey !== '') {
                $stmt = $this->pdo->prepare("
                    SELECT s.title, s.result_summary 
                    FROM wrish_plan_steps s
                    JOIN wrish_plans p ON s.plan_id = p.id
                    WHERE p.session_id = ?
                    ORDER BY s.step_order ASC
                ");
                $stmt->execute([$run['session_id']]);
                $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $fullPlotDocument = "";

                // Prepend incoming source text if this Wrish step received input from an earlier step
                if ($sourceKey !== '' && isset($context[$sourceKey])) {
                    $sourceText = is_string($context[$sourceKey]) 
                        ? $context[$sourceKey] 
                        : json_encode($context[$sourceKey], JSON_UNESCAPED_UNICODE);

                    if (trim($sourceText) !== '') {
                        $fullPlotDocument .= "=== SOURCE CONTEXT ({$sourceKey}) ===\n\n" . trim($sourceText) . "\n\n";
                    }
                }

                $fullPlotDocument .= "=== NARRATIVE PLOT OUTLINE ===\n\n";
                foreach ($steps as $s) {
                    $fullPlotDocument .= "## " . $s['title'] . "\n";
                    $fullPlotDocument .= ($s['result_summary'] ?? '') . "\n\n";
                }

                $contextPatch[$writebackKey] = $fullPlotDocument;
            }

            return [
                'success' => true,
                'summary' => "Wrish run #{$run['id']} completed — {$run['steps_completed']} steps.",
                'data' => ['wrish_run_id' => $run['id'], 'steps_completed' => $run['steps_completed']],
                'context_patch' => $contextPatch
            ];
        }

        // cancelled (the only non-running/non-done status WrishEngine sets)
        $suffix = !empty($run['last_error']) ? ' ' . $run['last_error'] : '';
        return [
            'success' => false,
            'error' => "Wrish run #{$run['id']} ended with status '{$status}'.{$suffix}",
            'data' => ['wrish_run_id' => $run['id']],
        ];
    }
}