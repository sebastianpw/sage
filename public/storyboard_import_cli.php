#!/usr/bin/env php
<?php
// storyboard_import_cli.php
//
// CLI importer for storyboards with persistent sort_order.
// Modes:
//   1) Direct parameter mode
//   2) Interactive mode when no parameters are provided
//   3) Queue mode via JSONL file
//
// Ordering rule:
//   - latest sketches.id DESC
//   - frames.id DESC
//
// Behavior:
//   - Uses one PDO connection
//   - Uses one transaction
//   - Uses batched INSERTs
//   - Skips duplicates already present in the target storyboard
//
// Example direct run:
//   php storyboard_import_cli.php --storyboard_id=134 --min_map_run_id=5680 --entity_type=sketches
//
// Example interactive:
//   php storyboard_import_cli.php
//
// Example enqueue:
//   php storyboard_import_cli.php --queue_enqueue --queue_file=/tmp/storyboard_import.queue.jsonl --storyboard_id=134 --min_map_run_id=5680
//
// Example dequeue:
//   php storyboard_import_cli.php --queue_dequeue --queue_file=/tmp/storyboard_import.queue.jsonl --qjobs=3

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';
require_once __DIR__ . '/error_reporting.php';

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();

function out(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function err(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
}

function isInteractive(): bool
{
    return function_exists('posix_isatty') ? @posix_isatty(STDIN) : true;
}

function prompt(string $label, ?string $default = null): string
{
    $suffix = $default !== null && $default !== '' ? " [$default]" : '';
    fwrite(STDOUT, $label . $suffix . ': ');
    $line = trim((string)fgets(STDIN));
    if ($line === '' && $default !== null) {
        return $default;
    }
    return $line;
}

function parseBoolFlag(array $opts, string $name): bool
{
    return array_key_exists($name, $opts);
}

function loadQueueJobsFromFile(string $queueFile, int $limit): array
{
    if (!is_file($queueFile)) {
        return [];
    }

    $lines = file($queueFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        throw new RuntimeException("Unable to read queue file: {$queueFile}");
    }

    $jobs = [];
    foreach ($lines as $line) {
        $job = json_decode($line, true);
        if (is_array($job)) {
            $jobs[] = $job;
        }
        if (count($jobs) >= $limit) {
            break;
        }
    }

    return $jobs;
}

function rewriteQueueFileWithoutFirstN(string $queueFile, int $n): void
{
    if (!is_file($queueFile)) {
        return;
    }

    $lines = file($queueFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        throw new RuntimeException("Unable to read queue file: {$queueFile}");
    }

    $remaining = array_slice($lines, $n);
    file_put_contents($queueFile, implode(PHP_EOL, $remaining) . (empty($remaining) ? '' : PHP_EOL));
}

function appendQueueJob(string $queueFile, array $job): void
{
    $dir = dirname($queueFile);
    if (!is_dir($dir)) {
        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException("Unable to create queue directory: {$dir}");
        }
    }

    $line = json_encode($job, JSON_UNESCAPED_SLASHES);
    if ($line === false) {
        throw new RuntimeException('Unable to encode queue job as JSON');
    }

    file_put_contents($queueFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function runImport(PDO $pdo, array $job): array
{
    $storyboardId = (int)($job['storyboard_id'] ?? 0);
    $minMapRunId  = (int)($job['min_map_run_id'] ?? 5680);
    $entityType   = (string)($job['entity_type'] ?? 'sketches');
    $sortStep     = (int)($job['sort_step'] ?? 10);
    $batchSize    = (int)($job['batch_size'] ?? 200);

    if ($storyboardId <= 0) {
        throw new InvalidArgumentException('Invalid storyboard_id');
    }
    if ($minMapRunId <= 0) {
        throw new InvalidArgumentException('Invalid min_map_run_id');
    }
    if ($sortStep <= 0) {
        throw new InvalidArgumentException('Invalid sort_step');
    }
    if ($batchSize <= 0) {
        throw new InvalidArgumentException('Invalid batch_size');
    }

    $stmt = $pdo->prepare('SELECT id, name, directory FROM storyboards WHERE id = ?');
    $stmt->execute([$storyboardId]);
    $storyboard = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$storyboard) {
        throw new RuntimeException("Storyboard not found: {$storyboardId}");
    }

    // Fetch ordered candidate frames in one query.
    $sql = "
        SELECT
            x.frame_id,
            x.latest_sketch_id,
            f.name,
            f.prompt,
            f.filename
        FROM (
            SELECT
                f.id AS frame_id,
                MAX(s.id) AS latest_sketch_id
            FROM frames f
            INNER JOIN map_runs mr
                ON mr.id = f.map_run_id
            INNER JOIN frames_2_sketches f2s
                ON f2s.from_id = f.id
            INNER JOIN sketches s
                ON s.id = f2s.to_id
            WHERE mr.entity_type = ?
              AND mr.id >= ?
            GROUP BY f.id
        ) x
        INNER JOIN frames f
            ON f.id = x.frame_id
        ORDER BY x.latest_sketch_id DESC, x.frame_id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$entityType, $minMapRunId]);
    $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($candidates)) {
        return [
            'success' => true,
            'storyboard_id' => $storyboardId,
            'storyboard_name' => $storyboard['name'],
            'matched_count' => 0,
            'imported_count' => 0,
            'skipped_existing_count' => 0,
            'error_count' => 0,
            'imported' => [],
            'skipped_existing_frame_ids' => [],
            'errors' => [],
            'message' => 'No matching frames found',
        ];
    }

    // Load already imported frame_ids once.
    $stmt = $pdo->prepare("
        SELECT frame_id
        FROM storyboard_frames
        WHERE storyboard_id = ?
    ");
    $stmt->execute([$storyboardId]);
    $existingFrameIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    $existingSet = [];
    foreach ($existingFrameIds as $existingId) {
        $existingSet[(int)$existingId] = true;
    }

    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(sort_order), 0)
        FROM storyboard_frames
        WHERE storyboard_id = ?
    ");
    $stmt->execute([$storyboardId]);
    $currentMaxSortOrder = (int)$stmt->fetchColumn();

    $pdo->beginTransaction();

    try {
        $imported = [];
        $skippedExisting = [];
        $errors = [];
        $sortOrder = $currentMaxSortOrder;

        $batchRows = [];
        $batchParams = [];

        $flushBatch = function () use (
            &$batchRows,
            &$batchParams,
            $pdo
        ): void {
            if (empty($batchRows)) {
                return;
            }

            $sql = "
                INSERT INTO storyboard_frames (
                    storyboard_id,
                    frame_id,
                    name,
                    description,
                    filename,
                    sort_order,
                    is_copied,
                    original_filename,
                    created_at,
                    updated_at
                ) VALUES " . implode(', ', $batchRows);

            $stmt = $pdo->prepare($sql);
            $stmt->execute($batchParams);

            $batchRows = [];
            $batchParams = [];
        };

        foreach ($candidates as $row) {
            $frameId = (int)$row['frame_id'];

            if (isset($existingSet[$frameId])) {
                $skippedExisting[] = $frameId;
                continue;
            }

            $sortOrder += $sortStep;

            $batchRows[] = "(?, ?, ?, ?, ?, ?, 0, ?, NOW(), NOW())";
            $batchParams[] = $storyboardId;
            $batchParams[] = $frameId;
            $batchParams[] = (string)$row['name'];
            $batchParams[] = $row['prompt'] ?? null;
            $batchParams[] = (string)$row['filename'];
            $batchParams[] = $sortOrder;
            $batchParams[] = (string)$row['filename'];

            $imported[] = [
                'frame_id' => $frameId,
                'latest_sketch_id' => (int)$row['latest_sketch_id'],
                'sort_order' => $sortOrder,
            ];

            if (count($batchRows) >= $batchSize) {
                $flushBatch();
            }
        }

        $flushBatch();

        $pdo->commit();

        return [
            'success' => true,
            'storyboard_id' => $storyboardId,
            'storyboard_name' => $storyboard['name'],
            'storyboard_directory' => $storyboard['directory'],
            'matched_count' => count($candidates),
            'imported_count' => count($imported),
            'skipped_existing_count' => count($skippedExisting),
            'error_count' => count($errors),
            'imported' => $imported,
            'skipped_existing_frame_ids' => $skippedExisting,
            'errors' => $errors,
            'order_rule' => 'latest sketches.id DESC, frames.id DESC',
            'sort_step' => $sortStep,
            'next_sort_order' => $sortOrder + $sortStep,
            'message' => sprintf(
                'Imported %d frame(s), skipped %d existing frame(s).',
                count($imported),
                count($skippedExisting)
            ),
        ];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

$argvOpts = getopt('', [
    'storyboard_id::',
    'min_map_run_id::',
    'entity_type::',
    'sort_step::',
    'batch_size::',
    'queue_enqueue',
    'queue_dequeue',
    'queue_file::',
    'qjobs::',
    'help',
]);

if (isset($argvOpts['help'])) {
    out("Usage:");
    out("  php storyboard_import_cli.php --storyboard_id=134 --min_map_run_id=5680 --entity_type=sketches");
    out("  php storyboard_import_cli.php");
    out("  php storyboard_import_cli.php --queue_enqueue --queue_file=/tmp/storyboard_import.queue.jsonl --storyboard_id=134");
    out("  php storyboard_import_cli.php --queue_dequeue --queue_file=/tmp/storyboard_import.queue.jsonl --qjobs=3");
    exit(0);
}

$queueFile = (string)($argvOpts['queue_file'] ?? '/tmp/storyboard_import.queue.jsonl');
$qjobs = max(1, (int)($argvOpts['qjobs'] ?? 1));

if (parseBoolFlag($argvOpts, 'queue_enqueue')) {
    $job = [
        'storyboard_id' => (int)($argvOpts['storyboard_id'] ?? 0),
        'min_map_run_id' => (int)($argvOpts['min_map_run_id'] ?? 5680),
        'entity_type' => (string)($argvOpts['entity_type'] ?? 'sketches'),
        'sort_step' => (int)($argvOpts['sort_step'] ?? 10),
        'batch_size' => (int)($argvOpts['batch_size'] ?? 200),
    ];
    appendQueueJob($queueFile, $job);
    out(json_encode([
        'success' => true,
        'message' => 'Job enqueued',
        'queue_file' => $queueFile,
        'job' => $job,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    exit(0);
}

if (parseBoolFlag($argvOpts, 'queue_dequeue')) {
    $jobs = loadQueueJobsFromFile($queueFile, $qjobs);
    if (empty($jobs)) {
        out(json_encode([
            'success' => true,
            'message' => 'No queued jobs found',
            'queue_file' => $queueFile,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        exit(0);
    }

    $results = [];
    foreach ($jobs as $job) {
        $results[] = runImport($pdo, $job);
    }

    rewriteQueueFileWithoutFirstN($queueFile, count($jobs));

    out(json_encode([
        'success' => true,
        'processed_jobs' => count($jobs),
        'results' => $results,
        'queue_file' => $queueFile,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    exit(0);
}

$hasAnyArgs =
    isset($argvOpts['storyboard_id']) ||
    isset($argvOpts['min_map_run_id']) ||
    isset($argvOpts['entity_type']) ||
    isset($argvOpts['sort_step']) ||
    isset($argvOpts['batch_size']);

$job = [];

if ($hasAnyArgs) {
    $job = [
        'storyboard_id' => (int)($argvOpts['storyboard_id'] ?? 134),
        'min_map_run_id' => (int)($argvOpts['min_map_run_id'] ?? 5680),
        'entity_type' => (string)($argvOpts['entity_type'] ?? 'sketches'),
        'sort_step' => (int)($argvOpts['sort_step'] ?? 10),
        'batch_size' => (int)($argvOpts['batch_size'] ?? 200),
    ];
} elseif (isInteractive()) {
    $job = [
        'storyboard_id' => (int)prompt('Storyboard ID', '134'),
        'min_map_run_id' => (int)prompt('Minimum map_run_id', '5680'),
        'entity_type' => (string)prompt('Entity type', 'sketches'),
        'sort_step' => (int)prompt('Sort step', '10'),
        'batch_size' => (int)prompt('Batch size', '200'),
    ];
} else {
    $job = [
        'storyboard_id' => 134,
        'min_map_run_id' => 5680,
        'entity_type' => 'sketches',
        'sort_step' => 10,
        'batch_size' => 200,
    ];
}

try {
    $result = runImport($pdo, $job);
    out(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    exit($result['success'] ? 0 : 1);
} catch (Throwable $e) {
    out(json_encode([
        'success' => false,
        'message' => $e->getMessage(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    exit(1);
}