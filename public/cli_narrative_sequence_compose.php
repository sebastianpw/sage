<?php
// cli_narrative_sequence_compose.php
//
// Narrative Sequence → Episode Composer
// Inspired by cli_md_curator_extract_db.php
//
// Each sketch+analysis in the sequence = one "chunk"
// Three AI passes per sequence:
//   Pass 1 (Beat Analyst):     structural/emotional dissection of each beat
//   Pass 2 (Episode Composer): prose scene + directions, fed rolling context
//   Pass 3 (Synthesiser):      final episode document from all beat outputs
//
// Usage:
//   php cli_narrative_sequence_compose.php --seq=146
//   php cli_narrative_sequence_compose.php --seq=146 --rerun
//   php cli_narrative_sequence_compose.php --seq=146 --rerun --model=claude-sonnet-4-6
//   php cli_narrative_sequence_compose.php --qjobs=2
//
// Queue job_type: 'narrative_sequence_compose'
// Payload keys:  sequence_id, rerun (bool), overrideModel
// ============================================================================

if (php_sapi_name() !== 'cli') die("CLI only\n");

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;

// ANSI colours (reuse same constants as MD curator if already defined)
if (!defined('C_RESET')) {
    define('C_RESET',  "\033[0m");
    define('C_GREEN',  "\033[0m\033[32m");
    define('C_YELLOW', "\033[0m\033[33m");
    define('C_CYAN',   "\033[0m\033[36m");
    define('C_RED',    "\033[0m\033[31m");
    define('C_GRAY',   "\033[0m\033[90m");
    define('C_BLUE',   "\033[0m\033[34m");
}

// Generator config IDs (create these in your generator_config table)
const NSC_BEAT_ANALYST_CONFIG_ID  = 'narrative_beat_analyst_v1';
const NSC_COMPOSER_CONFIG_ID      = 'narrative_episode_composer_v1';
const NSC_SYNTHESISER_CONFIG_ID   = 'narrative_episode_synthesiser_v1';

// ============================================================================
// Helpers (subset of MD curator helpers — reuse if already loaded)
// ============================================================================

function nsc_change_key_case_recursive($arr) {
    if (!is_array($arr)) return $arr;
    $result = [];
    foreach ($arr as $key => $value) {
        $key = strtolower((string)$key);
        $result[$key] = nsc_change_key_case_recursive($value);
    }
    return $result;
}

function nsc_extract_balanced(string $text, int $startPos): ?string {
    $len = strlen($text);
    $inString = false;
    $escaped  = false;
    $depth    = 0;
    for ($i = $startPos; $i < $len; $i++) {
        $ch = $text[$i];
        if ($inString) {
            if ($escaped)       { $escaped = false; continue; }
            if ($ch === '\\')   { $escaped = true;  continue; }
            if ($ch === '"')    { $inString = false; continue; }
        } else {
            if ($ch === '"')    { $inString = true;  continue; }
            if ($ch === '{')    { $depth++; }
            elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) return substr($text, $startPos, $i - $startPos + 1);
            }
        }
    }
    return null;
}

function nsc_decode_json(string $raw): ?array {
    $raw = trim($raw);
    $d = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($d)) return nsc_change_key_case_recursive($d);

    // strip markdown fences
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $raw, $m)) {
        $d = json_decode($m[1], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($d)) return nsc_change_key_case_recursive($d);
    }

    // balanced brace extraction
    $pos = strpos($raw, '{');
    if ($pos !== false) {
        $sub = nsc_extract_balanced($raw, $pos);
        if ($sub) {
            // trailing comma cleanup
            $sub = preg_replace('/,\s*(\]|\})/m', '$1', $sub);
            $d = json_decode($sub, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($d)) return nsc_change_key_case_recursive($d);
        }
    }
    return null;
}

function nsc_str(array $data, string $key, string $default = ''): string {
    $v = $data[$key] ?? $default;
    return is_string($v) ? $v : (is_array($v) ? implode('; ', $v) : (string)$v);
}

function nsc_normalize_beat_entry($beatEntry): array {
    // 1. Single integer: Always Entity (Sketch) ID
    if (is_int($beatEntry) || (is_string($beatEntry) && ctype_digit($beatEntry))) {
        return [
            'sketch_id' => (int)$beatEntry,
            'frame_id'  => 0,
        ];
    }

    if (!is_array($beatEntry)) {
        return ['sketch_id' => 0, 'frame_id' => 0];
    }

    // 2. Sequential Array [entity_id, frame_id]
    if (isset($beatEntry[0]) && is_numeric($beatEntry[0])) {
        return [
            'sketch_id' => (int)$beatEntry[0],
            'frame_id'  => isset($beatEntry[1]) ? (int)$beatEntry[1] : 0,
        ];
    }

    // 3. Object / Associative Array {"id": 123, "frame_id": 456}
    return [
        'sketch_id' => (int)(
            $beatEntry['sketch_id']
            ?? $beatEntry['sketchId']
            ?? $beatEntry['sketch']
            ?? $beatEntry['entity_id']
            ?? $beatEntry['id']
            ?? 0
        ),
        'frame_id' => (int)(
            $beatEntry['frame_id']
            ?? $beatEntry['frameId']
            ?? 0
        ),
    ];
}

// ============================================================================
// Build the input payload for Pass 1 (Beat Analyst)
// ============================================================================

function build_beat_analyst_input(array $sketch, ?array $analysis, ?array $seq_analysis): string {
    $parts = [];
    $parts[] = "=== SKETCH ===";
    $parts[] = "ID: {$sketch['id']}";
    $parts[] = "Name: " . ($sketch['name'] ?? 'Unnamed');
    $parts[] = "Description:\n" . ($sketch['description'] ?? '');

    if (!empty($analysis)) {
        $parts[] = "\n=== EXISTING ANALYSIS ===";
        if (!empty($analysis['entities']))       $parts[] = "Entities: "       . json_encode($analysis['entities'],       JSON_UNESCAPED_UNICODE);
        if (!empty($analysis['classification'])) $parts[] = "Classification: " . json_encode($analysis['classification'], JSON_UNESCAPED_UNICODE);
        if (!empty($analysis['thematics']))      $parts[] = "Thematics: "      . json_encode($analysis['thematics'],      JSON_UNESCAPED_UNICODE);
        if (!empty($analysis['scoring']))        $parts[] = "Scoring: "        . json_encode($analysis['scoring'],        JSON_UNESCAPED_UNICODE);
    }

    if (!empty($seq_analysis)) {
        $parts[] = "\n=== SEQUENCE ANALYSIS ===";
        if (!empty($seq_analysis['short_logline']))   $parts[] = "Logline: "        . $seq_analysis['short_logline'];
        if (!empty($seq_analysis['connective_hint'])) $parts[] = "Connective hint: ". $seq_analysis['connective_hint'];
        if (!empty($seq_analysis['energy']))          $parts[] = "Energy: "         . $seq_analysis['energy'];
        if (!empty($seq_analysis['intensity']))       $parts[] = "Intensity: "      . $seq_analysis['intensity'];
    }

    return implode("\n", $parts);
}

// ============================================================================
// Build the input payload for Pass 2 (Episode Composer)
// ============================================================================

function build_composer_input(
    array  $sketch,
    ?array $beat_data,
    array  $rolling_context,
    array  $sequence_meta
): string {
    $parts = [];

    $parts[] = "=== SEQUENCE CONTEXT ===";
    $parts[] = "Sequence Name: "        . ($sequence_meta['name'] ?? '');
    $parts[] = "Sequence Description: " . ($sequence_meta['description'] ?? '');
    $parts[] = "Total Beats: "          . ($sequence_meta['total_beats'] ?? '?');
    $parts[] = "Current Beat: "         . ($sequence_meta['current_beat'] ?? '?') . " (0-indexed)";

    if (!empty($rolling_context['acts_so_far'])) {
        $parts[] = "\n=== ACTS WRITTEN SO FAR ===";
        $parts[] = implode("\n\n", $rolling_context['acts_so_far']);
    }

    if (!empty($rolling_context['open_tensions'])) {
        $parts[] = "\n=== OPEN TENSIONS (unresolved threads) ===";
        $parts[] = implode("\n", array_map(fn($t) => "• $t", $rolling_context['open_tensions']));
    }

    if (!empty($rolling_context['established_motifs'])) {
        $parts[] = "\n=== ESTABLISHED MOTIFS (must echo if relevant) ===";
        $parts[] = implode("\n", array_map(fn($m) => "• $m", $rolling_context['established_motifs']));
    }

    if (!empty($rolling_context['emotional_temperature'])) {
        $parts[] = "\n=== CURRENT EMOTIONAL TEMPERATURE ===";
        $parts[] = $rolling_context['emotional_temperature'];
    }

    if (!empty($rolling_context['last_beat_summary'])) {
        $parts[] = "\n=== LAST BEAT SUMMARY ===";
        $parts[] = $rolling_context['last_beat_summary'];
    }

    $parts[] = "\n=== CURRENT BEAT TO COMPOSE ===";
    $parts[] = "Sketch ID: {$sketch['id']}";
    $parts[] = "Sketch Name: " . ($sketch['name'] ?? 'Unnamed');
    $parts[] = "Description:\n" . ($sketch['description'] ?? '');

    if (!empty($beat_data)) {
        $parts[] = "\n=== BEAT ANALYSIS (from Pass 1) ===";
        foreach (['emotional_register','tension_type','narrative_function','visual_anchors','character_states','beat_purpose'] as $k) {
            if (!empty($beat_data[$k])) {
                $val = $beat_data[$k];
                if (is_array($val)) {
                    $arr = [];
                    foreach ($val as $v) {
                        // Transforms object array to text block for the prompt
                        $arr[] = is_array($v) ? implode(': ', $v) : (string)$v;
                    }
                    $valStr = implode('; ', $arr);
                } else {
                    $valStr = (string)$val;
                }
                $parts[] = ucfirst(str_replace('_',' ',$k)) . ": " . $valStr;
            }
        }
    }

    return implode("\n", $parts);
}

// ============================================================================
// Build the input for Pass 3 (Synthesiser)
// ============================================================================

function build_synthesiser_input(
    array  $sequence_meta,
    array  $rolling_context,
    array  $all_scene_titles,
    array  $all_act_labels
): string {
    $parts = [];

    $parts[] = "=== SEQUENCE METADATA ===";
    $parts[] = "Name: "        . ($sequence_meta['name'] ?? '');
    $parts[] = "Description: " . ($sequence_meta['description'] ?? '');
    $parts[] = "Total Beats: " . count($all_scene_titles);

    $parts[] = "\n=== BEATS IN ORDER ===";
    foreach ($all_scene_titles as $i => $title) {
        $act = $all_act_labels[$i] ?? '';
        $parts[] = "  Beat $i" . ($act ? " [$act]" : "") . ": $title";
    }

    if (!empty($rolling_context['established_motifs'])) {
        $parts[] = "\n=== ESTABLISHED MOTIFS ===";
        $parts[] = implode("\n", array_map(fn($m) => "• $m", $rolling_context['established_motifs']));
    }

    if (!empty($rolling_context['open_tensions'])) {
        $parts[] = "\n=== OPEN TENSIONS AT EPISODE END ===";
        $parts[] = implode("\n", array_map(fn($t) => "• $t", $rolling_context['open_tensions']));
    }

    if (!empty($rolling_context['emotional_temperature'])) {
        $parts[] = "\n=== FINAL EMOTIONAL TEMPERATURE ===";
        $parts[] = $rolling_context['emotional_temperature'];
    }

    $parts[] = "\n=== ALL ACT SUMMARIES ===";
    foreach ($rolling_context['acts_so_far'] ?? [] as $i => $actSummary) {
        $parts[] = "--- Act Summary $i ---\n" . $actSummary;
    }

    return implode("\n", $parts);
}

// ============================================================================
// Fetch helpers
// ============================================================================

function fetch_sequence(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM narrative_sequences WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $row['sequence_data_decoded'] = json_decode($row['sequence_data'] ?? '[]', true) ?: [];
    return $row;
}

function fetch_sketch(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM sketches WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function fetch_sketch_analysis(PDO $pdo, int $sketch_id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM sketch_analysis WHERE sketch_id = ?");
    $stmt->execute([$sketch_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    foreach (['entities','classification','scoring','thematics','recommendations'] as $f) {
        if (!empty($row[$f])) $row[$f] = json_decode($row[$f], true);
    }
    return $row;
}

function fetch_sketch_seq_analysis(PDO $pdo, int $sketch_id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM sketch_sequence_analysis WHERE sketch_id = ?");
    $stmt->execute([$sketch_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    foreach (['narrative_function','layer','tags'] as $f) {
        if (!empty($row[$f])) $row[$f] = json_decode($row[$f], true);
    }
    return $row;
}

function fetch_cached_beat(PDO $pdo, int $sequence_id, int $position): ?array {
    $stmt = $pdo->prepare("SELECT * FROM narrative_beat_analysis WHERE sequence_id = ? AND position = ?");
    $stmt->execute([$sequence_id, $position]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function upsert_beat(PDO $pdo, int $sequence_id, int $position, array $fields): void {
    $sql = "INSERT INTO narrative_beat_analysis 
                (sequence_id, position, sketch_id, frame_id, beat_raw, compose_raw,
                 scene_title, act_label, emotional_register, rolling_context)
            VALUES (:seq_id, :pos, :sketch_id, :frame_id, :beat_raw, :compose_raw,
                    :scene_title, :act_label, :emotional_register, :rolling_ctx)
            ON DUPLICATE KEY UPDATE
                beat_raw           = VALUES(beat_raw),
                compose_raw        = VALUES(compose_raw),
                scene_title        = VALUES(scene_title),
                act_label          = VALUES(act_label),
                emotional_register = VALUES(emotional_register),
                rolling_context    = VALUES(rolling_context),
                updated_at         = NOW()";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':seq_id'            => $sequence_id,
        ':pos'               => $position,
        ':sketch_id'         => $fields['sketch_id']         ?? 0,
        ':frame_id'          => $fields['frame_id']          ?? null,
        ':beat_raw'          => $fields['beat_raw']          ?? null,
        ':compose_raw'       => $fields['compose_raw']       ?? null,
        ':scene_title'       => $fields['scene_title']       ?? null,
        ':act_label'         => $fields['act_label']         ?? null,
        ':emotional_register'=> $fields['emotional_register']?? null,
        ':rolling_ctx'       => isset($fields['rolling_context'])
                                    ? json_encode($fields['rolling_context'], JSON_UNESCAPED_UNICODE)
                                    : null,
    ]);
}

function upsert_sequence_analysis(PDO $pdo, int $sequence_id, array $fields): void {
    $sql = "INSERT INTO narrative_sequence_analysis
                (sequence_id, episode_title, episode_subtitle, logline,
                 act_structure, production_notes, recurring_motifs,
                 episode_thesis, open_tensions, synthesiser_raw,
                 generator_config_id, beat_count, model_used)
            VALUES
                (:seq_id, :ep_title, :ep_subtitle, :logline,
                 :act_structure, :prod_notes, :motifs,
                 :thesis, :tensions, :synth_raw,
                 :cfg_id, :beat_count, :model)
            ON DUPLICATE KEY UPDATE
                episode_title        = VALUES(episode_title),
                episode_subtitle     = VALUES(episode_subtitle),
                logline              = VALUES(logline),
                act_structure        = VALUES(act_structure),
                production_notes     = VALUES(production_notes),
                recurring_motifs     = VALUES(recurring_motifs),
                episode_thesis       = VALUES(episode_thesis),
                open_tensions        = VALUES(open_tensions),
                synthesiser_raw      = VALUES(synthesiser_raw),
                generator_config_id  = VALUES(generator_config_id),
                beat_count           = VALUES(beat_count),
                model_used           = VALUES(model_used),
                updated_at           = NOW()";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':seq_id'      => $sequence_id,
        ':ep_title'    => $fields['episode_title']    ?? null,
        ':ep_subtitle' => $fields['episode_subtitle'] ?? null,
        ':logline'     => $fields['logline']          ?? null,
        ':act_structure'=> isset($fields['act_structure'])
                            ? json_encode($fields['act_structure'], JSON_UNESCAPED_UNICODE)
                            : null,
        ':prod_notes'  => $fields['production_notes'] ?? null,
        ':motifs'      => isset($fields['recurring_motifs'])
                            ? json_encode($fields['recurring_motifs'], JSON_UNESCAPED_UNICODE)
                            : null,
        ':thesis'      => $fields['episode_thesis']   ?? null,
        ':tensions'    => isset($fields['open_tensions'])
                            ? json_encode($fields['open_tensions'], JSON_UNESCAPED_UNICODE)
                            : null,
        ':synth_raw'   => $fields['synthesiser_raw']  ?? null,
        ':cfg_id'      => $fields['generator_config_id'] ?? null,
        ':beat_count'  => $fields['beat_count']       ?? 0,
        ':model'       => $fields['model_used']       ?? null,
    ]);
}

// ============================================================================
// AI call wrapper (with retry)
// ============================================================================

function ai_call(
    GeneratorService $service,
    GeneratorConfig  $config,
    string           $input,
    string           $label
): ?string {
    $retries = 0;
    while ($retries < 3) {
        try {
            if ($retries > 0) sleep(3);
            $res = $service->generate($config, ['entity_name' => $input]);
            $raw = is_object($res) && method_exists($res, 'getRawResponse')
                ? $res->getRawResponse()
                : (string)$res;
            echo C_GREEN . $label . C_RESET;
            return $raw;
        } catch (Throwable $e) {
            $retries++;
            echo C_RED . "x({$e->getMessage()})" . C_RESET;
        }
    }
    return null;
}

// ============================================================================
// MAIN
// ============================================================================

$opts = getopt('', ['seq::', 'rerun', 'model::', 'qjobs::']);
$qjobs         = isset($opts['qjobs']) ? (int)$opts['qjobs'] : 0;
$rerun         = isset($opts['rerun']);
$overrideModel = isset($opts['model']) ? trim($opts['model']) : null;
$seqIdCli      = isset($opts['seq']) ? (int)$opts['seq'] : null;

$hasQjobsParam = array_key_exists('qjobs', $opts);
$hasSeqParam   = array_key_exists('seq', $opts);

$em    = $spw->getEntityManager();
$pdo   = $spw->getPDO();
$conn  = $em->getConnection();

// Build job list
$jobsToProcess = [];

// Queue mode only when --qjobs is explicitly provided with a positive value.
if ($hasQjobsParam && $qjobs > 0) {
    $jobsToProcess = $conn->fetchAllAssociative(
        "SELECT * FROM forge_jobs
          WHERE job_type = 'narrative_sequence_compose'
            AND status   = 'pending'
          ORDER BY priority ASC, id ASC
          LIMIT " . $qjobs
    );
    if (empty($jobsToProcess)) {
        echo "No pending narrative_sequence_compose jobs.\n";
        exit(0);
    }

// Direct single-sequence mode when --seq is provided.
} elseif ($hasSeqParam && $seqIdCli > 0) {
    $jobsToProcess[] = [
        'id'      => null,
        'payload' => json_encode([
            'sequence_id'   => $seqIdCli,
            'rerun'         => $rerun,
            'overrideModel' => $overrideModel,
        ]),
    ];

// Interactive mode when no usable CLI params were given.
} else {
    $seqs = $pdo->query("SELECT id, name, created_at FROM narrative_sequences ORDER BY id DESC LIMIT 50")
                ->fetchAll(PDO::FETCH_ASSOC);
    if (empty($seqs)) {
        echo "No narrative sequences found.\n";
        exit(0);
    }
    echo C_CYAN . "\n📽 NARRATIVE SEQUENCE COMPOSER\n" . C_RESET;
    foreach ($seqs as $i => $s) {
        echo "  [{$s['id']}] {$s['name']}\n";
    }
    $input = (int)readline("Enter Sequence ID: ");
    if (!$input) { echo "Aborted.\n"; exit(0); }
    $jobsToProcess[] = [
        'id'      => null,
        'payload' => json_encode(['sequence_id' => $input, 'rerun' => false]),
    ];
}

// ============================================================================
// Job loop
// ============================================================================

foreach ($jobsToProcess as $jobRow) {
    $jobId = $jobRow['id'] ?? null;
    $cfg   = json_decode($jobRow['payload'] ?? '{}', true) ?: [];

    $sequenceId    = (int)($cfg['sequence_id'] ?? 0);
    $doRerun       = (bool)($cfg['rerun']       ?? false) || $rerun;
    $modelOverride = !empty($cfg['overrideModel']) ? $cfg['overrideModel'] : $overrideModel;

    if (!$sequenceId) {
        echo C_RED . "Invalid sequence_id in job payload.\n" . C_RESET;
        if ($jobId) $conn->executeStatement("UPDATE forge_jobs SET status='failed', error_msg='Invalid sequence_id', finished_at=NOW() WHERE id=?", [$jobId]);
        continue;
    }

    try {
        if ($jobId) {
            $conn->executeStatement("UPDATE forge_jobs SET status='processing', started_at=NOW() WHERE id=?", [$jobId]);
        }

        echo C_CYAN . "\n=== NARRATIVE SEQUENCE COMPOSER: seq #$sequenceId" . ($doRerun ? " [RERUN]" : "") . " ===\n" . C_RESET;

        // ------------------------------------------------------------------
        // 1. Load generator configs
        // ------------------------------------------------------------------
        $repo       = $em->getRepository(GeneratorConfig::class);
        $cfgBeat    = $repo->findOneBy(['configId' => NSC_BEAT_ANALYST_CONFIG_ID]);
        $cfgCompose = $repo->findOneBy(['configId' => NSC_COMPOSER_CONFIG_ID]);
        $cfgSynth   = $repo->findOneBy(['configId' => NSC_SYNTHESISER_CONFIG_ID]);

        foreach ([
            NSC_BEAT_ANALYST_CONFIG_ID  => $cfgBeat,
            NSC_COMPOSER_CONFIG_ID      => $cfgCompose,
            NSC_SYNTHESISER_CONFIG_ID   => $cfgSynth,
        ] as $cid => $c) {
            if (!$c) throw new RuntimeException("Missing generator config: $cid — create it first.");
        }

        if ($modelOverride) {
            foreach ([$cfgBeat, $cfgCompose, $cfgSynth] as $c) {
                if (method_exists($c, 'setModel')) $c->setModel($modelOverride);
            }
        }

        $aiProvider = $spw->getAIProvider();
        $service    = new GeneratorService(
            $aiProvider,
            new SchemaValidator(),
            new ResponseNormalizer(),
            $spw->getFileLogger()
        );

        // ------------------------------------------------------------------
        // 2. Load sequence
        // ------------------------------------------------------------------
        $sequence = fetch_sequence($pdo, $sequenceId);
        if (!$sequence) throw new RuntimeException("Sequence #$sequenceId not found.");

        $beats      = $sequence['sequence_data_decoded'];
        $totalBeats = count($beats);

        echo "  Name:   " . $sequence['name'] . "\n";
        echo "  Beats:  $totalBeats\n\n";

        if ($totalBeats === 0) throw new RuntimeException("Sequence has no beats.");

        $seqMeta = [
            'name'         => $sequence['name'],
            'description'  => $sequence['description'] ?? '',
            'total_beats'  => $totalBeats,
        ];

        // ------------------------------------------------------------------
        // 3. Beat loop
        // ------------------------------------------------------------------

        // Rolling context (the "previously on" state object)
        $rollingCtx = [
            'acts_so_far'           => [],
            'open_tensions'         => [],
            'established_motifs'    => [],
            'emotional_temperature' => 'undefined — episode not yet begun',
            'last_beat_summary'     => '',
        ];

        $allSceneTitles = [];
        $allActLabels   = [];
        $usedModel      = $modelOverride ?? '';

        foreach ($beats as $position => $beatEntry) {
            $normalizedBeat = nsc_normalize_beat_entry($beatEntry);
            $sketchId = (int)($normalizedBeat['sketch_id'] ?? 0);
            $frameId  = (int)($normalizedBeat['frame_id']  ?? 0);

            $labelFrame = $frameId ? ", frame #$frameId" : "";
            echo "  Beat $position/$totalBeats (sketch #$sketchId$labelFrame): ";

            $sketch = fetch_sketch($pdo, $sketchId);

            if (!$sketch) {
                echo C_YELLOW . "SKIP (sketch not found)\n" . C_RESET;
                continue;
            }

            // Check cache
            $cached = $doRerun ? null : fetch_cached_beat($pdo, $sequenceId, $position);

            $beatRaw    = $cached['beat_raw']    ?? null;
            $composeRaw = $cached['compose_raw'] ?? null;
            
            // Retain metadata safely if cached
            $sceneTitle   = $cached['scene_title'] ?? ($sketch['name'] ?? "Beat $position");
            $actLabel     = $cached['act_label'] ?? '';
            $emotionalReg = $cached['emotional_register'] ?? '';

            // Fetch analysis data
            $analysis  = fetch_sketch_analysis($pdo, $sketchId);
            $seqAnalys = fetch_sketch_seq_analysis($pdo, $sketchId);

            $seqMeta['current_beat'] = $position;

            // -------------------
            // Pass 1: Beat Analyst
            // -------------------
            $beatData = null;
            if (!empty($beatRaw)) {
                echo C_GRAY . "b" . C_RESET; // cached
                $beatData = nsc_decode_json($beatRaw);
            } else {
                $input1   = build_beat_analyst_input($sketch, $analysis, $seqAnalys);
                $beatRaw  = ai_call($service, $cfgBeat, $input1, 'B');
                if ($beatRaw) {
                    $beatData  = nsc_decode_json($beatRaw);
                    if (empty($usedModel) && method_exists($cfgBeat, 'getModel')) {
                        $usedModel = $cfgBeat->getModel();
                    }
                    // SAVE IMMEDIATELY AFTER PASS 1
                    upsert_beat($pdo, $sequenceId, $position, [
                        'sketch_id'          => $sketchId,
                        'frame_id'           => $frameId ?: null,
                        'beat_raw'           => $beatRaw,
                        'compose_raw'        => $composeRaw,
                        'scene_title'        => $sceneTitle,
                        'act_label'          => $actLabel,
                        'emotional_register' => $emotionalReg,
                        'rolling_context'    => $rollingCtx, 
                    ]);
                }
            }

            // -------------------
            // Pass 2: Episode Composer
            // -------------------
            $composedSummary = '';
            $cGenerated = false;

            if (!empty($composeRaw)) {
                echo C_GRAY . "c" . C_RESET; // cached
                $composeData = nsc_decode_json($composeRaw);
            } else {
                $input2     = build_composer_input($sketch, $beatData, $rollingCtx, $seqMeta);
                $composeRaw = ai_call($service, $cfgCompose, $input2, 'C');
                if ($composeRaw) {
                    $cGenerated  = true;
                    $composeData = nsc_decode_json($composeRaw);
                }
            }

            // Extract fields from compose output
            if (!empty($composeData) && is_array($composeData)) {
                $sceneTitle       = nsc_str($composeData, 'scene_title',        $sceneTitle);
                $actLabel         = nsc_str($composeData, 'act_label',          $actLabel);
                $emotionalReg     = nsc_str($composeData, 'emotional_register', $emotionalReg);
                $composedSummary  = nsc_str($composeData, 'beat_summary',       '');

                // Update rolling context
                if (!empty($composedSummary)) {
                    $rollingCtx['last_beat_summary'] = $composedSummary;
                    if (!empty($actLabel)) {
                        $rollingCtx['acts_so_far'][] = "[$actLabel] $sceneTitle: $composedSummary";
                    }
                }
                if (!empty($emotionalReg)) {
                    $rollingCtx['emotional_temperature'] = $emotionalReg;
                }
                // Absorb new tensions and motifs emitted by the composer
                foreach (($composeData['new_tensions'] ?? []) as $t) {
                    if ($t && !in_array($t, $rollingCtx['open_tensions'])) {
                        $rollingCtx['open_tensions'][] = $t;
                    }
                }
                foreach (($composeData['new_motifs'] ?? []) as $m) {
                    if ($m && !in_array($m, $rollingCtx['established_motifs'])) {
                        $rollingCtx['established_motifs'][] = $m;
                    }
                }
                foreach (($composeData['resolved_tensions'] ?? []) as $r) {
                    $rollingCtx['open_tensions'] = array_values(
                        array_filter($rollingCtx['open_tensions'], fn($t) => $t !== $r)
                    );
                }
            }

            $allSceneTitles[] = $sceneTitle;
            $allActLabels[]   = $actLabel;

            // SAVE IMMEDIATELY AFTER PASS 2 (if it was newly generated)
            if ($cGenerated) {
                upsert_beat($pdo, $sequenceId, $position, [
                    'sketch_id'          => $sketchId,
                    'frame_id'           => $frameId ?: null,
                    'beat_raw'           => $beatRaw,
                    'compose_raw'        => $composeRaw,
                    'scene_title'        => $sceneTitle,
                    'act_label'          => $actLabel,
                    'emotional_register' => $emotionalReg,
                    'rolling_context'    => $rollingCtx,
                ]);
            }

            echo "\n";
        }

        // ------------------------------------------------------------------
        // 4. Pass 3: Synthesiser (final episode document)
        // ------------------------------------------------------------------
        echo "\n  " . C_CYAN . "Pass 3: Episode Synthesiser..." . C_RESET . "\n";

        $synthInput = build_synthesiser_input($seqMeta, $rollingCtx, $allSceneTitles, $allActLabels);
        $synthRaw   = ai_call($service, $cfgSynth, $synthInput, 'S');

        $synthData  = $synthRaw ? nsc_decode_json($synthRaw) : [];

        upsert_sequence_analysis($pdo, $sequenceId, [
            'episode_title'       => nsc_str($synthData ?? [], 'episode_title',    $sequence['name']),
            'episode_subtitle'    => nsc_str($synthData ?? [], 'episode_subtitle', ''),
            'logline'             => nsc_str($synthData ?? [], 'logline',          ''),
            'act_structure'       => $synthData['act_structure']    ?? $allSceneTitles,
            'production_notes'    => nsc_str($synthData ?? [], 'production_notes', ''),
            'recurring_motifs'    => $synthData['recurring_motifs'] ?? $rollingCtx['established_motifs'],
            'episode_thesis'      => nsc_str($synthData ?? [], 'episode_thesis',   ''),
            'open_tensions'       => $synthData['open_tensions']    ?? $rollingCtx['open_tensions'],
            'synthesiser_raw'     => $synthRaw,
            'generator_config_id' => $cfgSynth ? $cfgSynth->getId() : null,
            'beat_count'          => $totalBeats,
            'model_used'          => $usedModel,
        ]);

        echo C_GREEN . "\n✓ Sequence #$sequenceId complete — {$totalBeats} beats processed.\n" . C_RESET;

        if ($jobId) {
            $conn->executeStatement(
                "UPDATE forge_jobs SET status='done', finished_at=NOW() WHERE id=?",
                [$jobId]
            );
        }

    } catch (Throwable $ex) {
        echo C_RED . "\nERROR: " . $ex->getMessage() . "\n" . C_RESET;
        if ($jobId) {
            $conn->executeStatement(
                "UPDATE forge_jobs SET status='failed', error_msg=?, finished_at=NOW() WHERE id=?",
                [substr($ex->getMessage(), 0, 5000), $jobId]
            );
        }
    }
}

echo "\n--- Narrative Sequence Composer done ---\n";