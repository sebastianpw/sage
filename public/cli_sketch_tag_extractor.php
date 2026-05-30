<?php
// cli_sketch_tag_extractor.php
// Sketch Tag Extractor (Pass 1) — Hybrid Deterministic + AI Taxonomic Distillation
// Deterministic pass: controlled-vocab fields from sketch_sequence_analysis
// AI pass: freetext/poetic fields via GeneratorService + generator_config row
//
// Usage:
//   php cli_sketch_tag_extractor.php --from=1000 --to=2000
//   php cli_sketch_tag_extractor.php --from=1000 --to=2000 --batch=5
//   php cli_sketch_tag_extractor.php --from=1000 --to=2000 --dry-run
//   php cli_sketch_tag_extractor.php --qjobs=2
//
// Queue job_type: 'sketch_tag_extract'
// Payload keys:  from_id, to_id, batch, dry_run
// ----------------------------------------------------

if (php_sapi_name() !== 'cli') die("CLI only\n");

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;

// ═══════════════════════════════════════════════════════
// ANSI COLORS (Smart toggling for logs/queue)
// ═══════════════════════════════════════════════════════
$isTty = defined('STDOUT') && stream_isatty(STDOUT);
$isQueue = false;
foreach ($argv ?? [] as $arg) {
    if (strpos($arg, '--qjobs') === 0) $isQueue = true;
}
$useColor = $isTty && !$isQueue;

if (!defined('C_RESET')) {
    define('C_RESET',  $useColor ? "\033[0m" : "");
    define('C_GREEN',  $useColor ? "\033[0m\033[32m" : "");
    define('C_YELLOW', $useColor ? "\033[0m\033[33m" : "");
    define('C_CYAN',   $useColor ? "\033[0m\033[36m" : "");
    define('C_RED',    $useColor ? "\033[0m\033[31m" : "");
    define('C_GRAY',   $useColor ? "\033[0m\033[90m" : "");
    define('C_AMBER',  $useColor ? "\033[0m\033[33m" : "");
}

function cecho(string $msg, string $color = C_RESET): void {
    if ($color === "") {
        echo $msg;
    } else {
        echo $color . $msg . C_RESET;
    }
}

// ═══════════════════════════════════════════════════════
// CANONICALIZATION
// ═══════════════════════════════════════════════════════
function canonicalizeTag(string $raw): string {
    $t = mb_strtolower(trim($raw), 'UTF-8');
    // Strip anything not letter, digit, hyphen, or space
    $t = preg_replace('/[^a-z0-9\-\s]/u', '', $t);
    // Collapse repeated spaces and hyphens
    $t = preg_replace('/\s+/', ' ', $t);
    $t = preg_replace('/-+/', '-', $t);
    // Trim stray edge characters
    $t = trim($t, ' -');
    // Enforce DB limit, trim again after substr
    $t = trim(mb_substr($t, 0, 50, 'UTF-8'), ' -');
    return $t;
}

const STOPWORDS = [
    'a','an','the','and','or','of','in','on','at','to','for',
    'by','with','is','are','was','were','be','been','it','its',
];

function isValidTag(string $tag): bool {
    if (strlen($tag) < 3) return false;
    foreach (explode(' ', $tag) as $word) {
        if (!in_array($word, STOPWORDS, true)) return true;
    }
    return false;
}

// ═══════════════════════════════════════════════════════
// ROBUST JSON EXTRACTION
// Handles markdown fences, leading garbage, trailing commas
// ═══════════════════════════════════════════════════════
function extractJsonRobust(string $raw): mixed {
    $raw = trim($raw);
    $raw = preg_replace('/^\x{FEFF}/u', '', $raw);

    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) return $decoded;

    $tryDecode = function(string $s): mixed {
        $s = str_replace(
            ["\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x9C", "\xE2\x80\x9D"],
            ["'", "'", '"', '"'],
            $s
        );
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s);
        $d = json_decode($s, true);
        if (json_last_error() === JSON_ERROR_NONE) return $d;
        $fixed = preg_replace('/,\s*(\]|\})/m', '$1', $s);
        $d = json_decode($fixed, true);
        if (json_last_error() === JSON_ERROR_NONE) return $d;
        return null;
    };

    if (preg_match_all('/```(?:json)?\s*(.*?)\s*```/is', $raw, $blocks)) {
        foreach ($blocks[1] as $block) {
            $d = $tryDecode($block);
            if ($d !== null) return $d;
        }
    }

    $start = strpos($raw, '{');
    if ($start !== false) {
        $depth = 0; $inStr = false; $esc = false;
        for ($i = $start, $len = strlen($raw); $i < $len; $i++) {
            $ch = $raw[$i];
            if ($inStr) {
                if ($esc)         { $esc = false; continue; }
                if ($ch === '\\') { $esc = true;  continue; }
                if ($ch === '"')  { $inStr = false; continue; }
            } else {
                if ($ch === '"')  { $inStr = true; continue; }
                if ($ch === '{')  $depth++;
                elseif ($ch === '}') {
                    $depth--;
                    if ($depth === 0) {
                        $sub = substr($raw, $start, $i - $start + 1);
                        $d = $tryDecode($sub);
                        if ($d !== null) return $d;
                        break;
                    }
                }
            }
        }
    }

    $arrStart = strpos($raw, '[');
    $arrEnd   = strrpos($raw, ']');
    if ($arrStart !== false && $arrEnd !== false && $arrEnd > $arrStart) {
        $d = $tryDecode(substr($raw, $arrStart, $arrEnd - $arrStart + 1));
        if ($d !== null) return $d;
    }

    return null;
}

// ═══════════════════════════════════════════════════════
// DETERMINISTIC EXTRACTOR
// Pulls clean tags from controlled-vocab fields only.
// No scoring, no quality numbers.
// ═══════════════════════════════════════════════════════
function extractDeterministicTags(array $row): array {
    $tags = [];

    $resolveField = function(mixed $val): array {
        if (empty($val)) return [];
        if (is_string($val)) {
            $decoded = json_decode($val, true);
            if (is_array($decoded)) return $decoded;
            return [trim($val)];
        }
        if (is_array($val)) return $val;
        return [];
    };

    // narrative_function — JSON array e.g. ["REVELATION","COMPLICATION"]
    foreach ($resolveField($row['narrative_function'] ?? '') as $nf) {
        if (is_string($nf) && trim($nf)) $tags[] = strtolower(trim($nf));
    }

    // layer — JSON array e.g. ["world","theme"]
    foreach ($resolveField($row['layer'] ?? '') as $ly) {
        if (is_string($ly) && trim($ly)) $tags[] = strtolower(trim($ly)) . ' layer';
    }

    // energy — scalar e.g. "turn"
    if (!empty($row['energy'])) {
        $tags[] = strtolower(trim($row['energy'])) . ' energy';
    }

    // position — scalar e.g. "opener"
    if (!empty($row['position'])) {
        $tags[] = strtolower(trim($row['position']));
    }

    // shot_scale — scalar e.g. "establishing"
    if (!empty($row['shot_scale'])) {
        $tags[] = strtolower(trim($row['shot_scale'])) . ' shot';
    }

    // intensity — scalar e.g. "high"
    if (!empty($row['intensity'])) {
        $tags[] = strtolower(trim($row['intensity'])) . ' intensity';
    }

    // structure_type — scalar e.g. "non-linear"
    if (!empty($row['structure_type'])) {
        $tags[] = strtolower(trim($row['structure_type']));
    }

    // standalone — scalar e.g. "provides-context"
    if (!empty($row['standalone'])) {
        $tags[] = str_replace('-', ' ', strtolower(trim($row['standalone'])));
    }

    // edit_relationship — scalar e.g. "leads-to-action"
    if (!empty($row['edit_relationship'])) {
        $tags[] = str_replace('-', ' ', strtolower(trim($row['edit_relationship'])));
    }

    // fabula_position — scalar e.g. "early"
    if (!empty($row['fabula_position'])) {
        $tags[] = strtolower(trim($row['fabula_position']));
    }

    // syuzhet_position — only emit if different from fabula_position
    if (!empty($row['syuzhet_position']) && $row['syuzhet_position'] !== $row['fabula_position']) {
        $tags[] = 'syuzhet ' . strtolower(trim($row['syuzhet_position']));
    }

    // world_specificity — scalar e.g. "world-locked"
    if (!empty($row['world_specificity'])) {
        $tags[] = str_replace('-', ' ', strtolower(trim($row['world_specificity'])));
    }

    // character_presence — scalar e.g. "featured"
    if (!empty($row['character_presence'])) {
        $tags[] = strtolower(trim($row['character_presence'])) . ' character presence';
    }

    $out = [];
    foreach ($tags as $t) {
        $c = canonicalizeTag($t);
        if (isValidTag($c)) $out[$c] = true;
    }
    return array_keys($out);
}

// ═══════════════════════════════════════════════════════
// CONFIG ID
// ═══════════════════════════════════════════════════════
const GENERATOR_CONFIG_ID = 'sketch_tag_extractor_v1';

// ═══════════════════════════════════════════════════════
// CORE EXTRACTION RUNNER
// Shared by both direct-CLI and queue-job modes.
// ═══════════════════════════════════════════════════════
function runExtraction(
    PDO              $pdo,
    GeneratorService $service,
    GeneratorConfig  $config,
    int              $fromId,
    int              $toId,
    int              $batch,
    bool             $dryRun
): array {
    $configModel = method_exists($config, 'getModel') ? $config->getModel() : '(from config)';

    cecho("\n🧠 SKETCH TAG EXTRACTOR (Pass 1)\n", C_CYAN);
    cecho("   Range   : #$fromId → #$toId\n",        C_GRAY);
    cecho("   Batch   : $batch sketches per chunk\n", C_GRAY);
    cecho("   Model   : $configModel\n",              C_GRAY);
    cecho("   Config  : " . GENERATOR_CONFIG_ID . "\n", C_GRAY);
    cecho("   Dry Run : " . ($dryRun ? 'YES (no writes)' : 'NO') . "\n", C_GRAY);
    echo "\n";

    $idStmt = $pdo->prepare("SELECT id FROM sketches WHERE id >= ? AND id <= ? ORDER BY id ASC");
    $idStmt->execute([$fromId, $toId]);
    $allIds = $idStmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($allIds)) {
        cecho("No sketches found in range #$fromId–#$toId.\n", C_YELLOW);
        return ['sketches' => 0, 'tags_new' => 0, 'links_new' => 0, 'errors' => 0];
    }

    cecho("Found " . count($allIds) . " sketches. Starting hybrid extraction...\n\n", C_GREEN);

    $totalSketches = 0;
    $totalTagsNew  = 0;
    $totalLinksNew = 0;
    $totalErrors   = 0;

    $chunks = array_chunk($allIds, $batch);

    foreach ($chunks as $chunkIndex => $sketchIds) {
        $minId = min($sketchIds);
        $maxId = max($sketchIds);
        
        
       cecho("Chunk " . ($chunkIndex + 1) . "/" . count($chunks) . " [#{$minId}–#{$maxId}]: ", C_CYAN);
        
        
        
        try {
            $inClause = implode(',', array_fill(0, count($sketchIds), '?'));

            $sql = "
                SELECT
                    s.id                            AS sketch_id,
                    sa.entities,
                    sa.thematics,
                    sa.classification,
                    sa.recommendations,
                    ssa.narrative_function,
                    ssa.layer,
                    ssa.energy,
                    ssa.position,
                    ssa.shot_scale,
                    ssa.intensity,
                    ssa.structure_type,
                    ssa.standalone,
                    ssa.edit_relationship,
                    ssa.fabula_position,
                    ssa.syuzhet_position,
                    ssa.character_presence,
                    ssa.world_specificity
                FROM sketches s
                LEFT JOIN sketch_analysis sa            ON s.id = sa.sketch_id
                LEFT JOIN sketch_sequence_analysis ssa  ON s.id = ssa.sketch_id
                WHERE s.id IN ($inClause)
            ";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($sketchIds);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $tagsToCreate = []; // canonical_tag => true
            $sketchToTags = []; // sketch_id => [canonical_tag => true]

            foreach ($rows as $row) {
                $sId = $row['sketch_id'];
                $sketchToTags[$sId] = [];

                // ── 1. DETERMINISTIC PASS ────────────────────
                $detTags = extractDeterministicTags($row);
                foreach ($detTags as $dt) {
                    $tagsToCreate[$dt]       = true;
                    $sketchToTags[$sId][$dt] = true;
                }
                echo C_GRAY . "d" . C_RESET;

                // ── 2. AI PASS — freetext/poetic fields only ─
                $aiInput = [];

                $entities  = extractJsonRobust($row['entities']        ?? '') ?? [];
                $thematics = extractJsonRobust($row['thematics']       ?? '') ?? [];
                $recs      = extractJsonRobust($row['recommendations'] ?? '') ?? [];
                $classif   = extractJsonRobust($row['classification']  ?? '') ?? [];

                if (!empty($entities))  $aiInput['entities']        = $entities;
                if (!empty($thematics)) $aiInput['thematics']       = $thematics;
                if (!empty($recs))      $aiInput['recommendations'] = $recs;

                if (is_array($classif)) {
                    $classifProse = [];
                    foreach (['narrative_function', 'emotional_tone', 'visual_style'] as $proseKey) {
                        if (!empty($classif[$proseKey]) && is_string($classif[$proseKey])) {
                            $classifProse[$proseKey] = $classif[$proseKey];
                        }
                    }
                    if (!empty($classifProse)) $aiInput['classification'] = $classifProse;
                }

                if (!empty($aiInput)) {
                    $retries   = 0;
                    $aiSuccess = false;

                    while (!$aiSuccess && $retries < 2) {
                        try {
                            if ($retries > 0) sleep(2);

                            $userPrompt = "Extract taxonomy tags from this sketch analysis data:\n"
                                . json_encode($aiInput, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                            $res    = $service->generate($config, ['entity_name' => $userPrompt]);
                            $rawStr = is_object($res) && method_exists($res, 'getRawResponse')
                                ? $res->getRawResponse()
                                : (string)$res;

                            $aiParsed = extractJsonRobust($rawStr);

                            if (!is_array($aiParsed) && is_object($res) && method_exists($res, 'getData')) {
                                $aiParsed = $res->getData();
                            }

                            if (is_array($aiParsed)) {
                                foreach ($aiParsed as $categoryTags) {
                                    if (!is_array($categoryTags)) continue;
                                    foreach ($categoryTags as $tag) {
                                        if (!is_string($tag)) continue;
                                        $c = canonicalizeTag($tag);
                                        if (isValidTag($c)) {
                                            $tagsToCreate[$c]        = true;
                                            $sketchToTags[$sId][$c]  = true;
                                        }
                                    }
                                }
                                echo C_GREEN . "a" . C_RESET;
                            } else {
                                echo C_YELLOW . "?" . C_RESET;
                            }
                            $aiSuccess = true;

                        } catch (Exception $aiEx) {
                            $retries++;
                            echo C_RED . "x" . C_RESET;
                            if ($retries >= 2) {
                                cecho("\n  AI error sketch #$sId: " . $aiEx->getMessage() . "\n", C_RED);
                                $totalErrors++;
                            }
                        }
                    }
                } else {
                    echo C_GRAY . "-" . C_RESET;
                }
            }

            // ── DB WRITES ────────────────────────────────────
            $newTagsCount  = 0;
            $newLinksCount = 0;

            if (!empty($tagsToCreate) && !$dryRun) {
                $pdo->beginTransaction();
                try {
                    $tagStmt = $pdo->prepare(
                        "INSERT IGNORE INTO tags (name, show_in_ui, created_at, updated_at) VALUES (?, 0, NOW(), NOW())"
                    );
                    foreach (array_keys($tagsToCreate) as $tagName) {
                        $tagStmt->execute([$tagName]);
                        if ($tagStmt->rowCount() > 0) $newTagsCount++;
                    }

                    $tagNames   = array_keys($tagsToCreate);
                    $tagIn      = implode(',', array_fill(0, count($tagNames), '?'));
                    $tagMapStmt = $pdo->prepare("SELECT id, name FROM tags WHERE name IN ($tagIn)");
                    $tagMapStmt->execute($tagNames);
                    $tagMap = [];
                    foreach ($tagMapStmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
                        $tagMap[$t['name']] = $t['id'];
                    }

                    $linkStmt = $pdo->prepare(
                        "INSERT IGNORE INTO tags_2_sketches (from_id, to_id) VALUES (?, ?)"
                    );
                    foreach ($sketchToTags as $sId => $tags) {
                        foreach (array_keys($tags) as $tagName) {
                            if (isset($tagMap[$tagName])) {
                                $linkStmt->execute([$tagMap[$tagName], $sId]);
                                if ($linkStmt->rowCount() > 0) $newLinksCount++;
                            }
                        }
                    }

                    $pdo->commit();

                } catch (Exception $txEx) {
                    $pdo->rollBack();
                    throw $txEx;
                }

            } elseif ($dryRun && !empty($tagsToCreate)) {
                $newTagsCount  = count($tagsToCreate);
                $newLinksCount = array_sum(array_map('count', $sketchToTags));
            }

            $totalSketches += count($rows);
            $totalTagsNew  += $newTagsCount;
            $totalLinksNew += $newLinksCount;

            $suffix = $dryRun ? ' (dry run)' : '';
            cecho(
                " → +{$newTagsCount} tags, +{$newLinksCount} links{$suffix}\n",
                $newLinksCount > 0 ? C_GREEN : C_GRAY
            );

        } catch (Exception $chunkEx) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            cecho("\n  Chunk error: " . $chunkEx->getMessage() . "\n", C_RED);
            $totalErrors++;
        }
    }

    echo "\n";
    cecho("─────────────────────────────────────────\n", C_GRAY);
    cecho("EXTRACTION COMPLETE" . ($dryRun ? " (DRY RUN)" : "") . "\n", C_CYAN);
    cecho("  Sketches processed : $totalSketches\n", C_RESET);
    cecho("  New taxonomy tags  : $totalTagsNew\n",  C_AMBER);
    cecho("  New links written  : $totalLinksNew\n", C_GREEN);
    if ($totalErrors > 0) {
        cecho("  Errors             : $totalErrors\n", C_RED);
    }
    cecho("─────────────────────────────────────────\n", C_GRAY);
    echo "\n";

    return [
        'sketches'  => $totalSketches,
        'tags_new'  => $totalTagsNew,
        'links_new' => $totalLinksNew,
        'errors'    => $totalErrors,
    ];
}

// ═══════════════════════════════════════════════════════
// ARGUMENT PARSING
// ═══════════════════════════════════════════════════════
$opts   = getopt('', ['from::', 'to::', 'batch::', 'dry-run', 'qjobs::']);

$fromId = isset($opts['from'])   ? (int)$opts['from']   : null;
$toId   = isset($opts['to'])     ? (int)$opts['to']     : null;
$batch  = isset($opts['batch'])  ? max(1, (int)$opts['batch']) : 5;
$dryRun = array_key_exists('dry-run', $opts);
$qjobs  = isset($opts['qjobs'])  ? (int)$opts['qjobs']  : 0;

$hasQjobsParam = array_key_exists('qjobs', $opts);
$hasFromParam  = array_key_exists('from',  $opts);
$hasToParam    = array_key_exists('to',    $opts);

// ═══════════════════════════════════════════════════════
// INIT — EntityManager, PDO, GeneratorService
// ═══════════════════════════════════════════════════════
$em  = $spw->getEntityManager();
$pdo = $spw->getPDO();
$conn = $em->getConnection();

$repo   = $em->getRepository(GeneratorConfig::class);
$config = $repo->findOneBy(['configId' => GENERATOR_CONFIG_ID]);

if (!$config) {
    cecho("Error: generator_config row '" . GENERATOR_CONFIG_ID . "' not found. Import the SQL first.\n", C_RED);
    exit(1);
}

$service = new GeneratorService(
    $spw->getAIProvider(),
    new SchemaValidator(),
    new ResponseNormalizer(),
    $spw->getFileLogger()
);

// ═══════════════════════════════════════════════════════
// BUILD JOB LIST
// ═══════════════════════════════════════════════════════
$jobsToProcess = [];

// Queue mode — only when --qjobs is explicitly provided with a positive value.
if ($hasQjobsParam && $qjobs > 0) {
    $jobsToProcess = $conn->fetchAllAssociative(
        "SELECT * FROM forge_jobs
          WHERE job_type = 'sketch_tag_extract'
            AND status   = 'pending'
          ORDER BY priority ASC, id ASC
          LIMIT " . $qjobs
    );
    if (empty($jobsToProcess)) {
        echo "No pending sketch_tag_extract jobs.\n";
        exit(0);
    }

// Direct mode — both --from and --to provided.
} elseif ($hasFromParam && $hasToParam) {
    if ($fromId === null || $toId === null || $fromId > $toId) {
        cecho("Usage: php cli_sketch_tag_extractor.php --from=ID --to=ID [--batch=5] [--dry-run]\n", C_YELLOW);
        exit(1);
    }
    $jobsToProcess[] = [
        'id'      => null,
        'payload' => json_encode([
            'from_id' => $fromId,
            'to_id'   => $toId,
            'batch'   => $batch,
            'dry_run' => $dryRun,
        ]),
    ];

// Interactive mode — no usable CLI params given.
} else {
    cecho("\n🧠 SKETCH TAG EXTRACTOR — Interactive Mode\n", C_CYAN);

    $minMax = $pdo->query("SELECT MIN(id) AS min_id, MAX(id) AS max_id FROM sketches")
                  ->fetch(PDO::FETCH_ASSOC);
    if (empty($minMax['min_id'])) {
        cecho("No sketches found in database.\n", C_YELLOW);
        exit(0);
    }
    cecho("  Sketch ID range in DB: #{$minMax['min_id']} – #{$minMax['max_id']}\n\n", C_GRAY);

    $fromId = (int)readline("Enter --from ID: ");
    $toId   = (int)readline("Enter --to ID  : ");
    if (!$fromId || !$toId || $fromId > $toId) {
        cecho("Invalid range. Aborted.\n", C_RED);
        exit(0);
    }
    $batchIn = trim(readline("Batch size [5]: "));
    $batch   = $batchIn !== '' ? max(1, (int)$batchIn) : 5;

    $jobsToProcess[] = [
        'id'      => null,
        'payload' => json_encode([
            'from_id' => $fromId,
            'to_id'   => $toId,
            'batch'   => $batch,
            'dry_run' => false,
        ]),
    ];
}

// ═══════════════════════════════════════════════════════
// JOB LOOP
// ═══════════════════════════════════════════════════════
foreach ($jobsToProcess as $jobRow) {
    $jobId = $jobRow['id'] ?? null;
    $cfg   = json_decode($jobRow['payload'] ?? '{}', true) ?: [];

    $jFromId  = (int)($cfg['from_id'] ?? 0);
    $jToId    = (int)($cfg['to_id']   ?? 0);
    $jBatch   = max(1, (int)($cfg['batch']   ?? 5));
    $jDryRun  = (bool)($cfg['dry_run'] ?? false);

    if (!$jFromId || !$jToId || $jFromId > $jToId) {
        cecho("Invalid from_id/to_id in job payload.\n", C_RED);
        if ($jobId) {
            $conn->executeStatement(
                "UPDATE forge_jobs SET status='failed', error_msg='Invalid from_id/to_id', finished_at=NOW() WHERE id=?",
                [$jobId]
            );
        }
        continue;
    }

    try {
        if ($jobId) {
            $conn->executeStatement(
                "UPDATE forge_jobs SET status='processing', started_at=NOW() WHERE id=?",
                [$jobId]
            );
        }

        $result = runExtraction($pdo, $service, $config, $jFromId, $jToId, $jBatch, $jDryRun);

        if ($jobId) {
            $conn->executeStatement(
                "UPDATE forge_jobs SET status='done', finished_at=NOW(), result=? WHERE id=?",
                [json_encode($result, JSON_UNESCAPED_UNICODE), $jobId]
            );
        }

    } catch (Throwable $ex) {
        cecho("\nERROR: " . $ex->getMessage() . "\n", C_RED);
        if ($jobId) {
            $conn->executeStatement(
                "UPDATE forge_jobs SET status='failed', error_msg=?, finished_at=NOW() WHERE id=?",
                [substr($ex->getMessage(), 0, 5000), $jobId]
            );
        }
    }
}

echo "\n--- Sketch Tag Extractor done ---\n";
