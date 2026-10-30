<?php
// src/Wtf/StepHandlerInterface.php
// ─────────────────────────────────────────────────────────────────────────────
// WTF — Work The Flow
// Every step handler (built-in or tool-specific, e.g. a future wrish
// handler) implements this. WTF core only ever talks to handlers through
// this interface — it never knows what a handler actually does.
// ─────────────────────────────────────────────────────────────────────────────
namespace App\Wtf;

interface StepHandlerInterface
{
    /**
     * Execute a single run step.
     *
     * @param array $step    Row from wtf_run_steps (associative array), including
     *                       decoded 'config' (from config_json) already provided
     *                       as $step['config'] for convenience.
     * @param array $context Live run-level context (decoded from wtf_runs.context_json).
     *                       Handlers may read from this. To persist changes, return
     *                       them under the 'context_patch' key of the result array
     *                       (shallow-merged into the run's context by the executor).
     *
     * @return array Result array. Recognized keys:
     *   - 'success'        (bool, required unless in_progress/requires_human) whether
     *                       the step completed successfully
     *   - 'summary'        (string, optional) short human-readable summary for the UI
     *   - 'data'           (array, optional) structured result payload (stored as result_json)
     *   - 'error'          (string, optional) error message when success=false
     *   - 'context_patch'  (array, optional) shallow-merged into wtf_runs.context_json
     *   - 'requires_human' (bool, optional) if true, executor marks step 'active', pauses
     *                       the run, and waits — used by handlers like 'manual' that can't
     *                       self-complete at all
     *   - 'in_progress'    (bool, optional) if true, executor leaves the step 'active' and
     *                       the run 'running' without pausing — used by handlers that wrap
     *                       a still-running external process (e.g. polling a scheduler
     *                       task_runs row) and expect execute() to be called again later
     *                       via advance(), either by client polling or a scheduled CLI job
     */
    public function execute(array $step, array $context): array;
}
