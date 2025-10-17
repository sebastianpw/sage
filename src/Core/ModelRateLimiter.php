<?php
// src/Core/ModelRateLimiter.php
namespace App\Core;

/**
 * Simple file-backed rate limiter for model-level RPM/TPM/RPD/TPD enforcement.
 *
 * - Keeps counters for minute and day windows.
 * - Persists state to $stateFile (json).
 * - acquire($modelId, $reqTokens = 0, $reqCount = 1) will block (sleep) until the request
 *   can be issued without exceeding limits. Blocking is intentional for CLI analyzer runs.
 *
 * NOTE: For multi-process environments prefer DB+transactions or a centralized redis token-bucket.
 */
class ModelRateLimiter
{
    private string $stateFile;
    private array $limits; // model -> ['rpm'=>, 'rpd'=>, 'tpm'=>, 'tpd'=>]
    private array $state;

    /**
     * Default limits derived from provider table (conservative).
     * Keep this updated from provider dashboards if values change.
     */
    private static array $DEFAULT_LIMITS = [
        // Groq / general models (from provided table)
        'allam-2-7b' => ['rpm'=>30,'rpd'=>7000,'tpm'=>6000,'tpd'=>500000],
        'groq/compound' => ['rpm'=>30,'rpd'=>250,'tpm'=>70000,'tpd'=>70000],
        'groq/compound-mini' => ['rpm'=>30,'rpd'=>250,'tpm'=>70000,'tpd'=>70000],
        'llama-3.1-8b-instant' => ['rpm'=>30,'rpd'=>14400,'tpm'=>6000,'tpd'=>500000],
        'llama-3.3-70b-versatile' => ['rpm'=>30,'rpd'=>1000,'tpm'=>12000,'tpd'=>100000],
        'meta-llama/llama-4-maverick-17b-128e-instruct' => ['rpm'=>30,'rpd'=>1000,'tpm'=>8000,'tpd'=>200000],
        'meta-llama/llama-4-scout-17b-16e-instruct' => ['rpm'=>30,'rpd'=>1000,'tpm'=>30000,'tpd'=>500000],
        'meta-llama/llama-guard-4-12b' => ['rpm'=>30,'rpd'=>14400,'tpm'=>15000,'tpd'=>500000],
        'meta-llama/llama-prompt-guard-2-22m' => ['rpm'=>30,'rpd'=>14400,'tpm'=>15000,'tpd'=>500000],
        'meta-llama/llama-prompt-guard-2-86m' => ['rpm'=>30,'rpd'=>14400,'tpm'=>15000,'tpd'=>500000],
        'moonshotai/kimi-k2-instruct' => ['rpm'=>60,'rpd'=>1000,'tpm'=>10000,'tpd'=>300000],
        'moonshotai/kimi-k2-instruct-0905' => ['rpm'=>60,'rpd'=>1000,'tpm'=>10000,'tpd'=>300000],
        'openai/gpt-oss-120b' => ['rpm'=>30,'rpd'=>1000,'tpm'=>8000,'tpd'=>200000],
        'openai/gpt-oss-20b' => ['rpm'=>30,'rpd'=>1000,'tpm'=>8000,'tpd'=>200000],
        'playai-tts' => ['rpm'=>10,'rpd'=>100,'tpm'=>1200,'tpd'=>3600],
        'playai-tts-arabic' => ['rpm'=>10,'rpd'=>100,'tpm'=>1200,'tpd'=>3600],
        'qwen/qwen3-32b' => ['rpm'=>60,'rpd'=>1000,'tpm'=>6000,'tpd'=>500000],
        'qwen2.5-coder-32b-instruct' => ['rpm'=>60,'rpd'=>1000,'tpm'=>6000,'tpd'=>500000],
        'whisper-large-v3' => ['rpm'=>20,'rpd'=>2000,'tpm'=>0,'tpd'=>0,'ash'=>7200,'asd'=>28800],
        'whisper-large-v3-turbo' => ['rpm'=>20,'rpd'=>2000,'tpm'=>0,'tpd'=>0,'ash'=>7200,'asd'=>28800],
        // commonly referenced models (generic conservative defaults)
        'gpt-4' => ['rpm'=>60,'rpd'=>1000,'tpm'=>120000,'tpd'=>1000000],
        'gpt-4o' => ['rpm'=>60,'rpd'=>1000,'tpm'=>200000,'tpd'=>1000000],
        // Add any other models you use here, or override via constructor
    ];

    public function __construct(?string $stateFile = null, array $limits = [])
    {
        $home = getenv('HOME') ?: '/tmp';
        $this->stateFile = $stateFile ?: ($home . '/.codeintel_rate_state.json');

        // merge provided limits with defaults (provided overrides take precedence)
        $this->limits = $limits + self::$DEFAULT_LIMITS;
        $this->loadState();
    }

    private function loadState(): void
    {
        if (is_readable($this->stateFile)) {
            $s = @file_get_contents($this->stateFile);
            $this->state = is_string($s) ? (json_decode($s, true) ?: []) : [];
        } else {
            $this->state = [];
        }
    }

    private function saveState(): void
    {
        // best-effort atomic write
        $tmp = $this->stateFile . '.tmp';
        @file_put_contents($tmp, json_encode($this->state));
        @rename($tmp, $this->stateFile);
    }

    /**
     * Acquire permission to send one or more requests consuming $reqTokens tokens.
     * Blocks (sleep) until allowed.
     *
     * @param string $modelId
     * @param int $reqTokens estimated tokens this request will use (input+output)
     * @param int $reqCount number of requests (usually 1)
     */
    public function acquire(string $modelId, int $reqTokens = 0, int $reqCount = 1): void
    {
        $limits = $this->limits[$modelId] ?? null;
        if ($limits === null) {
            // try lowercased model id fallback
            $lc = strtolower($modelId);
            $limits = $this->limits[$lc] ?? null;
        }
        if ($limits === null) {
            // no limits known — proceed without blocking
            return;
        }

        // normalize limit keys (ensure integers)
        $rpm = (int)($limits['rpm'] ?? PHP_INT_MAX);
        $tpm = (int)($limits['tpm'] ?? PHP_INT_MAX);
        $rpd = (int)($limits['rpd'] ?? PHP_INT_MAX);
        $tpd = (int)($limits['tpd'] ?? PHP_INT_MAX);

        // ensure state shapes exist
        if (!isset($this->state[$modelId])) {
            $this->state[$modelId] = ['minutes' => [], 'days' => []];
        }

        while (true) {
            $now = time();
            $minuteKey = (int) floor($now / 60); // epoch minutes
            $dayKey = (int) floor($now / 86400); // epoch days
            $this->loadState(); // reload in case other processes updated it

            if (!isset($this->state[$modelId])) {
                $this->state[$modelId] = ['minutes' => [], 'days' => []];
            }

            // initialize minute/day buckets if absent
            $mArr = &$this->state[$modelId]['minutes'];
            $dArr = &$this->state[$modelId]['days'];

            // clean old minute entries (keep only current minuteKey)
            foreach ($mArr as $k => $v) {
                if ((int)$k !== $minuteKey) {
                    unset($mArr[$k]);
                }
            }

            // clean old day entries (keep only current dayKey)
            foreach ($dArr as $k => $v) {
                if ((int)$k !== $dayKey) {
                    unset($dArr[$k]);
                }
            }

            $minuteState = $mArr[$minuteKey] ?? ['requests' => 0, 'tokens' => 0];
            $dayState = $dArr[$dayKey] ?? ['requests' => 0, 'tokens' => 0];

            $willMinuteRequests = $minuteState['requests'] + $reqCount;
            $willMinuteTokens = $minuteState['tokens'] + $reqTokens;

            $willDayRequests = $dayState['requests'] + $reqCount;
            $willDayTokens = $dayState['tokens'] + $reqTokens;

            $ok = $willMinuteRequests <= $rpm && $willMinuteTokens <= $tpm && $willDayRequests <= $rpd && $willDayTokens <= $tpd;

            if ($ok) {
                // update state and persist
                $mArr[$minuteKey] = ['requests' => $willMinuteRequests, 'tokens' => $willMinuteTokens];
                $dArr[$dayKey] = ['requests' => $willDayRequests, 'tokens' => $willDayTokens];
                $this->saveState();
                return;
            }

            // Compute earliest wake time (in seconds) to satisfy minute/day windows
            $sleepSeconds = 1; // default minimal backoff
            // if minute limits exceeded, wait until next minute boundary:
            if ($willMinuteRequests > $rpm || $willMinuteTokens > $tpm) {
                $nextMinuteStart = ($minuteKey + 1) * 60;
                $sleepSeconds = max($sleepSeconds, $nextMinuteStart - $now);
            }
            // if day limits exceeded, wait until next day boundary:
            if ($willDayRequests > $rpd || $willDayTokens > $tpd) {
                $nextDayStart = ($dayKey + 1) * 86400;
                $sleepSeconds = max($sleepSeconds, $nextDayStart - $now);
            }

            // Sleep (block) for computed seconds (capped)
            $sleepSeconds = (int) max(1, min(30, $sleepSeconds)); // cap to 30s to keep CLI responsive
            sleep($sleepSeconds);
            // then loop and re-check
        }
    }

    /**
     * Optionally allow external updating of limits (hot-reload)
     */
    public function updateLimits(array $newLimits): void
    {
        $this->limits = $newLimits + $this->limits;
    }
}
