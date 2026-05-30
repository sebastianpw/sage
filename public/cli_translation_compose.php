<?php
// cli_translation_compose.php
//
// Translation Composer — Sketch & Sequence Overlay Texts
//
// Translates sketch_overlay_texts and sequence_overlay_texts for sketches
// that belong to narrative_sequences which are linked to at least one cinemagic.
// Only cinemagic-linked sequences are eligible (published/chosen for distribution).
//
// ── Usage ───────────────────────────────────────────────────────────────────
//
//   Interactive (no args):
//     php cli_translation_compose.php
//
//   Direct — single sequence:
//     php cli_translation_compose.php --seq=96 --lang=pt
//     php cli_translation_compose.php --seq=96 --lang=pt --rerun
//
//   Direct — all sequences in a cinemagic:
//     php cli_translation_compose.php --cinemagic=1 --lang=fr
//     php cli_translation_compose.php --cinemagic=1 --lang=fr --rerun
//
//   JSON payload mode (from cli_forge UI queue button):
//     php cli_translation_compose.php --json='{"sequence_id":96,"lang":"pt","rerun":false}'
//     php cli_translation_compose.php --json='{"cinemagic_id":1,"lang":"fr","rerun":false}'
//
//   Queue mode (process pending forge_jobs):
//     php cli_translation_compose.php --qjobs=5
//
// ── Queue job_type ───────────────────────────────────────────────────────────
//   translation_compose
//
// ── Payload keys ────────────────────────────────────────────────────────────
//   sequence_id  (int)      — translate a specific sequence
//   cinemagic_id (int)      — translate all sequences in a cinemagic
//   lang         (string)   — 2-char language code, must exist in system_languages
//   rerun        (bool)     — force re-translate even if translations exist
//
// ── Required generator_config ───────────────────────────────────────────────
//   config_id: overlay_text_translator_v1
//   (SQL to create it is at the bottom of this file)
// ============================================================================

if (php_sapi_name() !== 'cli') die("CLI only\n");

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;

// ── ANSI colours ─────────────────────────────────────────────────────────────
if (!defined('C_RESET')) {
    define('C_RESET',  "\033[0m");
    define('C_GREEN',  "\033[0m\033[32m");
    define('C_YELLOW', "\033[0m\033[33m");
    define('C_CYAN',   "\033[0m\033[36m");
    define('C_RED',    "\033[0m\033[31m");
    define('C_GRAY',   "\033[0m\033[90m");
    define('C_BLUE',   "\033[0m\033[34m");
}

// Generator config ID — install via SQL at bottom of file before first run.
const TC_TRANSLATOR_CONFIG_ID = 'overlay_text_translator_v1';

// ============================================================================
// JSON helpers (self-contained, mirrors NSC pattern)
// ============================================================================

function tc_change_key_case_recursive(mixed $arr): mixed {
    if (!is_array($arr)) return $arr;
    $result = [];
    foreach ($arr as $key => $value) {
        $result[strtolower((string)$key)] = tc_change_key_case_recursive($value);
    }
    return $result;
}

function tc_extract_balanced(string $text, int $startPos): ?string {
    $len      = strlen($text);
    $inString = false;
    $escaped  = false;
    $depth    = 0;
    for ($i = $startPos; $i < $len; $i++) {
        $ch = $text[$i];
        if ($inString) {
            if ($escaped)     { $escaped = false; continue; }
            if ($ch === '\\') { $escaped = true;  continue; }
            if ($ch === '"')  { $inString = false; continue; }
        } else {
            if ($ch === '"')  { $inString = true; continue; }
            if ($ch === '{')  { $depth++; }
            elseif ($ch === '}') {
                $depth--;
                if ($depth === 0) return substr($text, $startPos, $i - $startPos + 1);
            }
        }
    }
    return null;
}

function tc_decode_json(string $raw): ?array {
    $raw = trim($raw);
    $d = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($d)) {
        return tc_change_key_case_recursive($d);
    }
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $raw, $m)) {
        $d = json_decode($m[1], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($d)) {
            return tc_change_key_case_recursive($d);
        }
    }
    $pos = strpos($raw, '{');
    if ($pos !== false) {
        $sub = tc_extract_balanced($raw, $pos);
        if ($sub) {
            $sub = preg_replace('/,\s*(\]|\})/m', '$1', $sub);
            $d   = json_decode($sub, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($d)) {
                return tc_change_key_case_recursive($d);
            }
        }
    }
    return null;
}

// ============================================================================
// Fetch helpers
// ============================================================================

/**
 * All cinemagics, ordered by id.
 */
function tc_fetch_all_cinemagics(PDO $pdo): array {
    return $pdo->query(
        "SELECT id, name, description FROM cinemagics ORDER BY id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * All narrative_sequences linked to a given cinemagic.
 */
function tc_fetch_sequences_in_cinemagic(PDO $pdo, int $cinemagicId): array {
    $stmt = $pdo->prepare(
        "SELECT ns.id, ns.name, ns.description, c2s.sort_order, c2s.chapter_label
           FROM narrative_sequences ns
           JOIN cinemagics_2_sequences c2s ON c2s.sequence_id = ns.id
          WHERE c2s.cinemagic_id = :cid
          ORDER BY c2s.sort_order ASC, ns.id ASC"
    );
    $stmt->bindValue(':cid', $cinemagicId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Verify a sequence is linked to at least one cinemagic.
 */
function tc_sequence_is_in_cinemagic(PDO $pdo, int $sequenceId): bool {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM cinemagics_2_sequences WHERE sequence_id = ? LIMIT 1"
    );
    $stmt->execute([$sequenceId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Sequence row by id, with decoded sequence_data.
 */
function tc_fetch_sequence(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM narrative_sequences WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $row['sequence_data_decoded'] = json_decode($row['sequence_data'] ?? '[]', true) ?: [];
    return $row;
}

/**
 * All active non-main languages from system_languages.
 */
function tc_fetch_target_languages(PDO $pdo): array {
    return $pdo->query(
        "SELECT code, name FROM system_languages WHERE is_main = 0 ORDER BY code ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Single language row by code.
 */
function tc_fetch_language(PDO $pdo, string $code): ?array {
    $stmt = $pdo->prepare("SELECT code, name FROM system_languages WHERE code = ?");
    $stmt->execute([$code]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * All unique sketch IDs referenced in a sequence's sequence_data JSON.
 */
function tc_sketch_ids_from_sequence(array $sequenceDataDecoded): array {
    $ids = [];
    foreach ($sequenceDataDecoded as $beatEntry) {
        $sketchId = 0;
        if (is_int($beatEntry) || (is_string($beatEntry) && ctype_digit((string)$beatEntry))) {
            $sketchId = (int)$beatEntry;
        } elseif (is_array($beatEntry)) {
            if (isset($beatEntry[0]) && is_numeric($beatEntry[0])) {
                $sketchId = (int)$beatEntry[0];
            } else {
                $sketchId = (int)(
                    $beatEntry['sketch_id'] ?? $beatEntry['sketchId']
                    ?? $beatEntry['entity_id'] ?? $beatEntry['id'] ?? 0
                );
            }
        }
        if ($sketchId > 0) $ids[$sketchId] = $sketchId;
    }
    return array_values($ids);
}

/**
 * Fetch all sketch_overlay_texts rows where language_code = 'en' for a given sketch.
 */
function tc_fetch_sketch_source_texts(PDO $pdo, int $sketchId): array {
    $stmt = $pdo->prepare(
        "SELECT id, text_content, display_order
           FROM sketch_overlay_texts
          WHERE sketch_id = :sid AND language_code = 'en'
          ORDER BY display_order ASC"
    );
    $stmt->bindValue(':sid', $sketchId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Return display_order values already translated for a sketch in a given lang.
 */
function tc_fetch_translated_sketch_orders(PDO $pdo, int $sketchId, string $lang): array {
    $stmt = $pdo->prepare(
        "SELECT display_order
           FROM sketch_overlay_texts
          WHERE sketch_id = :sid AND language_code = :lang"
    );
    $stmt->bindValue(':sid', $sketchId, PDO::PARAM_INT);
    $stmt->bindValue(':lang', $lang);
    $stmt->execute();
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'display_order');
}

/**
 * Upsert a translated sketch overlay text row.
 * Logical key: sketch_id + language_code + display_order
 */
function tc_upsert_sketch_overlay(PDO $pdo, int $sketchId, string $lang, int $displayOrder, string $text): void {
    $check = $pdo->prepare(
        "SELECT id FROM sketch_overlay_texts
          WHERE sketch_id = :sid AND language_code = :lang AND display_order = :ord
          LIMIT 1"
    );
    $check->bindValue(':sid', $sketchId, PDO::PARAM_INT);
    $check->bindValue(':lang', $lang);
    $check->bindValue(':ord', $displayOrder, PDO::PARAM_INT);
    $check->execute();
    $existing = $check->fetchColumn();

    if ($existing) {
        $stmt = $pdo->prepare(
            "UPDATE sketch_overlay_texts SET text_content = :txt WHERE id = :id"
        );
        $stmt->bindValue(':txt', $text);
        $stmt->bindValue(':id', (int)$existing, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO sketch_overlay_texts
                 (sketch_id, text_content, display_order, language_code)
             VALUES (:sid, :txt, :ord, :lang)"
        );
        $stmt->bindValue(':sid', $sketchId, PDO::PARAM_INT);
        $stmt->bindValue(':txt', $text);
        $stmt->bindValue(':ord', $displayOrder, PDO::PARAM_INT);
        $stmt->bindValue(':lang', $lang);
        $stmt->execute();
    }
}

/**
 * Fetch sequence_overlay_texts English source row for a sequence.
 * Returns null if no English row exists (falls back to sequence.name/description).
 */
function tc_fetch_sequence_source_overlay(PDO $pdo, int $sequenceId): ?array {
    $stmt = $pdo->prepare(
        "SELECT name_overlay, description_overlay
           FROM sequence_overlay_texts
          WHERE sequence_id = :sid AND language_code = 'en'
          LIMIT 1"
    );
    $stmt->bindValue(':sid', $sequenceId, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Check if a sequence_overlay_texts translation row already exists.
 */
function tc_sequence_overlay_translated(PDO $pdo, int $sequenceId, string $lang): bool {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM sequence_overlay_texts
          WHERE sequence_id = :sid AND language_code = :lang LIMIT 1"
    );
    $stmt->bindValue(':sid', $sequenceId, PDO::PARAM_INT);
    $stmt->bindValue(':lang', $lang);
    $stmt->execute();
    return (bool)$stmt->fetchColumn();
}

/**
 * Upsert sequence_overlay_texts (unique key: sequence_id + language_code).
 */
function tc_upsert_sequence_overlay(PDO $pdo, int $sequenceId, string $lang, ?string $name, ?string $description): void {
    $stmt = $pdo->prepare(
        "INSERT INTO sequence_overlay_texts
             (sequence_id, language_code, name_overlay, description_overlay)
         VALUES (:sid, :lang, :name, :desc)
         ON DUPLICATE KEY UPDATE
             name_overlay        = VALUES(name_overlay),
             description_overlay = VALUES(description_overlay)"
    );
    $stmt->bindValue(':sid', $sequenceId, PDO::PARAM_INT);
    $stmt->bindValue(':lang', $lang);
    $stmt->bindValue(':name', $name);
    $stmt->bindValue(':desc', $description);
    $stmt->execute();
}

// ============================================================================
// AI call wrapper (retry ×3, mirrors NSC pattern)
// ============================================================================

function tc_ai_call(
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
// Prompt builders
// ============================================================================

function tc_build_sketch_prompt(
    int    $sketchId,
    string $sketchName,
    array  $sourceTexts,
    string $targetLangCode,
    string $targetLangName
): string {
    $parts   = [];
    $parts[] = "=== TRANSLATION TASK ===";
    $parts[] = "Target language: $targetLangName ($targetLangCode)";
    $parts[] = "Content type: Sketch overlay texts (caption/subtitle lines for an animated scene)";
    $parts[] = "Series: The Anima Chronicles (science-fantasy anime, prestige tone)";
    $parts[] = "";
    $parts[] = "=== SOURCE (English) ===";
    $parts[] = "Sketch ID: $sketchId";
    $parts[] = "Sketch Name: $sketchName";
    $parts[] = "";
    foreach ($sourceTexts as $row) {
        $parts[] = "Line (display_order={$row['display_order']}):";
        $parts[] = $row['text_content'];
        $parts[] = "";
    }
    $parts[] = "=== INSTRUCTIONS ===";
    $parts[] = "Translate each line into $targetLangName. Preserve tone, register, and the series' mythic-elemental aesthetic.";
    $parts[] = "Do not summarise or paraphrase — translate the full content of each line.";
    $parts[] = "Return a JSON object where each key is the display_order integer (as a string) and the value is the translated string.";
    $parts[] = 'Example: {"0": "translated line", "1": "translated line"}';
    return implode("\n", $parts);
}

function tc_build_sequence_prompt(
    int    $sequenceId,
    string $sequenceName,
    string $nameOverlay,
    string $descriptionOverlay,
    string $targetLangCode,
    string $targetLangName
): string {
    $parts   = [];
    $parts[] = "=== TRANSLATION TASK ===";
    $parts[] = "Target language: $targetLangName ($targetLangCode)";
    $parts[] = "Content type: Sequence overlay — the public-facing title and description for an episode/sequence";
    $parts[] = "Series: The Anima Chronicles (science-fantasy anime, prestige tone)";
    $parts[] = "";
    $parts[] = "=== SOURCE (English) ===";
    $parts[] = "Sequence ID: $sequenceId";
    $parts[] = "Sequence Name: $sequenceName";
    $parts[] = "Name Overlay: $nameOverlay";
    $parts[] = "Description Overlay:";
    $parts[] = $descriptionOverlay;
    $parts[] = "";
    $parts[] = "=== INSTRUCTIONS ===";
    $parts[] = "Translate the Name Overlay and Description Overlay into $targetLangName.";
    $parts[] = "Preserve tone, register, and the series' mythic-elemental aesthetic.";
    $parts[] = "The name_overlay should read as a compelling episode title in $targetLangName.";
    $parts[] = "The description_overlay should feel like native-language synopsis copy, not translated English.";
    $parts[] = 'Return strict JSON with keys: name_overlay, description_overlay.';
    return implode("\n", $parts);
}

// ============================================================================
// Core: process one sequence for one language
// ============================================================================

function tc_process_sequence(
    PDO              $pdo,
    GeneratorService $service,
    GeneratorConfig  $cfg,
    int              $sequenceId,
    string           $langCode,
    string           $langName,
    bool             $rerun
): void {
    $sequence = tc_fetch_sequence($pdo, $sequenceId);
    if (!$sequence) {
        echo C_RED . "  Sequence #$sequenceId not found — skip.\n" . C_RESET;
        return;
    }

    echo C_CYAN . "\n  ── Sequence #$sequenceId: {$sequence['name']} → $langName ($langCode)" . C_RESET . "\n";

    // ── 1. Sequence overlay ──────────────────────────────────────────────────
    $seqSource = tc_fetch_sequence_source_overlay($pdo, $sequenceId);
    if (!$seqSource) {
        // Fall back to the sequence row itself
        $seqSource = [
            'name_overlay'        => $sequence['name'],
            'description_overlay' => $sequence['description'] ?? '',
        ];
    }

    $alreadyTranslated = !$rerun && tc_sequence_overlay_translated($pdo, $sequenceId, $langCode);

    if ($alreadyTranslated) {
        echo "  [seq overlay] " . C_GRAY . "cached\n" . C_RESET;
    } else {
        echo "  [seq overlay] translating... ";
        $seqPrompt = tc_build_sequence_prompt(
            $sequenceId,
            $sequence['name'],
            (string)($seqSource['name_overlay'] ?? $sequence['name']),
            (string)($seqSource['description_overlay'] ?? $sequence['description'] ?? ''),
            $langCode,
            $langName
        );
        $seqRaw  = tc_ai_call($service, $cfg, $seqPrompt, 'T');
        $seqData = $seqRaw ? tc_decode_json($seqRaw) : null;

        if ($seqData) {
            tc_upsert_sequence_overlay(
                $pdo,
                $sequenceId,
                $langCode,
                $seqData['name_overlay']        ?? null,
                $seqData['description_overlay'] ?? null
            );
            echo C_GREEN . " ✓\n" . C_RESET;
        } else {
            echo C_RED . " FAIL (could not parse response)\n" . C_RESET;
        }
    }

    // ── 2. Sketch overlays ───────────────────────────────────────────────────
    $sketchIds = tc_sketch_ids_from_sequence($sequence['sequence_data_decoded']);

    if (empty($sketchIds)) {
        echo C_YELLOW . "  No sketches in sequence data — nothing more to translate.\n" . C_RESET;
        return;
    }

    echo "  Sketches in sequence: " . count($sketchIds) . "\n";

    // Bulk-fetch sketch names
    $placeholders = implode(',', array_fill(0, count($sketchIds), '?'));
    $nameStmt     = $pdo->prepare("SELECT id, name FROM sketches WHERE id IN ($placeholders)");
    $nameStmt->execute($sketchIds);
    $sketchNames = array_column($nameStmt->fetchAll(PDO::FETCH_ASSOC), 'name', 'id');

    foreach ($sketchIds as $sketchId) {
        $sketchName  = $sketchNames[$sketchId] ?? "Sketch #$sketchId";
        $sourceTexts = tc_fetch_sketch_source_texts($pdo, $sketchId);

        if (empty($sourceTexts)) {
            echo "    sketch #$sketchId: " . C_GRAY . "no source texts — skip\n" . C_RESET;
            continue;
        }

        $translatedOrders = $rerun ? [] : tc_fetch_translated_sketch_orders($pdo, $sketchId, $langCode);
        $pendingTexts     = array_filter(
            $sourceTexts,
            fn($row) => !in_array((int)$row['display_order'], array_map('intval', $translatedOrders))
        );

        if (empty($pendingTexts)) {
            echo "    sketch #$sketchId [$sketchName]: " . C_GRAY . "all cached\n" . C_RESET;
            continue;
        }

        echo "    sketch #$sketchId [$sketchName]: " . count($pendingTexts) . " line(s)... ";

        $prompt  = tc_build_sketch_prompt($sketchId, $sketchName, array_values($pendingTexts), $langCode, $langName);
        $raw     = tc_ai_call($service, $cfg, $prompt, 'T');
        $decoded = $raw ? tc_decode_json($raw) : null;

        if (!$decoded) {
            echo C_RED . "FAIL\n" . C_RESET;
            continue;
        }

        $saved = 0;
        foreach ($pendingTexts as $row) {
            $ord  = (string)$row['display_order'];
            $text = $decoded[$ord] ?? $decoded[(int)$ord] ?? null;
            if ($text === null) {
                foreach ($decoded as $k => $v) {
                    if ((string)$k === $ord) { $text = $v; break; }
                }
            }
            if ($text) {
                tc_upsert_sketch_overlay($pdo, $sketchId, $langCode, (int)$row['display_order'], (string)$text);
                $saved++;
            }
        }

        echo C_GREEN . "✓ ($saved saved)\n" . C_RESET;
    }
}

// ============================================================================
// MAIN — argument parsing
// ============================================================================

$opts = getopt('', ['seq::', 'lang::', 'cinemagic::', 'rerun', 'qjobs::', 'json::']);

$hasSeqParam       = array_key_exists('seq',       $opts);
$hasCinemagicParam = array_key_exists('cinemagic', $opts);
$hasQjobsParam     = array_key_exists('qjobs',     $opts);
$hasJsonParam      = array_key_exists('json',      $opts);

$seqIdCli       = isset($opts['seq'])       ? (int)trim($opts['seq'])        : null;
$cinemagicIdCli = isset($opts['cinemagic']) ? (int)trim($opts['cinemagic'])  : null;
$langCli        = isset($opts['lang'])      ? strtolower(trim($opts['lang'])): null;
$qjobs          = isset($opts['qjobs'])     ? (int)$opts['qjobs']            : 0;
$rerun          = isset($opts['rerun']);
$jsonRaw        = isset($opts['json'])      ? trim($opts['json'])            : null;

// ── Bootstrap services ────────────────────────────────────────────────────────
$em   = $spw->getEntityManager();
$pdo  = $spw->getPDO();
$conn = $em->getConnection();

$repo   = $em->getRepository(GeneratorConfig::class);
$cfgObj = $repo->findOneBy(['configId' => TC_TRANSLATOR_CONFIG_ID]);

if (!$cfgObj) {
    echo C_RED . "ERROR: Missing generator config '" . TC_TRANSLATOR_CONFIG_ID . "'.\n";
    echo "Run the SQL at the bottom of this file to create it.\n" . C_RESET;
    exit(1);
}

$aiProvider = $spw->getAIProvider();
$service    = new GeneratorService(
    $aiProvider,
    new SchemaValidator(),
    new ResponseNormalizer(),
    $spw->getFileLogger()
);

// ============================================================================
// Build job list — four modes
// ============================================================================

$jobsToProcess = [];

// ── MODE 1: Queue mode — --qjobs=N ──────────────────────────────────────────
if ($hasQjobsParam && $qjobs > 0) {
    $rawJobs = $conn->fetchAllAssociative(
        "SELECT * FROM forge_jobs
          WHERE job_type = 'translation_compose'
            AND status   = 'pending'
          ORDER BY priority ASC, id ASC
          LIMIT " . $qjobs
    );
    if (empty($rawJobs)) {
        echo "No pending translation_compose jobs.\n";
        exit(0);
    }
    $jobsToProcess = $rawJobs;

// ── MODE 2: JSON payload mode — --json='{...}' ───────────────────────────────
} elseif ($hasJsonParam && $jsonRaw) {
    $jsonPayload = json_decode($jsonRaw, true);
    if (!is_array($jsonPayload)) {
        echo C_RED . "ERROR: --json value is not valid JSON.\n" . C_RESET;
        exit(1);
    }
    // Normalise: support both sequence_id and cinemagic_id inside JSON payload
    $jobsToProcess = tc_expand_json_payload($pdo, $jsonPayload, $rerun);

// ── MODE 3: Direct CLI — --seq + --lang  or  --cinemagic + --lang ────────────
} elseif (($hasSeqParam || $hasCinemagicParam) && $langCli) {

    $langRow = tc_fetch_language($pdo, $langCli);
    if (!$langRow) {
        echo C_RED . "Language '$langCli' not found in system_languages.\n" . C_RESET;
        exit(1);
    }

    if ($hasSeqParam && $seqIdCli > 0) {
        if (!tc_sequence_is_in_cinemagic($pdo, $seqIdCli)) {
            echo C_RED . "Sequence #$seqIdCli is not linked to any cinemagic — ineligible.\n" . C_RESET;
            exit(1);
        }
        $jobsToProcess[] = tc_make_job($seqIdCli, $langCli, $rerun);

    } elseif ($hasCinemagicParam && $cinemagicIdCli > 0) {
        $seqsInMag = tc_fetch_sequences_in_cinemagic($pdo, $cinemagicIdCli);
        if (empty($seqsInMag)) {
            echo C_YELLOW . "Cinemagic #$cinemagicIdCli has no sequences.\n" . C_RESET;
            exit(0);
        }
        foreach ($seqsInMag as $s) {
            $jobsToProcess[] = tc_make_job((int)$s['id'], $langCli, $rerun);
        }
    } else {
        echo C_RED . "Provide --seq=ID or --cinemagic=ID together with --lang=CODE.\n" . C_RESET;
        exit(1);
    }

// ── MODE 4: Interactive ───────────────────────────────────────────────────────
} else {

    echo C_CYAN . "\n🌐 TRANSLATION COMPOSER\n" . C_RESET;

    // Step 1: cinemagic
    $allCinemagics = tc_fetch_all_cinemagics($pdo);
    if (empty($allCinemagics)) {
        echo C_RED . "No cinemagics found.\n" . C_RESET;
        exit(0);
    }
    echo "\n" . C_CYAN . "── Cinemagics ──\n" . C_RESET;
    foreach ($allCinemagics as $cm) {
        echo "  [{$cm['id']}] {$cm['name']}\n";
    }
    $pickedCinemagic = (int)readline("Select Cinemagic ID (or 0 to pick any linked sequence): ");

    $sequenceChoices = [];
    if ($pickedCinemagic > 0) {
        $sequenceChoices = tc_fetch_sequences_in_cinemagic($pdo, $pickedCinemagic);
        if (empty($sequenceChoices)) {
            echo C_YELLOW . "No sequences in cinemagic #$pickedCinemagic.\n" . C_RESET;
            exit(0);
        }
    } else {
        $allLinked = $pdo->query(
            "SELECT DISTINCT ns.id, ns.name
               FROM narrative_sequences ns
               JOIN cinemagics_2_sequences c2s ON c2s.sequence_id = ns.id
              ORDER BY ns.id DESC LIMIT 100"
        )->fetchAll(PDO::FETCH_ASSOC);
        if (empty($allLinked)) {
            echo C_RED . "No cinemagic-linked sequences found.\n" . C_RESET;
            exit(0);
        }
        $sequenceChoices = $allLinked;
    }

    // Step 2: sequence (or all)
    echo "\n" . C_CYAN . "── Sequences ──\n" . C_RESET;
    echo "  [0] ALL sequences listed\n";
    foreach ($sequenceChoices as $s) {
        $label = isset($s['chapter_label']) && $s['chapter_label'] ? " — {$s['chapter_label']}" : '';
        echo "  [{$s['id']}] {$s['name']}$label\n";
    }
    $pickedSeqId = (int)readline("Select Sequence ID (0 = all): ");

    // Step 3: language
    $allLangs = tc_fetch_target_languages($pdo);
    if (empty($allLangs)) {
        echo C_RED . "No non-main languages in system_languages.\n" . C_RESET;
        exit(0);
    }
    echo "\n" . C_CYAN . "── Target Languages ──\n" . C_RESET;
    foreach ($allLangs as $l) {
        echo "  [{$l['code']}] {$l['name']}\n";
    }
    $pickedLang = strtolower(trim(readline("Enter language code: ")));
    if (!$pickedLang) { echo "Aborted.\n"; exit(0); }

    $langRow = tc_fetch_language($pdo, $pickedLang);
    if (!$langRow) {
        echo C_RED . "Language '$pickedLang' not recognised.\n" . C_RESET;
        exit(1);
    }

    // Step 4: rerun?
    $rerunInput = strtolower(trim(readline("Force re-translate already translated items? [y/N]: ")));
    $rerun = ($rerunInput === 'y');

    if ($pickedSeqId === 0) {
        foreach ($sequenceChoices as $s) {
            $jobsToProcess[] = tc_make_job((int)$s['id'], $pickedLang, $rerun);
        }
    } else {
        if (!tc_sequence_is_in_cinemagic($pdo, $pickedSeqId)) {
            echo C_RED . "Sequence #$pickedSeqId is not linked to any cinemagic.\n" . C_RESET;
            exit(1);
        }
        $jobsToProcess[] = tc_make_job($pickedSeqId, $pickedLang, $rerun);
    }
}

// ============================================================================
// Job helpers (must be defined before the job loop references them)
// ============================================================================

/**
 * Build a synthetic job array for a single sequence.
 */
function tc_make_job(int $sequenceId, string $lang, bool $rerun): array {
    return [
        'id'      => null,
        'payload' => json_encode([
            'sequence_id' => $sequenceId,
            'lang'        => $lang,
            'rerun'       => $rerun,
        ]),
    ];
}

/**
 * Expand a --json payload (which may carry cinemagic_id instead of sequence_id)
 * into one or more synthetic job rows.
 */
function tc_expand_json_payload(PDO $pdo, array $payload, bool $cliRerun): array {
    $lang        = strtolower(trim($payload['lang'] ?? ''));
    $rerun       = (bool)($payload['rerun'] ?? false) || $cliRerun;
    $sequenceId  = (int)($payload['sequence_id']  ?? 0);
    $cinemagicId = (int)($payload['cinemagic_id'] ?? 0);

    if (!$lang) {
        echo C_RED . "JSON payload missing 'lang'.\n" . C_RESET;
        exit(1);
    }

    if ($sequenceId > 0) {
        return [tc_make_job($sequenceId, $lang, $rerun)];
    }

    if ($cinemagicId > 0) {
        $seqs = tc_fetch_sequences_in_cinemagic($pdo, $cinemagicId);
        if (empty($seqs)) {
            echo C_YELLOW . "Cinemagic #$cinemagicId has no sequences.\n" . C_RESET;
            return [];
        }
        $jobs = [];
        foreach ($seqs as $s) {
            $jobs[] = tc_make_job((int)$s['id'], $lang, $rerun);
        }
        return $jobs;
    }

    echo C_RED . "JSON payload must contain 'sequence_id' or 'cinemagic_id'.\n" . C_RESET;
    exit(1);
}

// ============================================================================
// Job loop
// ============================================================================

echo C_CYAN . "\n=== TRANSLATION COMPOSER: " . count($jobsToProcess) . " job(s) ===\n" . C_RESET;

foreach ($jobsToProcess as $jobRow) {
    $jobId  = $jobRow['id'] ?? null;
    $jobCfg = json_decode($jobRow['payload'] ?? '{}', true) ?: [];

    $sequenceId  = (int)($jobCfg['sequence_id']  ?? 0);
    $cinemagicId = (int)($jobCfg['cinemagic_id'] ?? 0);
    $langCode    = strtolower(trim($jobCfg['lang'] ?? ''));
    $doRerun     = (bool)($jobCfg['rerun'] ?? false) || $rerun;

    // Queue jobs may carry cinemagic_id — expand them on the fly
    if (!$sequenceId && $cinemagicId) {
        $expandedSeqs = tc_fetch_sequences_in_cinemagic($pdo, $cinemagicId);
        foreach ($expandedSeqs as $s) {
            // Inject back as a synthetic non-DB job so the loop handles them
            $jobsToProcess[] = [
                'id'      => null,
                'payload' => json_encode([
                    'sequence_id' => (int)$s['id'],
                    'lang'        => $langCode,
                    'rerun'       => $doRerun,
                ]),
            ];
        }
        // Mark the parent cinemagic-level job done
        if ($jobId) {
            $conn->executeStatement(
                "UPDATE forge_jobs SET status='done', finished_at=NOW() WHERE id=?",
                [$jobId]
            );
        }
        continue;
    }

    if (!$sequenceId || !$langCode) {
        echo C_RED . "Invalid job payload (sequence_id=$sequenceId, lang=$langCode) — skip.\n" . C_RESET;
        if ($jobId) {
            $conn->executeStatement(
                "UPDATE forge_jobs SET status='failed', error_msg='Invalid payload', finished_at=NOW() WHERE id=?",
                [$jobId]
            );
        }
        continue;
    }

    $langRow = tc_fetch_language($pdo, $langCode);
    if (!$langRow) {
        echo C_RED . "Language '$langCode' not in system_languages — skip.\n" . C_RESET;
        if ($jobId) {
            $conn->executeStatement(
                "UPDATE forge_jobs SET status='failed', error_msg='Unknown language', finished_at=NOW() WHERE id=?",
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

        tc_process_sequence(
            $pdo,
            $service,
            $cfgObj,
            $sequenceId,
            $langCode,
            $langRow['name'],
            $doRerun
        );

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

echo "\n--- Translation Composer done ---\n";

// ============================================================================
// REQUIRED SQL — run once to install the generator config
//
// INSERT INTO `generator_config`
//     (`config_id`, `user_id`, `title`, `model`, `system_role`, `instructions`,
//      `parameters`, `output_schema`, `examples`, `oracle_config`,
//      `created_at`, `updated_at`, `active`, `is_public`, `list_order`)
// VALUES (
//   'overlay_text_translator_v1',
//   1,
//   'Overlay Text Translator',
//   'claude-fast',
//   'You are the official localisation translator for The Anima Chronicles, a prestige animated science-fantasy series. You translate overlay texts — scene captions, subtitle lines, episode titles, and synopses — into the requested target language. You preserve the series'' mythic, elemental, cinematic tone. You never summarise or paraphrase; you translate in full. You output only strict JSON as instructed.',
//   '["Read the source English text and target language specified in the input.","Translate all provided content into the target language accurately and completely.","Preserve tone, register, and the mythic-elemental aesthetic of The Anima Chronicles.","For sketch overlay lines: return a JSON object keyed by display_order integers (as strings), each value being the translated string.","For sequence overlays: return a JSON object with keys name_overlay and description_overlay.","The translation should feel native to the target language — not like translated English.","OUTPUT: Strict JSON only. No markdown, no prose outside the JSON."]',
//   '{"temperature": 0.2, "max_tokens": 4000}',
//   '{"type":"object","description":"Dynamic structure. Sketch overlays: keys are display_order integers as strings, values are translated strings. Sequence overlays: keys name_overlay and description_overlay.","additionalProperties":{"type":"string"}}',
//   '[]',
//   NULL,
//   NOW(), NOW(),
//   1, 1, 50
// );
//
// ALSO run this ALTER to add translation_compose to forge_jobs.job_type enum:
//
//   ALTER TABLE `forge_jobs`
//     MODIFY COLUMN `job_type`
//       ENUM(
//         'kg_sketch','lore_sketch','autopilot',
//         'md_curator_extract','md_curator_aggregate',
//         'narrative_sequence_compose','sketch_tag_extract',
//         'github_sync','translation_compose'
//       ) NOT NULL;
// ============================================================================
