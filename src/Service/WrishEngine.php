<?php
// src/Service/WrishEngine.php
// ─────────────────────────────────────────────────────────────────────────────
// WRISH — Server-Side Engine (v2 — Deterministic Step Ownership)
//
// This replaces the earlier per-turn JSON-extraction design entirely, after
// repeated real-world testing showed that asking a model to self-report
// structured control-flow state (which turn is a plan vs. a step vs.
// conversation; step ordering; completion) is an unbounded source of shape
// mismatches — every schema tightening just surfaced a new variant. The
// fix is architectural, not another patch: the ENGINE now owns all control
// flow deterministically, and the model is only ever asked for content.
//
// The model (via generator config wrish_showrunner_v1 / id 133 — UNCHANGED,
// still full creative freedom, still the many-focused-inferences design
// that makes Wrish work) is asked for exactly two kinds of things:
//   1. Turn 1 of any session: "invent + plan" — a full creative response
//      that includes a battle plan in prose, however it wants to write it.
//   2. Turn 2..N: "write this specific step" — focused content for
//      whichever step the ENGINE says is active, by title/purpose.
//
// Between turn 1 and turn 2, exactly ONE extraction call ever happens per
// run — not per step — asking for nothing but a flat, unkeyed, single-line,
// delimiter-separated list of step titles (delimiter: "|||", chosen because
// it cannot plausibly appear inside a short title phrase, unlike commas or
// newlines, which real titles contain/wrap on). The engine parses that
// list, creates all wrish_plan_steps rows itself (step_order = array
// position, deterministically), and NEVER re-derives structure from prose
// again for the rest of the run. If even that one extraction fails or
// looks malformed, there is NO retry — the engine deterministically falls
// back to a single unparsed step containing the whole turn-1 response
// verbatim, and the run continues rather than halting.
//
// Every step's raw AI output is stored VERBATIM, unparsed, as
// wrish_plan_steps.result_summary. Nothing about a step's content is ever
// interpreted by this engine — the entire concatenated set of per-step
// raw strings is what eventually gets handed to a downstream writer AI,
// outside Wrish entirely, and that consumer wants prose, not structured
// data, so extracting structure from step content was pure wasted
// surface area for failure with no consumer that needed it.
//
// "Automatic" in this system describes the ARCHITECTURE (deterministic
// step ownership, no per-turn classification) — not the transport. Both
// API Mode and Export Mode run through the exact same deterministic
// engine; the only difference is whether a creative call happens via the
// AI provider directly (API Mode) or is exported as text and its response
// pasted back in (Export Mode). auto_advance (steps chain without waiting)
// only makes sense in API Mode, since Export Mode always has a human
// physically relaying each round by definition — but the step ownership
// underneath is identical either way.
// ─────────────────────────────────────────────────────────────────────────────
namespace App\Service;

use PDO;
use Exception;

class WrishEngine
{
    
    const HARD_MAX_STEPS = 250;
    
    const TITLE_DELIMITER = '|||';
    
    const CREATIVE_CONFIG_ID = 'wrish_showrunner_v1';
    const EXTRACTOR_CONFIG_ID = 'wrish_extractor_v1';
    const TITLE_EXTRACTOR_CONFIG_ID = 'wrish_step_titles_v1';
    const MAX_EXTRACTION_ATTEMPTS = 1;
    
    private PDO $pdo;
    private $spw;
    private GeneratorRepository $repo;

    public function __construct()
    {
        global $spw;
        $this->spw = $spw;
        $this->pdo = $spw->getPDO();
        $this->repo = new GeneratorRepository($this->pdo);
    }

    // ── Run tracking ─────────────────────────────────────────────────────────

    public function startRun(int $userId, string $sessionId, bool $autoAdvance = false, ?int $maxSteps = null): array
    {
        $existing = $this->getActiveRun($userId, $sessionId);
        if ($existing) {
            return $existing;
        }

        $maxSteps = min($maxSteps ?? self::HARD_MAX_STEPS, self::HARD_MAX_STEPS);

        $stmt = $this->pdo->prepare("
            INSERT INTO wrish_runs (user_id, session_id, status, steps_completed, max_steps, auto_advance)
            VALUES (?, ?, 'running', 0, ?, ?)
        ");
        $stmt->execute([$userId, $sessionId, $maxSteps, $autoAdvance ? 1 : 0]);
        $runId = (int)$this->pdo->lastInsertId();

        return $this->getRun($userId, $runId);
    }

    public function getRun(int $userId, int $runId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM wrish_runs WHERE id = ? AND user_id = ?");
        $stmt->execute([$runId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getActiveRun(int $userId, string $sessionId): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM wrish_runs
            WHERE user_id = ? AND session_id = ? AND status IN ('running', 'paused')
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$userId, $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function pauseRun(int $userId, int $runId): void
    {
        $this->pdo->prepare("UPDATE wrish_runs SET status='paused' WHERE id=? AND user_id=?")->execute([$runId, $userId]);
    }

    public function resumeRun(int $userId, int $runId): void
    {
        $this->pdo->prepare("UPDATE wrish_runs SET status='running' WHERE id=? AND user_id=?")->execute([$runId, $userId]);
    }

    public function cancelRun(int $userId, int $runId): void
    {
        $this->pdo->prepare("UPDATE wrish_runs SET status='cancelled', finished_at=NOW() WHERE id=? AND user_id=?")->execute([$runId, $userId]);
    }

    // ── Deterministic step state (read-side) ────────────────────────────────

    public function loadPlan(int $userId, string $sessionId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM wrish_plans WHERE user_id = ? AND session_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId, $sessionId]);
        $planRow = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$planRow) {
            return null;
        }

        $stmt = $this->pdo->prepare("SELECT * FROM wrish_plan_steps WHERE plan_id = ? ORDER BY step_order ASC");
        $stmt->execute([$planRow['id']]);
        $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'id' => $planRow['id'],
            'title' => $planRow['title'],
            'premise' => $planRow['premise'],
            'status' => $planRow['status'],
            'steps' => $steps,
        ];
    }
    
    private function getSessionSettings(int $userId, string $sessionId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM wrish_session_settings WHERE user_id = ? AND session_id = ?");
        $stmt->execute([$userId, $sessionId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) return $row;
        
        $stmt = $this->pdo->prepare("SELECT * FROM wrish_settings WHERE user_id = ?");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Deterministically decides what the NEXT turn's job is, purely from
     * position/state the engine already owns — never from classifying any
     * prior AI output. Returns one of:
     *   ['kind' => 'plan']            — turn 1: invent + propose a battle plan
     *   ['kind' => 'step', 'step' => [...]] — write this specific step
     *   ['kind' => 'done']            — no plan yet needed, or all steps done
     */
    public function determineNextTurn(int $userId, string $sessionId): array
    {
        $plan = $this->loadPlan($userId, $sessionId);

        if (!$plan) {
            // No plan exists yet in this session at all — turn 1 is always,
            // deterministically, the battle-plan-generation turn. This is a
            // structural fact about session state, not a guess.
            return ['kind' => 'plan'];
        }
        
        $settings = $this->getSessionSettings($userId, $sessionId);
        $overwriteDone = !empty($settings['overwrite_done']);

        // If overwrite_done was turned off, clean up any dangling unexecuted "Manual Continuation" steps
        if (!$overwriteDone) {
            $cleaned = false;
            foreach ($plan['steps'] as $step) {
                if (in_array($step['status'], ['active', 'pending'], true) && str_starts_with($step['title'], 'Manual Continuation')) {
                    $this->pdo->prepare("DELETE FROM wrish_plan_steps WHERE id=?")->execute([$step['id']]);
                    $cleaned = true;
                }
            }
            if ($cleaned) {
                $this->pdo->prepare("UPDATE wrish_plans SET status='completed' WHERE id=?")->execute([$plan['id']]);
                $this->pdo->prepare("UPDATE wrish_runs SET status='done' WHERE user_id=? AND session_id=? AND status='running' ORDER BY id DESC LIMIT 1")->execute([$userId, $sessionId]);
                $plan = $this->loadPlan($userId, $sessionId); // reload plan
            }
        }

        foreach ($plan['steps'] as $step) {
            if ($step['status'] === 'active' || $step['status'] === 'pending') {
                return ['kind' => 'step', 'step' => $step];
            }
        }
        
        if ($overwriteDone) {
            $stmt = $this->pdo->prepare("SELECT MAX(step_order) FROM wrish_plan_steps WHERE plan_id = ?");
            $stmt->execute([$plan['id']]);
            $maxOrder = (int)$stmt->fetchColumn();

            if ($maxOrder < self::HARD_MAX_STEPS) {
                $this->pdo->prepare("INSERT INTO wrish_plan_steps (plan_id, user_id, step_order, title, purpose, status) VALUES (?, ?, ?, ?, ?, 'active')")
                    ->execute([$plan['id'], $userId, $maxOrder + 1, 'Manual Continuation ' . ($maxOrder + 1), 'Manual continuation step']);
                
                $this->pdo->prepare("UPDATE wrish_plans SET status='active' WHERE id=?")->execute([$plan['id']]);
                $this->pdo->prepare("UPDATE wrish_runs SET status='running' WHERE user_id=? AND session_id=? AND status='done' ORDER BY id DESC LIMIT 1")->execute([$userId, $sessionId]);

                return $this->determineNextTurn($userId, $sessionId);
            }
        }

        return ['kind' => 'done'];
    }

    // ── API-MODE convenience wrapper ─────────────────────────────────────────

    /**
     * Does exactly ONE turn — whichever kind determineNextTurn() says is
     * next — via a live API call, persists everything, advances state.
     * Zero classification/parsing happens for step turns; the one-time
     * title-list extraction only happens on the turn immediately following
     * turn 1 (see createPlanAndBootstrapSteps()).
     */
    public function runStep(
        int $userId,
        string $sessionId,
        ?string $userInput,
        array $settings,
        ?int $runId = null
    ): array {
        $preflight = $this->preflightRun($userId, $runId);
        if ($preflight !== null) {
            return $preflight;
        }

        $turn = $this->determineNextTurn($userId, $sessionId);

        if ($turn['kind'] === 'done') {
            if ($runId) {
                $this->pdo->prepare("UPDATE wrish_runs SET status='done', finished_at=NOW() WHERE id=?")->execute([$runId]);
            }
            return $this->result('done', '', $this->loadPlan($userId, $sessionId), $runId ? $this->getRun($userId, $runId) : null);
        }

        $contextBlock = $this->buildContextBlock($userId, $sessionId, $settings, $turn);
        $query = $userInput !== null && $userInput !== '' ? $userInput : $this->defaultQueryFor($turn);
        $fullPrompt = "{$contextBlock}\n\nQUERY:\n{$query}";

        $conversation = $this->loadConversationForApi($userId, $sessionId);
        $conversation[] = ['role' => 'user', 'content' => $fullPrompt];

        // ── Creative call — UNCHANGED behavior, same config as always ───────
        $aiText = $this->callGenerator($userId, self::CREATIVE_CONFIG_ID, $conversation);

        return $this->commitTurn($userId, $sessionId, $settings, $turn, $fullPrompt, $query, $aiText, $runId);
    }

    /**
     * EXPORT-MODE, step A: persist a pasted-in creative response for
     * whichever turn is currently next, and commit it exactly like an API
     * turn would. Zero API calls.
     */
    public function injectStep(
        int $userId,
        string $sessionId,
        array $settings,
        string $injectedAiText,
        ?int $runId = null
    ): array {
        $turn = $this->determineNextTurn($userId, $sessionId);
        if ($turn['kind'] === 'done') {
            return $this->result('done', '', $this->loadPlan($userId, $sessionId), $runId ? $this->getRun($userId, $runId) : null);
        }

        // No fullPrompt/query to persist as the "user" side here in a
        // meaningful way beyond a marker — the actual exported prompt text
        // was already shown to the human externally by exportStepPrompt().
        return $this->commitTurn($userId, $sessionId, $settings, $turn, '(exported and pasted externally)', '(exported and pasted externally)', $injectedAiText, $runId);
    }

    /**
     * EXPORT-MODE, step B: builds the exact prompt text for whichever turn
     * is next, for the human to copy/download and run externally. Pure
     * string assembly — zero API calls. $userInput is the optional text
     * from the existing message input box, included exactly where it
     * already sits in the flow (end of the context, before QUERY).
     */
    public function exportStepPrompt(int $userId, string $sessionId, array $settings, ?string $userInput): string
    {
        $turn = $this->determineNextTurn($userId, $sessionId);
        if ($turn['kind'] === 'done') {
            throw new Exception('This session has no pending step — the plan is already complete.');
        }

        $contextBlock = $this->buildContextBlock($userId, $sessionId, $settings, $turn);
        $query = $userInput !== null && $userInput !== '' ? $userInput : $this->defaultQueryFor($turn);
        return "{$contextBlock}\n\nQUERY:\n{$query}";
    }

    private function defaultQueryFor(array $turn): string
    {
        if ($turn['kind'] === 'plan') {
            return 'Plan a full season battle plan, then begin.';
        }
        $step = $turn['step'];
        return "Write this step now: \"{$step['title']}\" — {$step['purpose']}";
    }

    /**
     * Shared commit logic for both the live-API path and the inject path:
     * persist the conversation turn, then deterministically either (a)
     * bootstrap all plan steps from a one-time title-list extraction (only
     * ever runs immediately after turn 1), or (b) store this step's raw
     * output verbatim and advance to the next step. No classification of
     * which branch to take — determined entirely by $turn['kind'], which
     * the engine already established before making the call.
     */
    private function commitTurn(
        int $userId,
        string $sessionId,
        array $settings,
        array $turn,
        string $fullPrompt,
        string $displayQuery,
        string $aiText,
        ?int $runId
    ): array {
        $nowMs = (int)round(microtime(true) * 1000);
        $this->persistConversationTurn($userId, $sessionId, $fullPrompt, $displayQuery, $nowMs, $aiText);

        if ($turn['kind'] === 'plan') {
            $plan = $this->createPlanAndBootstrapSteps($userId, $sessionId, $settings, $aiText);
            if ($runId) {
                $this->incrementRunSteps($runId);
            }
            return $this->result('plan_created', $aiText, $plan, $runId ? $this->getRun($userId, $runId) : null);
        }

        // $turn['kind'] === 'step': store verbatim, advance deterministically.
        $step = $turn['step'];
        $this->pdo->prepare("
            UPDATE wrish_plan_steps SET status='done', result_summary=?, completed_at=NOW() WHERE id=?
        ")->execute([$aiText, $step['id']]);

        $nextStmt = $this->pdo->prepare("
            SELECT id FROM wrish_plan_steps WHERE plan_id=? AND step_order > ? ORDER BY step_order ASC LIMIT 1
        ");
        $nextStmt->execute([$step['plan_id'], $step['step_order']]);
        $nextId = $nextStmt->fetchColumn();

        if ($nextId) {
            $this->pdo->prepare("UPDATE wrish_plan_steps SET status='active' WHERE id=?")->execute([$nextId]);
        } else {
            $this->pdo->prepare("UPDATE wrish_plans SET status='completed' WHERE id=?")->execute([$step['plan_id']]);
        }

        if ($runId) {
            $this->incrementRunSteps($runId);
        }

        $plan = $this->loadPlan($userId, $sessionId);
        
        
        
        
        $overwriteDone = !empty($settings['overwriteDone']) || !empty($settings['overwrite_done']);
        if ($runId && $this->planIsComplete($plan) && !$overwriteDone) { // <── Prevent done status if overwrite is enabled
            $this->pdo->prepare("UPDATE wrish_runs SET status='done', finished_at=NOW() WHERE id=?")->execute([$runId]);
        }

        
        /*
        if ($runId && $this->planIsComplete($plan)) {
            $this->pdo->prepare("UPDATE wrish_runs SET status='done', finished_at=NOW() WHERE id=?")->execute([$runId]);
        }
        */

        return $this->result('step_completed', $aiText, $plan, $runId ? $this->getRun($userId, $runId) : null);
    }

    /**
     * Runs immediately after turn 1 (plan creation) — this is the ONE AND
     * ONLY extraction call in the entire lifetime of a run. Asks for a
     * flat, single-line, delimiter-separated list of step titles — nothing
     * else, no JSON, no keys, no per-step metadata beyond the title text
     * itself. NO RETRY on malformed output, per explicit decision: if the
     * response doesn't look like a clean delimited list, the engine
     * deterministically falls back to a single unparsed step containing
     * the entire turn-1 response verbatim, and the run continues. A
     * failure here never halts anything.
     */
    private function createPlanAndBootstrapSteps(int $userId, string $sessionId, array $settings, string $planText): array
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO wrish_plans (user_id, session_id, title, premise, status)
            VALUES (?, ?, ?, ?, 'active')
        ");
        $title = $settings['sessionTopic'] ?? 'Season Battle Plan';
        $stmt->execute([$userId, $sessionId, $title, $settings['sessionTopic'] ?? '']);
        $planId = (int)$this->pdo->lastInsertId();

        $titles = $this->extractStepTitlesNoRetry($userId, $planText);
        $isFallback = empty($titles);

        if ($isFallback) {
            // Deterministic fallback — never halts, never retries.
            $titles = ['Full Battle Plan (unparsed)'];
        }

        $sStmt = $this->pdo->prepare("
            INSERT INTO wrish_plan_steps (plan_id, user_id, step_order, title, purpose, status)
            VALUES (?, ?, ?, ?, '', ?)
        ");
        foreach ($titles as $i => $stepTitle) {
            $sStmt->execute([$planId, $userId, $i + 1, $stepTitle, $i === 0 ? 'active' : 'pending']);
        }

        // If we fell back to the single-unparsed-step case, that one step's
        // eventual "content" IS the original plan text — store it now
        // rather than waiting for a step-turn to produce it, since there is
        // no further AI call that would ever populate it otherwise (turn 1
        // already happened; a fresh call would just re-invent the plan).
        if ($isFallback) {
            $this->pdo->prepare("
                UPDATE wrish_plan_steps SET status='done', result_summary=?, completed_at=NOW() WHERE plan_id=? AND step_order=1
            ")->execute([$planText, $planId]);
            $this->pdo->prepare("UPDATE wrish_plans SET status='completed' WHERE id=?")->execute([$planId]);
        }

        return $this->loadPlan($userId, $sessionId);
    }

    private function extractStepTitlesNoRetry(int $userId, string $planText): array
    {
        try {
            $raw = $this->callGenerator($userId, self::TITLE_EXTRACTOR_CONFIG_ID, [
                ['role' => 'user', 'content' => $this->buildTitleExtractionPrompt($planText)],
            ]);
        } catch (Exception $e) {
            $this->logTitleExtractionAttempt($userId, $planText, '', false, $e->getMessage());
            return [];
        }

        $titles = $this->parseTitleList($raw);
        $this->logTitleExtractionAttempt($userId, $planText, $raw, !empty($titles), empty($titles) ? 'Parsed zero titles from output.' : null);

        return $titles;
    }

    private function buildTitleExtractionPrompt(string $planText): string
    {
        $d = self::TITLE_DELIMITER;
        return "The following text contains a battle plan with a numbered or otherwise formatted list of steps.\n\n"
            . "Respond with ONLY the step titles, in order, on a SINGLE LINE, separated by the exact delimiter {$d} "
            . "(three pipe characters) and nothing else — no numbering, no bullets, no quotes, no explanation, no line breaks.\n\n"
            . "Example of the exact expected output format:\nFirst Step Title{$d}Second Step Title{$d}Third Step Title\n\n"
            . "═══ SOURCE TEXT ═══\n{$planText}\n═══ END SOURCE TEXT ═══";
    }

    private function parseTitleList(string $raw): array
    {
        $cleaned = trim($raw);
        $cleaned = preg_replace('/^```(?:\w*)?\s*/', '', $cleaned);
        $cleaned = preg_replace('/\s*```$/', '', $cleaned);
        $cleaned = trim($cleaned);

        if ($cleaned === '') {
            return [];
        }

        if (!str_contains($cleaned, self::TITLE_DELIMITER)) {
            // No delimiter found at all. If it's short and plausibly a
            // single title (one line, no wild length), accept it as a
            // one-step plan. Otherwise, treat as unusable — no retry, just
            // signal "nothing usable" so the caller falls back
            // deterministically to a single unparsed step.
            $single = trim($cleaned, " \t\n\r\0\x0B\"'");
            if ($single !== '' && !str_contains($single, "\n") && mb_strlen($single) < 200) {
                return [$single];
            }
            return [];
        }

        $parts = explode(self::TITLE_DELIMITER, $cleaned);
        $titles = [];
        foreach ($parts as $part) {
            $t = trim($part);
            $t = trim($t, " \t\n\r\0\x0B\"'");
            if ($t !== '') {
                $titles[] = $t;
            }
        }

        if (count($titles) > self::HARD_MAX_STEPS) {
            $titles = array_slice($titles, 0, self::HARD_MAX_STEPS);
        }

        return $titles;
    }

    private function logTitleExtractionAttempt(int $userId, string $sourceText, string $rawOutput, bool $ok, ?string $error): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO wrish_extraction_log
                (run_id, user_id, session_id, attempt, source_text, raw_extraction_output, parsed_ok, parse_error)
            VALUES (NULL, ?, '', 1, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $sourceText, $rawOutput, $ok ? 1 : 0, $error]);
    }

    private function planIsComplete(?array $plan): bool
    {
        if (!$plan) {
            return false;
        }
        if ($plan['status'] === 'completed') {
            return true;
        }
        foreach ($plan['steps'] as $s) {
            if (in_array($s['status'], ['pending', 'active'], true)) {
                return false;
            }
        }
        return true;
    }

    private function preflightRun(int $userId, ?int $runId): ?array
    {
        if (!$runId) {
            return null;
        }
        $run = $this->getRun($userId, $runId);
        if ($run && !in_array($run['status'], ['running'], true)) {
            throw new Exception("Run {$runId} is '{$run['status']}' and cannot take another step.");
        }
        if ($run && (int)$run['steps_completed'] >= (int)$run['max_steps']) {
            $this->pdo->prepare("UPDATE wrish_runs SET status='done', finished_at=NOW() WHERE id=?")->execute([$runId]);
            return $this->result('done', '', null, $this->getRun($userId, $runId));
        }
        return null;
    }

    private function incrementRunSteps(int $runId): void
    {
        $this->pdo->prepare("UPDATE wrish_runs SET steps_completed = steps_completed + 1 WHERE id = ?")->execute([$runId]);
    }

    // ── Context building ─────────────────────────────────────────────────────

    private function buildContextBlock(int $userId, string $sessionId, array $settings, array $turn): string
    {
        $parts = [];

        if (!empty($settings['sessionTopic'])) {
            $parts[] = "SESSION TOPIC: {$settings['sessionTopic']}";
        }
        $friction = $settings['frictionIntensity'] ?? 'medium';
        $parts[] = "FRICTION INTENSITY: {$friction}";

        if (!empty($settings['contextExtra'])) {
            $parts[] = "ADDITIONAL CONTEXT:\n{$settings['contextExtra']}";
        }

        // Pools are no longer managed through the UI, but if they exist in the DB
        // we still include them as per "designed to craft them automatically and has them in the main string lore"
        $pools = $this->loadPools($userId);
        if (!empty($pools)) {
            $byType = [];
            foreach ($pools as $p) {
                $byType[$p['pool_type']][] = $p;
            }
            $poolChunks = [];
            foreach ($byType as $type => $items) {
                $lines = array_map(fn($p) => "- [{$p['source']}] {$p['label']}: " . ($p['body'] ?? ''), $items);
                $poolChunks[] = strtoupper($type) . " POOL:\n" . implode("\n", $lines);
            }
            $parts[] = "═══ CREATIVE POOLS ═══\n" . implode("\n\n", $poolChunks);
        }

        if ($turn['kind'] === 'step') {
            $plan = $this->loadPlan($userId, $sessionId);
            if ($plan) {
                $stepLines = array_map(function ($s) {
                    $marker = $s['status'] === 'active' ? '→ ' : '  ';
                    return "{$marker}{$s['step_order']}. [{$s['status']}] {$s['title']}";
                }, $plan['steps']);
                $parts[] = "═══ BATTLE PLAN (for context — you are writing the step marked →) ═══\n" . implode("\n", $stepLines);
            }
        }

        // The existing message-input-box text (if any) is appended by the
        // caller as part of QUERY, exactly where it already sits in the
        // flow today — this context block does not duplicate it.

        return implode("\n\n", $parts);
    }

    // ── Pools / conversation persistence ─────────────────────────────────────

    private function loadPools(int $userId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM wrish_pools WHERE user_id = ? ORDER BY id ASC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function loadConversationForApi(int $userId, string $sessionId): array
    {
        $stmt = $this->pdo->prepare("SELECT role, content FROM wrish_conversation WHERE user_id = ? AND session_id = ? ORDER BY id ASC");
        $stmt->execute([$userId, $sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function persistConversationTurn(int $userId, string $sessionId, string $fullPrompt, string $displayQuery, int $tsMs, string $aiText): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO wrish_conversation (user_id, session_id, role, content, display_content, ts)
            VALUES (?, ?, 'user', ?, ?, ?)
        ");
        $stmt->execute([$userId, $sessionId, $fullPrompt, $displayQuery, $tsMs]);

        $stmt = $this->pdo->prepare("
            INSERT INTO wrish_conversation (user_id, session_id, role, content, ts)
            VALUES (?, ?, 'assistant', ?, ?)
        ");
        $stmt->execute([$userId, $sessionId, $aiText, (int)round(microtime(true) * 1000)]);

        $this->pdo->prepare("
            INSERT INTO wrish_chat_sessions (session_id, user_id, title, updated_at)
            VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE updated_at = NOW()
        ")->execute([$sessionId, $userId, mb_substr($displayQuery, 0, 40)]);
    }

    /**
     * EXPORT MODE ONLY: replace the most recent assistant message's content
     * in place, for correcting an accidental bad paste — per explicit
     * design, this stays editable until the human clicks the next-step
     * action; there is no general per-message delete, only ever the tip of
     * the conversation, which by definition hasn't yet caused any
     * downstream plan/step state to unwind.
     */
    public function replaceLastAssistantMessage(int $userId, string $sessionId, string $newText): bool
    {
        $stmt = $this->pdo->prepare("
            SELECT id FROM wrish_conversation
            WHERE user_id = ? AND session_id = ? AND role = 'assistant'
            ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$userId, $sessionId]);
        $id = $stmt->fetchColumn();
        if (!$id) {
            return false;
        }
        $this->pdo->prepare("UPDATE wrish_conversation SET content = ? WHERE id = ? AND user_id = ?")->execute([$newText, $id, $userId]);
        return true;
    }

    // ── AI call plumbing ─────────────────────────────────────────────────────

    private function callGenerator(int $userId, string $configId, array $conversationMessages): string
    {
        $rec = $this->repo->findActiveForUser($configId, $userId);
        if (!$rec) {
            throw new Exception("Generator config '{$configId}' not found or inactive for user {$userId}.");
        }

        $sysParts = [];
        if ($rec->systemRole) {
            $sysParts[] = $rec->systemRole;
        }
        if (!empty($rec->instructions)) {
            $sysParts[] = "═══ RULES & PROTOCOLS ═══\n" . implode("\n\n", $rec->instructions);
        }

        $messages = [['role' => 'system', 'content' => implode("\n\n", $sysParts)]];
        foreach ($conversationMessages as $msg) {
            $messages[] = [
                'role' => ($msg['role'] === 'user') ? 'user' : 'assistant',
                'content' => $msg['content'],
            ];
        }

        $aiOptions = [];
        if (isset($rec->parameters['temperature'])) {
            $aiOptions['temperature'] = (float)$rec->parameters['temperature']['default'];
        }
        $aiOptions['max_tokens'] = isset($rec->parameters['max_tokens'])
            ? (int)$rec->parameters['max_tokens']['default']
            : 65536;

        return $this->spw->getAIProvider()->sendMessage($rec->model, $messages, $aiOptions);
    }

    private function result(string $turnType, string $aiText, ?array $plan, ?array $run): array
    {
        return [
            'turn_type' => $turnType,
            'ai_text' => $aiText,
            'plan' => $plan,
            'run' => $run,
        ];
    }
}
