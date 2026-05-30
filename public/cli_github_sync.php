<?php
// public/cli_github_sync.php
// =============================================================================
// CLI GITHUB SYNC
// Queue worker + direct CLI runner + interactive mode
// Uses forge_jobs.job_type = 'github_sync'
// =============================================================================

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

use Throwable;

// ═══════════════════════════════════════════════════════
// ANSI COLORS
// ═══════════════════════════════════════════════════════
$isTty = defined('STDOUT') && stream_isatty(STDOUT);
$useColor = $isTty;

if (!defined('C_RESET')) {
    define('C_RESET',  $useColor ? "\033[0m" : "");
    define('C_GREEN',  $useColor ? "\033[32m" : "");
    define('C_YELLOW', $useColor ? "\033[33m" : "");
    define('C_CYAN',   $useColor ? "\033[36m" : "");
    define('C_RED',    $useColor ? "\033[31m" : "");
    define('C_GRAY',   $useColor ? "\033[90m" : "");
    define('C_AMBER',  $useColor ? "\033[33m" : "");
}

function cecho(string $msg, string $color = C_RESET): void
{
    if ($color === "") {
        echo $msg;
    } else {
        echo $color . $msg . C_RESET;
    }
}

function prompt(string $label, ?string $default = null): string
{
    $suffix = $default !== null && $default !== '' ? " [{$default}]" : '';
    $line = readline($label . $suffix . ': ');
    $line = trim((string)$line);
    if ($line === '' && $default !== null) {
        return $default;
    }
    return $line;
}

function promptYesNo(string $label, bool $default = true): bool
{
    $suffix = $default ? ' [Y/n]' : ' [y/N]';
    $line = strtolower(trim((string)readline($label . $suffix . ': ')));
    if ($line === '') {
        return $default;
    }
    return in_array($line, ['y', 'yes', '1', 'true', 'on'], true);
}

function parseBool(mixed $value, bool $default = false): bool
{
    if ($value === null) return $default;
    if (is_bool($value)) return $value;
    if (is_int($value)) return $value !== 0;
    $v = strtolower(trim((string)$value));
    if ($v === '') return $default;
    return in_array($v, ['1', 'true', 'yes', 'y', 'on'], true);
}

function normalizeMessage(string $message): string
{
    $message = trim(preg_replace('/\s+/', ' ', $message) ?? '');
    if ($message === '') {
        $message = 'Auto commit from PHP';
    }
    return mb_substr($message, 0, 255);
}

function commandExists(string $bin): bool
{
    $cmd = 'command -v ' . escapeshellarg($bin) . ' >/dev/null 2>&1';
    exec($cmd, $out, $code);
    return $code === 0;
}

function buildGitCommand(string $repoPath, array $args): string
{
    $parts = ['git', '-C', $repoPath];
    foreach ($args as $arg) {
        $parts[] = $arg;
    }

    $cmd = [];
    foreach ($parts as $part) {
        $cmd[] = escapeshellarg((string)$part);
    }
    return implode(' ', $cmd);
}

function runCommand(string $command, ?string $cwd = null, array $env = []): array
{
    $descriptorSpec = [
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptorSpec, $pipes, $cwd, $env);

    if (!is_resource($process)) {
        return [
            'code' => 127,
            'stdout' => '',
            'stderr' => 'proc_open() failed',
        ];
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    $code = proc_close($process);

    return [
        'code' => (int)$code,
        'stdout' => (string)$stdout,
        'stderr' => (string)$stderr,
    ];
}

function jsonPretty(mixed $value): string
{
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
}

function writeJobSuccess(\PDO $pdo, int $jobId, array $result): void
{
    $stmt = $pdo->prepare("
        UPDATE forge_jobs
        SET status = 'done',
            result = ?,
            error_msg = NULL,
            finished_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([jsonPretty($result), $jobId]);
}

function writeJobFailure(\PDO $pdo, int $jobId, string $errorMessage, array $result = []): void
{
    $stmt = $pdo->prepare("
        UPDATE forge_jobs
        SET status = 'failed',
            error_msg = ?,
            result = ?,
            finished_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([
        mb_substr($errorMessage, 0, 5000),
        jsonPretty($result),
        $jobId
    ]);
}

function claimNextGitJob(\PDO $pdo, string $workerName): ?array
{
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare("
            SELECT *
            FROM forge_jobs
            WHERE job_type = 'github_sync'
              AND status = 'pending'
            ORDER BY priority ASC, id ASC
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute();
        $job = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            $pdo->commit();
            return null;
        }

        $upd = $pdo->prepare("
            UPDATE forge_jobs
            SET status = 'processing',
                started_at = NOW(),
                updated_at = NOW()
            WHERE id = ?
              AND status = 'pending'
        ");
        $upd->execute([(int)$job['id']]);

        if ($upd->rowCount() !== 1) {
            $pdo->rollBack();
            return null;
        }

        $pdo->commit();
        $job['status'] = 'processing';
        return $job;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
}

function gatherRepoState(string $repoPath): array
{
    $statusCmd = buildGitCommand($repoPath, ['status', '--porcelain=v1']);
    $statusRes  = runCommand($statusCmd, $repoPath);

    $branchCmd  = buildGitCommand($repoPath, ['rev-parse', '--abbrev-ref', 'HEAD']);
    $branchRes  = runCommand($branchCmd, $repoPath);

    $shaCmd     = buildGitCommand($repoPath, ['rev-parse', '--short', 'HEAD']);
    $shaRes     = runCommand($shaCmd, $repoPath);

    return [
        'has_changes' => trim($statusRes['stdout']) !== '',
        'status_code' => $statusRes['code'],
        'status'      => trim($statusRes['stdout']),
        'branch'      => trim($branchRes['stdout']),
        'sha'         => trim($shaRes['stdout']),
    ];
}

function runGitHubSync(array $cfg, bool $isQueueJob = false): array
{
    $repoPath   = trim((string)($cfg['repo_path'] ?? ''));
    $branchName = trim((string)($cfg['branch_name'] ?? 'main'));
    $remoteName = trim((string)($cfg['remote_name'] ?? 'origin'));
    $message    = normalizeMessage((string)($cfg['commit_message'] ?? 'Auto commit from PHP'));

    $addAll     = parseBool($cfg['add_all'] ?? true, true);
    $doCommit   = parseBool($cfg['commit'] ?? true, true);
    $doPush     = parseBool($cfg['push'] ?? true, true);
    $pullRebase = parseBool($cfg['pull_rebase'] ?? false, false);
    $amend      = parseBool($cfg['amend'] ?? false, false);
    $allowEmpty = parseBool($cfg['allow_empty'] ?? false, false);
    $dryRun     = parseBool($cfg['dry_run'] ?? false, false);
    $forcePush  = parseBool($cfg['force_push'] ?? false, false);

    $gitUserName  = trim((string)($cfg['git_user_name'] ?? (getenv('GIT_BOT_NAME') ?: 'Post Bot')));
    $gitUserEmail = trim((string)($cfg['git_user_email'] ?? (getenv('GIT_BOT_EMAIL') ?: 'post-bot@example.invalid')));

    if ($repoPath === '' || !is_dir($repoPath)) {
        throw new RuntimeException("Repository path does not exist: {$repoPath}");
    }
    if (!is_dir($repoPath . '/.git')) {
        throw new RuntimeException("Not a git repository: {$repoPath}");
    }

    /*
    $env = [
        'GIT_AUTHOR_NAME' => $gitUserName,
        'GIT_AUTHOR_EMAIL' => $gitUserEmail,
        'GIT_COMMITTER_NAME' => $gitUserName,
        'GIT_COMMITTER_EMAIL' => $gitUserEmail,
        'GIT_TERMINAL_PROMPT' => '0',
    ];
     */

$env = [
    'GIT_AUTHOR_NAME' => $gitUserName,
    'GIT_AUTHOR_EMAIL' => $gitUserEmail,
    'GIT_COMMITTER_NAME' => $gitUserName,
    'GIT_COMMITTER_EMAIL' => $gitUserEmail,
    'GIT_TERMINAL_PROMPT' => '0',
    'GH_TOKEN' => getenv('GH_TOKEN') ?: '',
];


    $steps = [];
    $outputs = [];
    $repoStateBefore = gatherRepoState($repoPath);

    $steps[] = [
        'name' => 'repo-state-before',
        'command' => '(internal)',
        'code' => 0,
        'stdout' => jsonPretty($repoStateBefore),
        'stderr' => '',
    ];

    if (commandExists('gh')) {
        $ghCmd = 'gh auth status --hostname github.com 2>&1';
        $ghRes = runCommand($ghCmd, $repoPath, $env);
        $steps[] = [
            'name' => 'gh auth status',
            'command' => $ghCmd,
            'code' => $ghRes['code'],
            'stdout' => $ghRes['stdout'],
            'stderr' => $ghRes['stderr'],
        ];
    } else {
        $steps[] = [
            'name' => 'gh auth status',
            'command' => '(gh not installed)',
            'code' => 0,
            'stdout' => '',
            'stderr' => '',
        ];
    }

    if ($pullRebase) {
        $pullCmd = buildGitCommand($repoPath, ['pull', '--rebase', $remoteName, $branchName]);
        if ($dryRun) {
            $steps[] = [
                'name' => 'pull-rebase',
                'command' => $pullCmd,
                'code' => 0,
                'stdout' => '[dry-run]',
                'stderr' => '',
            ];
        } else {
            $pullRes = runCommand($pullCmd, $repoPath, $env);
            $steps[] = [
                'name' => 'pull-rebase',
                'command' => $pullCmd,
                'code' => $pullRes['code'],
                'stdout' => $pullRes['stdout'],
                'stderr' => $pullRes['stderr'],
            ];
            if ($pullRes['code'] !== 0) {
                throw new RuntimeException("git pull --rebase failed");
            }
        }
    }

    if ($addAll) {
        $addCmd = buildGitCommand($repoPath, ['add', '-A']);
        if ($dryRun) {
            $steps[] = [
                'name' => 'add-all',
                'command' => $addCmd,
                'code' => 0,
                'stdout' => '[dry-run]',
                'stderr' => '',
            ];
        } else {
            $addRes = runCommand($addCmd, $repoPath, $env);
            $steps[] = [
                'name' => 'add-all',
                'command' => $addCmd,
                'code' => $addRes['code'],
                'stdout' => $addRes['stdout'],
                'stderr' => $addRes['stderr'],
            ];
            if ($addRes['code'] !== 0) {
                throw new RuntimeException("git add failed");
            }
        }
    }

    $repoStateAfterAdd = gatherRepoState($repoPath);
    $hasChanges = $repoStateAfterAdd['has_changes'];

    $commitDone = false;
    $commitSha  = '';

    if ($doCommit) {
        if ($hasChanges || $allowEmpty || $amend) {
            $commitArgs = ['commit'];
            if ($amend) {
                $commitArgs[] = '--amend';
            }
            if ($allowEmpty) {
                $commitArgs[] = '--allow-empty';
            }
            $commitArgs[] = '-m';
            $commitArgs[] = $message;

            $commitCmd = buildGitCommand($repoPath, $commitArgs);

            if ($dryRun) {
                $steps[] = [
                    'name' => 'commit',
                    'command' => $commitCmd,
                    'code' => 0,
                    'stdout' => '[dry-run]',
                    'stderr' => '',
                ];
                $commitDone = true;
            } else {
                $commitRes = runCommand($commitCmd, $repoPath, $env);
                $steps[] = [
                    'name' => 'commit',
                    'command' => $commitCmd,
                    'code' => $commitRes['code'],
                    'stdout' => $commitRes['stdout'],
                    'stderr' => $commitRes['stderr'],
                ];

                $combined = strtolower($commitRes['stdout'] . "\n" . $commitRes['stderr']);
                if ($commitRes['code'] !== 0 && str_contains($combined, 'nothing to commit')) {
                    $commitDone = false;
                } elseif ($commitRes['code'] !== 0) {
                    throw new RuntimeException("git commit failed");
                } else {
                    $commitDone = true;
                    $shaCmd = buildGitCommand($repoPath, ['rev-parse', '--short', 'HEAD']);
                    $shaRes = runCommand($shaCmd, $repoPath, $env);
                    $steps[] = [
                        'name' => 'commit-sha',
                        'command' => $shaCmd,
                        'code' => $shaRes['code'],
                        'stdout' => $shaRes['stdout'],
                        'stderr' => $shaRes['stderr'],
                    ];
                    if ($shaRes['code'] === 0) {
                        $commitSha = trim($shaRes['stdout']);
                    }
                }
            }
        } else {
            $steps[] = [
                'name' => 'commit',
                'command' => '(skipped - clean tree)',
                'code' => 0,
                'stdout' => 'Nothing to commit.',
                'stderr' => '',
            ];
        }
    }

    if ($doPush) {
        if (!$commitDone && $hasChanges && !$allowEmpty) {
            throw new RuntimeException("Push requested, but there are uncommitted changes and commit did not run successfully.");
        }

        $pushArgs = ['push'];
        if ($forcePush) {
            $pushArgs[] = '--force-with-lease';
        }
        $pushArgs[] = $remoteName;
        $pushArgs[] = 'HEAD:' . $branchName;

        $pushCmd = buildGitCommand($repoPath, $pushArgs);

        if ($dryRun) {
            $steps[] = [
                'name' => 'push',
                'command' => $pushCmd,
                'code' => 0,
                'stdout' => '[dry-run]',
                'stderr' => '',
            ];
        } else {
            $pushRes = runCommand($pushCmd, $repoPath, $env);
            $steps[] = [
                'name' => 'push',
                'command' => $pushCmd,
                'code' => $pushRes['code'],
                'stdout' => $pushRes['stdout'],
                'stderr' => $pushRes['stderr'],
            ];
            if ($pushRes['code'] !== 0) {
                throw new RuntimeException("git push failed");
            }
        }
    }

    $repoStateAfter = gatherRepoState($repoPath);

    $outputs['repo_path'] = $repoPath;
    $outputs['branch_name'] = $branchName;
    $outputs['remote_name'] = $remoteName;
    $outputs['message'] = $message;
    $outputs['dry_run'] = $dryRun;
    $outputs['commit_done'] = $commitDone;
    $outputs['commit_sha'] = $commitSha;
    $outputs['has_changes_before'] = $repoStateBefore['has_changes'];
    $outputs['has_changes_after'] = $repoStateAfter['has_changes'];
    $outputs['steps'] = $steps;

    return $outputs;
}

// ═══════════════════════════════════════════════════════
// ARGUMENT PARSING
// ═══════════════════════════════════════════════════════
$opts = getopt('', [
    'repo::',
    'branch::',
    'remote::',
    'message::',
    'commit::',
    'push::',
    'add-all::',
    'pull-rebase::',
    'amend::',
    'allow-empty::',
    'dry-run::',
    'force-push::',
    'git-user-name::',
    'git-user-email::',
    'qjobs::',
    'watch',
    'once',
    'sleep::',
    'limit::',
    'lock-file::',
]);

$hasQjobsParam = array_key_exists('qjobs', $opts);
$qjobs = isset($opts['qjobs']) ? max(0, (int)$opts['qjobs']) : 0;

$watch = array_key_exists('watch', $opts);
$once  = array_key_exists('once', $opts);
$sleepSeconds = isset($opts['sleep']) ? max(1, (int)$opts['sleep']) : 5;
$limit = isset($opts['limit']) ? max(1, (int)$opts['limit']) : 50;
$lockFile = $opts['lock-file'] ?? (sys_get_temp_dir() . '/cli_github_sync.lock');

$hasDirectParams =
    array_key_exists('repo', $opts) ||
    array_key_exists('branch', $opts) ||
    array_key_exists('remote', $opts) ||
    array_key_exists('message', $opts) ||
    array_key_exists('commit', $opts) ||
    array_key_exists('push', $opts) ||
    array_key_exists('add-all', $opts) ||
    array_key_exists('pull-rebase', $opts) ||
    array_key_exists('amend', $opts) ||
    array_key_exists('allow-empty', $opts) ||
    array_key_exists('dry-run', $opts) ||
    array_key_exists('force-push', $opts) ||
    array_key_exists('git-user-name', $opts) ||
    array_key_exists('git-user-email', $opts);

// ═══════════════════════════════════════════════════════
// INIT
// ═══════════════════════════════════════════════════════
$em = $spw->getEntityManager();
$pdo = $spw->getPDO();
$conn = $em->getConnection();

$lockHandle = fopen($lockFile, 'c');
if ($lockHandle === false) {
    fwrite(STDERR, "Unable to open lock file: {$lockFile}\n");
    exit(1);
}
if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    cecho("Another github sync worker is already running.\n", C_YELLOW);
    exit(0);
}

$workerName = gethostname() . ':' . getmypid();

// ═══════════════════════════════════════════════════════
// BUILD JOBS
// ═══════════════════════════════════════════════════════
$jobsToProcess = [];

// Queue mode — only when --qjobs is explicitly present with a positive value.
if ($hasQjobsParam && $qjobs > 0) {
    $remaining = $qjobs;

    while ($remaining > 0) {
        $job = claimNextGitJob($pdo, $workerName);
        if (!$job) {
            break;
        }
        $jobsToProcess[] = $job;
        $remaining--;
    }

    if (empty($jobsToProcess)) {
        cecho("No pending github_sync jobs.\n", C_YELLOW);
        exit(0);
    }

// Direct mode — use CLI args if any relevant argument is present.
} elseif ($hasDirectParams) {
    $payload = [
        'repo_path'       => $opts['repo'] ?? (getenv('GIT_REPO_PATH') ?: ''),
        'branch_name'     => $opts['branch'] ?? (getenv('GIT_BRANCH') ?: 'main'),
        'remote_name'     => $opts['remote'] ?? (getenv('GIT_REMOTE') ?: 'origin'),
        'commit_message'  => $opts['message'] ?? 'Auto commit from PHP',
        'add_all'         => array_key_exists('add-all', $opts) ? parseBool($opts['add-all'], true) : true,
        'commit'          => array_key_exists('commit', $opts) ? parseBool($opts['commit'], true) : true,
        'push'            => array_key_exists('push', $opts) ? parseBool($opts['push'], true) : true,
        'pull_rebase'     => array_key_exists('pull-rebase', $opts) ? parseBool($opts['pull-rebase'], false) : false,
        'amend'           => array_key_exists('amend', $opts) ? parseBool($opts['amend'], false) : false,
        'allow_empty'     => array_key_exists('allow-empty', $opts) ? parseBool($opts['allow-empty'], false) : false,
        'dry_run'         => array_key_exists('dry-run', $opts) ? parseBool($opts['dry-run'], false) : false,
        'force_push'      => array_key_exists('force-push', $opts) ? parseBool($opts['force-push'], false) : false,
        'git_user_name'   => $opts['git-user-name'] ?? (getenv('GIT_BOT_NAME') ?: 'Post Bot'),
        'git_user_email'  => $opts['git-user-email'] ?? (getenv('GIT_BOT_EMAIL') ?: 'post-bot@example.invalid'),
    ];

    $jobsToProcess[] = [
        'id' => null,
        'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];

// Interactive mode — no usable CLI params.
} else {
    cecho("\nGITHUB SYNC — Interactive Mode\n", C_CYAN);

    $defaultRepo   = getenv('GIT_REPO_PATH') ?: dirname(__DIR__);
    $defaultBranch = getenv('GIT_BRANCH') ?: 'main';
    $defaultRemote = getenv('GIT_REMOTE') ?: 'origin';
    $defaultName   = getenv('GIT_BOT_NAME') ?: 'Post Bot';
    $defaultEmail  = getenv('GIT_BOT_EMAIL') ?: 'post-bot@example.invalid';

    $repoPath = prompt('Repository path', $defaultRepo);
    $branch   = prompt('Branch name', $defaultBranch);
    $remote   = prompt('Remote name', $defaultRemote);
    $message  = prompt('Commit message', 'Auto commit from PHP');

    $addAll     = promptYesNo('Run git add -A', true);
    $doCommit   = promptYesNo('Run git commit', true);
    $doPush     = promptYesNo('Run git push', true);
    $pullRebase = promptYesNo('Run git pull --rebase first', false);
    $amend      = promptYesNo('Use --amend', false);
    $allowEmpty = promptYesNo('Allow empty commit', false);
    $dryRun     = promptYesNo('Dry run', false);
    $forcePush  = promptYesNo('Force push with lease', false);

    $gitUserName  = prompt('Git author name', $defaultName);
    $gitUserEmail = prompt('Git author email', $defaultEmail);

    $payload = [
        'repo_path'       => $repoPath,
        'branch_name'     => $branch,
        'remote_name'     => $remote,
        'commit_message'  => $message,
        'add_all'         => $addAll,
        'commit'          => $doCommit,
        'push'            => $doPush,
        'pull_rebase'     => $pullRebase,
        'amend'           => $amend,
        'allow_empty'     => $allowEmpty,
        'dry_run'         => $dryRun,
        'force_push'      => $forcePush,
        'git_user_name'   => $gitUserName,
        'git_user_email'  => $gitUserEmail,
    ];

    $jobsToProcess[] = [
        'id' => null,
        'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
}

// ═══════════════════════════════════════════════════════
// EXECUTION
// ═══════════════════════════════════════════════════════
$processed = 0;

foreach ($jobsToProcess as $jobRow) {
    if ($processed >= $limit) {
        break;
    }

    $jobId = isset($jobRow['id']) ? (int)$jobRow['id'] : null;
    $cfg = json_decode((string)($jobRow['payload'] ?? '{}'), true);
    if (!is_array($cfg)) {
        $cfg = [];
    }

    if ($jobId !== null) {
        cecho("\n[JOB #{$jobId}] github_sync\n", C_AMBER);
    } else {
        cecho("\n[DIRECT] github_sync\n", C_AMBER);
    }

    try {
        $result = runGitHubSync($cfg, $jobId !== null);

        foreach ($result['steps'] as $step) {
            $name = (string)($step['name'] ?? 'step');
            $code = (int)($step['code'] ?? 0);
            $stdout = trim((string)($step['stdout'] ?? ''));
            $stderr = trim((string)($step['stderr'] ?? ''));

            if ($name === 'repo-state-before') {
                cecho("Repo state: {$stdout}\n", C_GRAY);
                continue;
            }

            $prefix = $code === 0 ? '✓' : '✗';
            $color  = $code === 0 ? C_GREEN : C_RED;

            cecho("{$prefix} {$name}\n", $color);

            if ($stdout !== '') {
                cecho($stdout . "\n", C_GRAY);
            }
            if ($stderr !== '') {
                cecho($stderr . "\n", C_YELLOW);
            }

            if ($code !== 0) {
                throw new RuntimeException("Step '{$name}' failed");
            }
        }

        if ($jobId !== null) {
            writeJobSuccess($pdo, $jobId, $result);
            cecho("Job #{$jobId} completed.\n", C_GREEN);
        } else {
            cecho("Direct github sync completed.\n", C_GREEN);
        }

    } catch (Throwable $ex) {
        $msg = $ex->getMessage();
        cecho("ERROR: {$msg}\n", C_RED);

        if ($jobId !== null) {
            $result = [
                'error' => $msg,
                'payload' => $cfg,
            ];
            writeJobFailure($pdo, $jobId, $msg, $result);
            cecho("Job #{$jobId} marked failed.\n", C_RED);
        } else {
            exit(1);
        }
    }

    $processed++;

    if ($watch && !$once && $hasQjobsParam && $qjobs > 0) {
        // In watch mode, keep draining the queue after the first pass.
        $next = claimNextGitJob($pdo, $workerName);
        if ($next) {
            $jobsToProcess[] = $next;
        } else {
            sleep($sleepSeconds);
        }
    }
}

if ($hasQjobsParam && $qjobs > 0) {
    cecho("\nProcessed {$processed} queued github_sync job(s).\n", C_CYAN);
} elseif ($hasDirectParams) {
    cecho("\nDone.\n", C_CYAN);
} else {
    cecho("\nDone.\n", C_CYAN);
}

