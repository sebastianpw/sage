<?php
// public/cli_continuity.php
// Standalone Continuity Runner
// Reads continuity_jobs, groups by sketch, runs AI, writes back to sketches.description
//
// Usage:
//   php public/cli_continuity.php                          (interactive)
//   php public/cli_continuity.php [ContGenID] [BatchSize]  (batch)
//   php public/cli_continuity.php [ContGenID] 0            (infinite / all pending)
//
// The script will:
//   1. Pull pending jobs grouped by sketch_id
//   2. Assemble the character context for each sketch
//   3. Call the continuity generator
//   4. Save description_raw (if empty) and update description
//   5. Mark jobs done / failed

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;
use App\Core\AIProvider;

// ── DEFAULTS ──────────────────────────────────────────────────────────────────
const DEFAULT_CONT_GEN_ID = 126;
const COOLDOWN_SECONDS    = 2;
const MAX_RETRIES         = 3;

// ── ANSI COLORS ───────────────────────────────────────────────────────────────
const C_RESET  = "\033[0m";
const C_RED    = "\033[31m";
const C_GREEN  = "\033[32m";
const C_YELLOW = "\033[33m";
const C_BLUE   = "\033[34m";
const C_CYAN   = "\033[36m";
const C_GRAY   = "\033[90m";
const C_WHITE  = "\033[1m";

// ── INIT ──────────────────────────────────────────────────────────────────────
$em   = $spw->getEntityManager();
$conn = $em->getConnection();
$repo = $em->getRepository(GeneratorConfig::class);

$logger      = $spw->getFileLogger();
$aiProvider  = $spw->getAIProvider();
if (!$aiProvider) $aiProvider = new AIProvider($logger);

$generatorService = new GeneratorService(
    $aiProvider, new SchemaValidator(), new ResponseNormalizer(), $logger
);

// ── HELPERS ───────────────────────────────────────────────────────────────────

function generateWithRetry(GeneratorService $service, GeneratorConfig $config, array $params, int $maxTries = 3) {
    for ($i = 1; $i <= $maxTries; $i++) {
        try {
            $res = $service->generate($config, $params);
            if ($res->isSuccess()) return $res;
        } catch (\Throwable $e) {
            $lastError = $e->getMessage();
        }
        if ($i < $maxTries) {
            echo C_YELLOW . "   (Retry $i/$maxTries)..." . C_RESET . "\n";
            sleep(2);
        }
    }
    throw new \Exception("AI generation failed after $maxTries attempts. Last error: " . ($lastError ?? 'unknown'));
}

function extractScenePrompt($data): string {
    if (is_array($data)) {
        foreach (['scene_prompt', 'description', 'text', 'content', 'result'] as $k) {
            if (!empty($data[$k]) && is_string($data[$k])) return $data[$k];
        }
        $first = reset($data);
        if (is_string($first)) return $first;
        return json_encode($data);
    }
    return trim((string)$data);
}

/**
 * Fetch all pending sketch IDs, ordered ascending.
 * Returns an array of sketch_ids.
 */
function fetchPendingSketchIds(\Doctrine\DBAL\Connection $conn, int $limit): array {
    $limitClause = $limit > 0 ? "LIMIT $limit" : "";
    $rows = $conn->fetchAllAssociative(
        "SELECT DISTINCT sketch_id
         FROM continuity_jobs
         WHERE status = 'pending'
         ORDER BY sketch_id ASC
         $limitClause"
    );
    return array_column($rows, 'sketch_id');
}

/**
 * Fetch all pending jobs for a single sketch, ordered by sort_order.
 */
function fetchJobsForSketch(\Doctrine\DBAL\Connection $conn, int $sketchId): array {
    return $conn->fetchAllAssociative(
        "SELECT cj.id AS job_id, cj.character_id, cj.sort_order, cj.cont_gen_id,
                c.name AS char_name, c.description AS char_desc
         FROM continuity_jobs cj
         JOIN characters c ON c.id = cj.character_id
         WHERE cj.sketch_id = ? AND cj.status = 'pending'
         ORDER BY cj.sort_order ASC",
        [$sketchId]
    );
}

/**
 * Fetch sketch row.
 */
function fetchSketch(\Doctrine\DBAL\Connection $conn, int $sketchId): ?array {
    $row = $conn->fetchAssociative("SELECT * FROM sketches WHERE id = ?", [$sketchId]);
    return $row ?: null;
}

/**
 * Mark a list of job IDs as done or failed.
 */
function markJobs(\Doctrine\DBAL\Connection $conn, array $jobIds, string $status, ?string $error = null, ?string $resultText = null): void {
    if (empty($jobIds)) return;
    $ph = implode(',', array_fill(0, count($jobIds), '?'));
    $conn->executeStatement(
        "UPDATE continuity_jobs SET status = ?, error_msg = ?, result_text = ?, attempts = attempts + 1
         WHERE id IN ($ph)",
        array_merge([$status, $error, $resultText], $jobIds)
    );
}

/**
 * Save the continuity result back to the sketch.
 * - Preserves description_raw (copies description→description_raw if raw is empty)
 * - Writes new description
 */
function saveSketchDescription(\Doctrine\DBAL\Connection $conn, int $sketchId, string $newDescription, string $originalDescription): void {
    // Only write description_raw on first continuity run (when it is still NULL / empty)
    $conn->executeStatement(
        "UPDATE sketches
         SET description     = ?,
             description_raw = COALESCE(NULLIF(description_raw, ''), ?)
         WHERE id = ?",
        [$newDescription, $originalDescription, $sketchId]
    );
}

// ── ARGUMENT PARSING & INTERACTIVE MODE ───────────────────────────────────────
$args      = array_slice($argv, 1);
$contGenId = DEFAULT_CONT_GEN_ID;
$batchSize = 0; // 0 = all pending

if (!empty($args)) {
    // Batch mode
    $contGenId = isset($args[0]) ? (int)$args[0] : DEFAULT_CONT_GEN_ID;
    $batchSize = isset($args[1]) ? (int)$args[1] : 0;

    echo "\n" . C_CYAN . "🖊  CLI CONTINUITY RUNNER (Batch Mode)" . C_RESET . "\n";
} else {
    // Interactive mode
    echo "\n" . C_CYAN . "========================================" . C_RESET . "\n";
    echo C_CYAN . "   🖊  CLI CONTINUITY RUNNER" . C_RESET . "\n";
    echo C_CYAN . "========================================" . C_RESET . "\n\n";

    $inputGenId = readline("Continuity Generator ID [default " . DEFAULT_CONT_GEN_ID . "]: ");
    $contGenId  = (trim($inputGenId) !== '' && is_numeric($inputGenId)) ? (int)$inputGenId : DEFAULT_CONT_GEN_ID;

    $inputBatch = readline("How many sketches to process? (0 = all pending): ");
    $batchSize  = (trim($inputBatch) !== '' && is_numeric($inputBatch)) ? max(0, (int)$inputBatch) : 0;
}

// ── VALIDATE GENERATOR ────────────────────────────────────────────────────────
$contConfig = $repo->find($contGenId);
if (!$contConfig) {
    die(C_RED . "Error: Generator config ID $contGenId not found.\n" . C_RESET);
}

// ── SUMMARY ───────────────────────────────────────────────────────────────────
$totalPending = (int)$conn->fetchOne("SELECT COUNT(DISTINCT sketch_id) FROM continuity_jobs WHERE status = 'pending'");

echo C_GRAY . "----------------------------------------" . C_RESET . "\n";
echo " Generator:     " . C_WHITE . $contConfig->getTitle() . C_RESET . "\n";
echo " Pending sketches: " . C_WHITE . $totalPending . C_RESET . "\n";
echo " Processing:    " . C_WHITE . ($batchSize > 0 ? $batchSize : "all ($totalPending)") . C_RESET . "\n";
echo C_GRAY . "----------------------------------------" . C_RESET . "\n\n";

if ($totalPending === 0) {
    echo C_GREEN . "✅ No pending jobs. Nothing to do.\n" . C_RESET;
    exit(0);
}

// ── MAIN LOOP ─────────────────────────────────────────────────────────────────
$sketchIds   = fetchPendingSketchIds($conn, $batchSize);
$total       = count($sketchIds);
$doneCount   = 0;
$failedCount = 0;

foreach ($sketchIds as $idx => $sketchId) {
    $progressStr = "[" . ($idx + 1) . "/$total]";
    echo C_BLUE . "$progressStr Sketch #$sketchId" . C_RESET . "\n";

    // 1. Load sketch
    $sketch = fetchSketch($conn, $sketchId);
    if (!$sketch) {
        echo C_RED . "   ⚠  Sketch $sketchId not found in sketches table. Skipping.\n" . C_RESET;
        // Mark all jobs for this sketch as skipped
        $jobIds = array_column(
            $conn->fetchAllAssociative("SELECT id FROM continuity_jobs WHERE sketch_id = ? AND status = 'pending'", [$sketchId]),
            'id'
        );
        markJobs($conn, $jobIds, 'skipped', 'Sketch row not found');
        continue;
    }

    $originalDescription = $sketch['description'] ?? '';
    if (trim($originalDescription) === '') {
        echo C_YELLOW . "   ⚠  Sketch $sketchId has no description. Skipping.\n" . C_RESET;
        $jobIds = array_column(
            $conn->fetchAllAssociative("SELECT id FROM continuity_jobs WHERE sketch_id = ? AND status = 'pending'", [$sketchId]),
            'id'
        );
        markJobs($conn, $jobIds, 'skipped', 'Sketch has no description');
        continue;
    }

    // 2. Load jobs / characters for this sketch
    $jobs = fetchJobsForSketch($conn, $sketchId);
    if (empty($jobs)) {
        echo C_GRAY . "   No pending jobs for this sketch.\n" . C_RESET;
        continue;
    }

    $jobIds    = array_column($jobs, 'job_id');
    $charNames = array_column($jobs, 'char_name');
    echo "   Characters: " . C_CYAN . implode(', ', $charNames) . C_RESET . "\n";

    // 3. Determine which generator to use (job-level override takes precedence)
    $overrideGenId = null;
    foreach ($jobs as $job) {
        if (!empty($job['cont_gen_id'])) {
            $overrideGenId = (int)$job['cont_gen_id'];
            break;
        }
    }

    $activeConfig = $contConfig;
    if ($overrideGenId && $overrideGenId !== $contGenId) {
        $overrideConfig = $repo->find($overrideGenId);
        if ($overrideConfig) {
            $activeConfig = $overrideConfig;
            echo C_GRAY . "   Using override generator: " . $overrideConfig->getTitle() . C_RESET . "\n";
        }
    }

    // 4. Build character context block
    $charContext = "";
    foreach ($jobs as $job) {
        $charDesc = trim(strip_tags($job['char_desc'] ?? ''));
        $charContext .= "CHARACTER: {$job['char_name']}\n{$charDesc}\n\n";
    }

    // 5. Build continuity prompt (same approach as autopilot)
    $continuityPrompt =
        "You are a cinematic scene compiler. Your task is to rewrite a scene description so that "
        . "the specified characters appear with their exact appearance as described, while preserving "
        . "the full cinematic dynamism and action of the original scene.\n\n"
        . "CRITICAL RULES:\n"
        . "- Keep the original scene energy, action, and visual drama INTACT\n"
        . "- Do NOT reduce the scene to a static or posed composition\n"
        . "- Characters must match their exact physical descriptions below\n"
        . "- Preserve all environmental details, lighting, scale, and atmosphere from the original\n"
        . "- Place characters naturally within the scene's action, not posed for a portrait\n"
        . "- The scene prompt goes LAST in your response for maximum AI impact\n\n"
        . "CHARACTER REFERENCE (Use these EXACT descriptions):\n"
        . $charContext
        . "\n\n---\n\n"
        . "ORIGINAL SCENE TO REWRITE:\n"
        . $originalDescription
        . "\n\n---\n\n"
        . "Rewrite the scene with the characters above integrated naturally into the action. "
        . "Return ONLY the final scene description as JSON: {\"scene_prompt\": \"...\"}";

    // 6. Call AI
    echo "   ⚡ Running continuity... ";

    try {
        $result = generateWithRetry($generatorService, $activeConfig, [
            'entity_name' => $continuityPrompt,
            'random_seed' => rand(1, 9999999)
        ], MAX_RETRIES);

        $newDescription = extractScenePrompt($result->getData());

        // Sanity check
        if (strlen(trim($newDescription)) < 80) {
            throw new \Exception("Response too short (" . strlen($newDescription) . " chars), likely invalid.");
        }

        // Strip em-dashes as in autopilot
        $newDescription = str_replace(["\u{2014}", "—"], "", $newDescription);

        echo C_GREEN . "OK (" . strlen($newDescription) . " chars)" . C_RESET . "\n";

        // 7. Save to DB
        $conn->beginTransaction();
        try {
            saveSketchDescription($conn, $sketchId, $newDescription, $originalDescription);
            markJobs($conn, $jobIds, 'done', null, $newDescription);
            $conn->commit();
            echo C_GREEN . "   💾 Saved sketch #$sketchId\n" . C_RESET;
            $doneCount++;
        } catch (\Throwable $dbEx) {
            $conn->rollBack();
            throw new \Exception("DB save failed: " . $dbEx->getMessage());
        }

    } catch (\Throwable $e) {
        if ($conn->isTransactionActive()) $conn->rollBack();
        $errMsg = $e->getMessage();
        echo C_RED . "FAILED\n   ❌ " . $errMsg . C_RESET . "\n";
        markJobs($conn, $jobIds, 'failed', $errMsg);
        $failedCount++;
    }

    // Cooldown between sketches (skip after last)
    if ($idx < $total - 1) {
        echo C_GRAY . "   Cooldown (" . COOLDOWN_SECONDS . "s)...\n" . C_RESET;
        sleep(COOLDOWN_SECONDS);
    }

    echo "\n";
}

// ── FINAL SUMMARY ─────────────────────────────────────────────────────────────
echo C_GRAY . "========================================" . C_RESET . "\n";
echo C_WHITE . "DONE" . C_RESET . "\n";
echo "  Processed:  $total sketches\n";
echo C_GREEN . "  Succeeded:  $doneCount\n" . C_RESET;
if ($failedCount > 0) {
    echo C_RED . "  Failed:     $failedCount\n" . C_RESET;
    echo C_YELLOW . "  Re-run the script to retry failed jobs (status reset to 'pending' manually or add --retry flag).\n" . C_RESET;
}
echo C_GRAY . "========================================\n" . C_RESET;
