<?php
// src/Wtf/WorkflowExecutor.php
// ─────────────────────────────────────────────────────────────────────────────
// WTF — Work The Flow
// Deterministic orchestration core. Knows about workflows/runs/steps/events
// ONLY. Never references wrish, image generation, sketches, continuity, or
// any other SAGE-specific concept — those live entirely inside handlers
// resolved via HandlerRegistry.
//
// v3 CHANGE: constructor no longer accepts $pdo or $spw. This class (like
// every other class in WTF/Wrish now) pulls the app's single global $spw
// singleton directly and gets $pdo from $spw->getPDO(). Every file that
// constructs a WorkflowExecutor must have already required bootstrap.php
// and env_locals.php (same as every other page in the app).
//
// v4 CHANGE (bugfix): executeStep()'s in-progress branch now persists
// result_json (not just result_summary) on every tick. Handlers like
// SchedulerTaskHandler keep their own poll state (baseline_run_id /
// watch_run_id) inside the step's result data and read it back out via
// $step['result'] on the next call. Previously that data was only ever
// written when a step reached the terminal "done" state (in completeStep()),
// so every in-progress poll looked like a brand-new first call to the
// handler — it kept re-triggering the underlying task over and over and
// never actually tracked it through to completion. This is the root cause
// of "the scheduled task really ran, but WTF never noticed."
// ─────────────────────────────────────────────────────────────────────────────
namespace App\Wtf;

use PDO;
use Exception;

class WorkflowExecutor
{
    private PDO $pdo;
    private HandlerRegistry $registry;

    public function __construct(?HandlerRegistry $registry = null)
    {
        global $spw;
        $this->pdo = $spw->getPDO();
        $this->registry = $registry ?? new HandlerRegistry();
    }

    // ── Workflow templates ──────────────────────────────────────────────────

    public function listWorkflows(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM wtf_workflows WHERE user_id = ? ORDER BY updated_at DESC");
        $stmt->execute([$userId]);
        return array_map([$this, 'hydrateWorkflow'], $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Paginated variant of listWorkflows(). Returns a page envelope
     * ({items, page, limit, total, total_pages}) rather than a bare array,
     * so the UI can render prev/next controls and a jump-to-page input
     * without needing a separate count query of its own.
     */
    public function listWorkflowsPaged(int $userId, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM wtf_workflows WHERE user_id = ?");
        $countStmt->execute([$userId]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare("SELECT * FROM wtf_workflows WHERE user_id = ? ORDER BY updated_at DESC LIMIT ? OFFSET ?");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = array_map([$this, 'hydrateWorkflow'], $stmt->fetchAll(PDO::FETCH_ASSOC));

        return [
            'workflows' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => max(1, (int)ceil($total / $limit)),
        ];
    }

    public function getWorkflow(int $userId, int $workflowId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM wtf_workflows WHERE id = ? AND user_id = ?");
        $stmt->execute([$workflowId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->hydrateWorkflow($row) : null;
    }

    public function saveWorkflow(int $userId, array $workflow): array
    {
        $steps = $workflow['steps'] ?? [];
        $this->validateStepDefs($steps);
        $stepsJson = json_encode($steps, JSON_UNESCAPED_UNICODE);

        if (!empty($workflow['id'])) {
            $stmt = $this->pdo->prepare("
                UPDATE wtf_workflows SET title=?, description=?, steps_json=?, is_active=?
                WHERE id=? AND user_id=?
            ");
            $stmt->execute([
                $workflow['title'] ?? 'Untitled Workflow',
                $workflow['description'] ?? '',
                $stepsJson,
                !empty($workflow['is_active']) ? 1 : 1,
                $workflow['id'],
                $userId,
            ]);
            $id = (int)$workflow['id'];
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO wtf_workflows (user_id, title, description, steps_json, is_active)
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $userId,
                $workflow['title'] ?? 'Untitled Workflow',
                $workflow['description'] ?? '',
                $stepsJson,
            ]);
            $id = (int)$this->pdo->lastInsertId();
        }

        return $this->getWorkflow($userId, $id);
    }

    public function deleteWorkflow(int $userId, int $workflowId): void
    {
        $this->pdo->prepare("DELETE FROM wtf_workflows WHERE id=? AND user_id=?")->execute([$workflowId, $userId]);
    }

    private function validateStepDefs(array $steps): void
    {
        foreach ($steps as $i => $s) {
            if (empty($s['handler_key'])) {
                throw new Exception("Step at index {$i} is missing handler_key.");
            }
            if (!$this->registry->has($s['handler_key'])) {
                $known = implode(', ', $this->registry->availableKeys());
                throw new Exception("Step at index {$i} uses unknown handler_key '{$s['handler_key']}'. Known handlers: {$known}.");
            }
        }
    }
    
    
       // ── Runs ─────────────────────────────────────────────────────────────────

    public function startRun(int $userId, int $workflowId, ?string $title = null, array $initialContext = [], bool $autoAdvance = false): array
    {
        $workflow = $this->getWorkflow($userId, $workflowId);
        if (!$workflow) {
            throw new Exception("Workflow {$workflowId} not found.");
        }
        if (empty($workflow['steps'])) {
            throw new Exception("Workflow '{$workflow['title']}' has no steps defined.");
        }

        $this->pdo->beginTransaction();
        try {
            $runTitle = $title ?: ($workflow['title'] . ' — ' . date('Y-m-d H:i'));
            $stmt = $this->pdo->prepare("
                INSERT INTO wtf_runs (user_id, workflow_id, title, status, context_json, auto_advance)
                VALUES (?, ?, ?, 'running', ?, ?)
            ");
            $stmt->execute([$userId, $workflowId, $runTitle, json_encode($initialContext, JSON_UNESCAPED_UNICODE), $autoAdvance ? 1 : 0]);
            $runId = (int)$this->pdo->lastInsertId();

            $stepStmt = $this->pdo->prepare("
                INSERT INTO wtf_run_steps (run_id, user_id, sort_order, step_key, label, handler_key, config_json, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            foreach ($workflow['steps'] as $i => $s) {
                $stepStmt->execute([
                    $runId,
                    $userId,
                    $i,
                    $s['key'] ?? null,
                    $s['label'] ?? ('Step ' . ($i + 1)),
                    $s['handler_key'],
                    json_encode($s['config'] ?? [], JSON_UNESCAPED_UNICODE),
                    $i === 0 ? 'active' : 'pending',
                ]);
            }

            // NOTE: lastInsertId after a loop reflects the LAST inserted row, not the first.
            // Re-fetch the actual first step id explicitly to avoid relying on insert order semantics.
            $firstStepStmt = $this->pdo->prepare("SELECT id FROM wtf_run_steps WHERE run_id = ? ORDER BY sort_order ASC LIMIT 1");
            $firstStepStmt->execute([$runId]);
            $firstStepId = (int)$firstStepStmt->fetchColumn();

            $this->pdo->prepare("UPDATE wtf_runs SET current_step_id = ? WHERE id = ?")->execute([$firstStepId, $runId]);

            $this->logEvent($runId, $userId, null, 'run_started', "Run started from workflow '{$workflow['title']}'.");

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return $this->getRun($userId, $runId);
    }

    public function listRuns(int $userId, int $limit = 50): array
    {
        $stmt = $this->pdo->prepare("
            SELECT r.*, w.title AS workflow_title
            FROM wtf_runs r
            JOIN wtf_workflows w ON w.id = r.workflow_id
            WHERE r.user_id = ?
            ORDER BY r.updated_at DESC
            LIMIT ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Paginated variant of listRuns(). Returns a page envelope
     * ({items, page, limit, total, total_pages}) rather than a bare array,
     * so the UI can render prev/next controls and a jump-to-page input
     * without needing a separate count query of its own.
     */
    public function listRunsPaged(int $userId, int $page = 1, int $limit = 20): array
    {
        $page = max(1, $page);
        $limit = max(1, $limit);
        $offset = ($page - 1) * $limit;

        $countStmt = $this->pdo->prepare("SELECT COUNT(*) FROM wtf_runs WHERE user_id = ?");
        $countStmt->execute([$userId]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $this->pdo->prepare("
            SELECT r.*, w.title AS workflow_title
            FROM wtf_runs r
            JOIN wtf_workflows w ON w.id = r.workflow_id
            WHERE r.user_id = ?
            ORDER BY r.updated_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->bindValue(1, $userId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'runs' => $items,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => max(1, (int)ceil($total / $limit)),
        ];
    }

    public function getRun(int $userId, int $runId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM wtf_runs WHERE id = ? AND user_id = ?");
        $stmt->execute([$runId, $userId]);
        $run = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$run) return null;

        $run['context'] = json_decode($run['context_json'] ?? '{}', true) ?: [];
        $run['auto_advance'] = (bool)($run['auto_advance'] ?? false);

        $stepStmt = $this->pdo->prepare("SELECT * FROM wtf_run_steps WHERE run_id = ? ORDER BY sort_order ASC");
        $stepStmt->execute([$runId]);
        $steps = $stepStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($steps as &$s) {
            $s['config'] = json_decode($s['config_json'] ?? '{}', true) ?: [];
            $s['result'] = json_decode($s['result_json'] ?? 'null', true);
        }
        unset($s);
        $run['steps'] = $steps;

        return $run;
    }

    public function getRunEvents(int $userId, int $runId, int $limit = 200): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM wtf_run_events WHERE run_id = ? AND user_id = ? ORDER BY id ASC LIMIT ?
        ");
        $stmt->bindValue(1, $runId, PDO::PARAM_INT);
        $stmt->bindValue(2, $userId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['meta'] = json_decode($r['meta_json'] ?? 'null', true);
        }
        return $rows;
    }
    
    
       // ── Step execution ───────────────────────────────────────────────────────

    /**
     * Attempt to execute the run's current active step via its registered
     * handler. Called by "Proceed" in the UI for MANUAL (auto_advance=0)
     * runs, or by the scheduler heartbeat loop for auto_advance=1 runs.
     * The frontend never calls this automatically/on a timer for
     * auto_advance runs — see TaskRunnerScheduler::maybeAdvanceWtfRuns().
     */
    public function advance(int $userId, int $runId): array
    {
        $run = $this->getRun($userId, $runId);
        if (!$run) throw new Exception("Run {$runId} not found.");
        if (!in_array($run['status'], ['running', 'pending'], true)) {
            throw new Exception("Run is '{$run['status']}' and cannot be advanced.");
        }

        $step = $this->findActiveStep($run);
        if (!$step) {
            return $this->maybeCompleteRun($userId, $run);
        }

        return $this->executeStep($userId, $run, $step);
    }

    private function findActiveStep(array $run): ?array
    {
        foreach ($run['steps'] as $s) {
            if ($s['status'] === 'active') return $s;
        }
        // No active step marked yet (e.g. fresh run) — promote the first pending one.
        foreach ($run['steps'] as $s) {
            if ($s['status'] === 'pending') return $s;
        }
        return null;
    }

    private function executeStep(int $userId, array $run, array $step): array
    {
        $stepId = (int)$step['id'];
        $isFirstEntry = $step['status'] !== 'active' || empty($step['started_at']);

        if ($isFirstEntry) {
            // Only stamp started_at / bump attempts / log 'started' the first time
            // a step becomes active — NOT on every re-poll of an in-progress step
            // (e.g. a handler that wraps a long-running external job and is polled
            // repeatedly via advance() until it finishes). Without this guard,
            // polling would spam step_started events and inflate attempts.
            $this->pdo->prepare("
                UPDATE wtf_run_steps SET status='active', started_at=IFNULL(started_at, NOW()), attempts=attempts+1 WHERE id=?
            ")->execute([$stepId]);
            $this->logEvent($run['id'], $userId, $stepId, 'step_started', "Step '{$step['label']}' started (handler: {$step['handler_key']}).");
        }

        try {
            $handler = $this->registry->resolve($step['handler_key']);
            $result = $handler->execute($step, $run['context']);
        } catch (Exception $e) {
            return $this->failStep($userId, $run['id'], $stepId, $e->getMessage());
        }

        if (!empty($result['requires_human'])) {
            // Handler cannot self-complete (e.g. 'manual'). Leave step active,
            // record what it's waiting on, and pause the run for attention.
            $summary = $result['summary'] ?? 'Waiting for human input.';
            $this->pdo->prepare("UPDATE wtf_run_steps SET result_summary=? WHERE id=?")->execute([$summary, $stepId]);
            $this->pdo->prepare("UPDATE wtf_runs SET status='paused' WHERE id=?")->execute([$run['id']]);
            $this->logEvent($run['id'], $userId, $stepId, 'run_paused', $summary);
            return $this->getRun($userId, $run['id']);
        }

        if (!empty($result['in_progress'])) {
            // Handler is watching a still-running external process (e.g. a
            // scheduler task_runs row not yet in a terminal state) or a
            // multi-turn sub-engine (e.g. Wrish). Not a failure, not waiting
            // on a human — just not done yet. Leave the step active and the
            // run 'running' so a later advance() call (from the scheduler
            // heartbeat, or a manual "Proceed" click for non-auto runs) picks
            // up exactly where this left off.
            //
            // CRITICAL: persist result_json here too, not just result_summary.
            // Handlers (e.g. SchedulerTaskHandler) keep their own poll state
            // (baseline_run_id / watch_run_id) inside 'data' and read it back
            // via $step['result'] on the next call. If this isn't persisted,
            // every poll looks like a brand-new first call to the handler.
            $summary = $result['summary'] ?? 'In progress…';
            $dataJson = isset($result['data']) ? json_encode($result['data'], JSON_UNESCAPED_UNICODE) : null;
            $this->pdo->prepare("UPDATE wtf_run_steps SET result_summary=?, result_json=? WHERE id=?")
                ->execute([$summary, $dataJson, $stepId]);
            return $this->getRun($userId, $run['id']);
        }

        if (empty($result['success'])) {
            $err = $result['error'] ?? 'Step handler reported failure with no message.';
            return $this->failStep($userId, $run['id'], $stepId, $err);
        }

        return $this->completeStep($userId, $run, $step, $result);
    }

    private function completeStep(int $userId, array $run, array $step, array $result): array
    {
        $stepId = (int)$step['id'];
        $summary = $result['summary'] ?? 'Completed.';
        $dataJson = isset($result['data']) ? json_encode($result['data'], JSON_UNESCAPED_UNICODE) : null;

        $this->pdo->prepare("
            UPDATE wtf_run_steps
            SET status='done', result_summary=?, result_json=?, finished_at=NOW()
            WHERE id=?
        ")->execute([$summary, $dataJson, $stepId]);
        $this->logEvent($run['id'], $userId, $stepId, 'step_done', $summary);

        if (!empty($result['context_patch']) && is_array($result['context_patch'])) {
            $newContext = array_merge($run['context'], $result['context_patch']);
            $this->pdo->prepare("UPDATE wtf_runs SET context_json=? WHERE id=?")
                ->execute([json_encode($newContext, JSON_UNESCAPED_UNICODE), $run['id']]);
        }

        // Promote next step, if any.
        $nextStmt = $this->pdo->prepare("
            SELECT id FROM wtf_run_steps WHERE run_id=? AND sort_order > ? ORDER BY sort_order ASC LIMIT 1
        ");
        $nextStmt->execute([$run['id'], $step['sort_order']]);
        $nextId = $nextStmt->fetchColumn();

        if ($nextId) {
            $this->pdo->prepare("UPDATE wtf_run_steps SET status='active' WHERE id=?")->execute([$nextId]);
            $this->pdo->prepare("UPDATE wtf_runs SET current_step_id=?, status='running' WHERE id=?")->execute([$nextId, $run['id']]);
            $refreshedRun = $this->getRun($userId, $run['id']);

            if (!empty($refreshedRun['auto_advance'])) {
                $nextStep = $this->findActiveStep($refreshedRun);
                if ($nextStep) {
                    return $this->executeStep($userId, $refreshedRun, $nextStep);
                }
            }
            return $refreshedRun;
        }

        // No more steps — run is complete.
        $this->pdo->prepare("UPDATE wtf_runs SET status='done', current_step_id=NULL, finished_at=NOW() WHERE id=?")
            ->execute([$run['id']]);
        $this->logEvent($run['id'], $userId, null, 'run_completed', 'All steps completed.');

        return $this->getRun($userId, $run['id']);
    }

    private function failStep(int $userId, int $runId, int $stepId, string $error): array
    {
        $this->pdo->prepare("
            UPDATE wtf_run_steps SET status='failed', error_text=?, finished_at=NOW() WHERE id=?
        ")->execute([$error, $stepId]);
        $this->pdo->prepare("UPDATE wtf_runs SET status='failed' WHERE id=?")->execute([$runId]);
        $this->logEvent($runId, $userId, $stepId, 'step_failed', $error);

        return $this->getRun($userId, $runId);
    }

    private function maybeCompleteRun(int $userId, array $run): array
    {
        $allDone = true;
        foreach ($run['steps'] as $s) {
            if (!in_array($s['status'], ['done', 'skipped'], true)) { $allDone = false; break; }
        }
        if ($allDone) {
            $this->pdo->prepare("UPDATE wtf_runs SET status='done', current_step_id=NULL, finished_at=NOW() WHERE id=?")
                ->execute([$run['id']]);
            $this->logEvent($run['id'], $userId, null, 'run_completed', 'All steps completed.');
        }
        return $this->getRun($userId, $run['id']);
    }
    
    
    
    
       // ── Human controls ───────────────────────────────────────────────────────

    /** Human marks the current (usually 'manual') step done, with an optional note/result. */
    public function completeStepManually(int $userId, int $runId, int $stepId, string $note = ''): array
    {
        $run = $this->getRun($userId, $runId);
        if (!$run) throw new Exception("Run {$runId} not found.");
        $step = $this->getStepOrFail($run, $stepId);

        $result = ['success' => true, 'summary' => $note !== '' ? $note : 'Marked done by human.'];
        return $this->completeStep($userId, $run, $step, $result);
    }

    public function skipStep(int $userId, int $runId, int $stepId, string $reason = ''): array
    {
        $this->pdo->prepare("
            UPDATE wtf_run_steps SET status='skipped', result_summary=?, finished_at=NOW() WHERE id=? AND run_id=?
        ")->execute([$reason !== '' ? $reason : 'Skipped by human.', $stepId, $runId]);
        $this->logEvent($runId, $userId, $stepId, 'step_skipped', $reason ?: 'Skipped by human.');

        $run = $this->getRun($userId, $runId);
        $step = $this->getStepOrFail($run, $stepId);

        $nextStmt = $this->pdo->prepare("
            SELECT id FROM wtf_run_steps WHERE run_id=? AND sort_order > ? ORDER BY sort_order ASC LIMIT 1
        ");
        $nextStmt->execute([$runId, $step['sort_order']]);
        $nextId = $nextStmt->fetchColumn();

        if ($nextId) {
            $this->pdo->prepare("UPDATE wtf_run_steps SET status='active' WHERE id=?")->execute([$nextId]);
            $this->pdo->prepare("UPDATE wtf_runs SET current_step_id=?, status='running' WHERE id=?")->execute([$nextId, $runId]);
        } else {
            $this->pdo->prepare("UPDATE wtf_runs SET status='done', current_step_id=NULL, finished_at=NOW() WHERE id=?")->execute([$runId]);
            $this->logEvent($runId, $userId, null, 'run_completed', 'All steps completed (last step skipped).');
        }

        return $this->getRun($userId, $runId);
    }

    public function retryStep(int $userId, int $runId, int $stepId): array
    {
        $this->pdo->prepare("
            UPDATE wtf_run_steps SET status='active', error_text=NULL, finished_at=NULL WHERE id=? AND run_id=?
        ")->execute([$stepId, $runId]);
        $this->logEvent($runId, $userId, $stepId, 'step_retried', 'Retrying step.');
        $this->pdo->prepare("UPDATE wtf_runs SET status='running', current_step_id=? WHERE id=?")->execute([$stepId, $runId]);

        return $this->advance($userId, $runId);
    }

    public function pauseRun(int $userId, int $runId): array
    {
        $this->pdo->prepare("UPDATE wtf_runs SET status='paused' WHERE id=? AND user_id=?")->execute([$runId, $userId]);
        $this->logEvent($runId, $userId, null, 'run_paused', 'Paused by human.');
        return $this->getRun($userId, $runId);
    }

    public function resumeRun(int $userId, int $runId): array
    {
        $this->pdo->prepare("UPDATE wtf_runs SET status='running' WHERE id=? AND user_id=?")->execute([$runId, $userId]);
        $this->logEvent($runId, $userId, null, 'run_resumed', 'Resumed by human.');
        return $this->getRun($userId, $runId);
    }

    public function cancelRun(int $userId, int $runId): array
    {
        $this->pdo->prepare("UPDATE wtf_runs SET status='cancelled', finished_at=NOW() WHERE id=? AND user_id=?")->execute([$runId, $userId]);
        $this->logEvent($runId, $userId, null, 'run_cancelled', 'Cancelled by human.');
        return $this->getRun($userId, $runId);
    }

    public function addNote(int $userId, int $runId, string $note, ?int $stepId = null): void
    {
        $this->logEvent($runId, $userId, $stepId, 'note', $note);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function getStepOrFail(array $run, int $stepId): array
    {
        foreach ($run['steps'] as $s) {
            if ((int)$s['id'] === $stepId) return $s;
        }
        throw new Exception("Step {$stepId} not found on run {$run['id']}.");
    }

    private function hydrateWorkflow(array $row): array
    {
        $row['steps'] = json_decode($row['steps_json'] ?? '[]', true) ?: [];
        return $row;
    }

    private function logEvent(int $runId, int $userId, ?int $stepId, string $type, string $message = '', array $meta = []): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO wtf_run_events (run_id, user_id, step_id, event_type, message, meta_json, ts)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $runId,
            $userId,
            $stepId,
            $type,
            $message,
            !empty($meta) ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            (int)round(microtime(true) * 1000),
        ]);
    }

    // ── Scheduler-heartbeat entry point ─────────────────────────────────────
    // Called by TaskRunnerScheduler::maybeAdvanceWtfRuns() once per heartbeat
    // tick (currently every 1s). Advances every running+auto_advance run by
    // exactly one step per call, per run — same "one tick, one step" contract
    // as everywhere else in this class. No logging happens here beyond what
    // advance()/executeStep() already do (i.e. only on real state transitions
    // or errors) — an empty pass produces zero log output.
    //
    // Returns the number of runs it attempted to advance, purely so the
    // caller can decide whether it's worth a log line at all (it isn't,
    // by design — see TaskRunnerScheduler).
    public function advanceAllAutoRuns(): int
    {
        $stmt = $this->pdo->query("
            SELECT id, user_id FROM wtf_runs
            WHERE status = 'running' AND auto_advance = 1
            ORDER BY updated_at ASC
        ");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($rows)) return 0;

        foreach ($rows as $row) {
            try {
                $this->advance((int)$row['user_id'], (int)$row['id']);
            } catch (Exception $e) {
                // Swallow here — advance() already wrote a step_failed/run
                // status change + wtf_run_events row for anything that broke.
                // The heartbeat loop itself must never die because one run's
                // handler threw; other runs still need their tick.
            }
        }
        return count($rows);
    }
}
    