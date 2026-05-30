<?php
// cli_overlay_compose.php
//
// Overlay Text Composer — English Scene Overlay Generator
//
// Generates English sketch_overlay_texts from sketches.description for sketches
// that belong to narrative_sequences. Also generates sequence_overlay_texts (en).
//
// The generated overlay texts are the source material used by cli_translation_compose.php.
// Maximum overlay per sketch: 150 words / 1000 characters total.
//
// ── Usage ───────────────────────────────────────────────────────────────────
//
//   Interactive (no args):
//     php cli_overlay_compose.php
//
//   Direct — single sequence:
//     php cli_overlay_compose.php --seq=96
//     php cli_overlay_compose.php --seq=96 --rerun
//
//   Direct — all sequences in a cinemagic:
//     php cli_overlay_compose.php --cinemagic=1
//     php cli_overlay_compose.php --cinemagic=1 --rerun
//
//   Direct — single sketch by ID (manual input mode):
//     php cli_overlay_compose.php --sketch=1234
//     php cli_overlay_compose.php --sketch=1234 --rerun
//
//   JSON payload mode (from cli_forge UI queue button):
//     php cli_overlay_compose.php --json='{"sequence_id":96,"rerun":false}'
//     php cli_overlay_compose.php --json='{"cinemagic_id":1,"rerun":false}'
//     php cli_overlay_compose.php --json='{"sketch_id":1234,"rerun":false}'
//
//   Queue mode (process pending forge_jobs):
//     php cli_overlay_compose.php --qjobs=5
//
// ── Queue job_type ───────────────────────────────────────────────────────────
//   overlay_compose
//
// ── Payload keys ────────────────────────────────────────────────────────────
//   sequence_id  (int)   — compose overlays for all sketches in a sequence
//   cinemagic_id (int)   — compose overlays for all sequences in a cinemagic
//   sketch_id    (int)   — compose overlay for a single sketch directly
//   rerun        (bool)  — force re-compose even if overlay texts already exist
//
// ── Selection modes ─────────────────────────────────────────────────────────
//   1. Sequence mode   — list all narrative sequences, pick one or run all
//   2. Cinemagic mode  — process every sequence in a cinemagic at once
//   3. Sketch ID mode  — direct sketch ID entry (no sequence navigation required)
//
// ── Required generator_config ───────────────────────────────────────────────
//   config_id: overlay_text_composer_en_v1
//   (SQL in cli_overlay_compose_config.sql)
// ============================================================================

if (php_sapi_name() !== 'cli') die("CLI only\n");

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;

// ── ANSI colours ──────────────────────────────────────────────────────────────
if (!defined('C_RESET')) {
    define('C_RESET',  "\033[0m");
    define('C_GREEN',  "\033[0m\033[32m");
    define('C_YELLOW', "\033[0m\033[33m");
    define('C_CYAN',   "\033[0m\033[36m");
    define('C_RED',    "\033[0m\033[31m");
    define('C_GRAY',   "\033[0m\033[90m");
    define('C_BLUE',   "\033[0m\033[34m");
}

// Generator config ID — install via SQL before first run.
const OC_CONFIG_ID = 'overlay_text_composer_en_v1';

// Hard limits for overlay text
const OC_MAX_WORDS = 150;
const OC_MAX_CHARS = 1000;

// ============================================================================
// JSON helpers
// ============================================================================

function oc_extract_balanced(string $text, int $startPos): ?string {
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

function oc_decode_json(string $raw): ?array {
    $raw = trim($raw);

    $d = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($d)) {
        return array_change_key_case($d, CASE_LOWER);
    }

    if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $raw, $m)) {
        $d = json_decode($m[1], true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($d)) {
            return array_change_key_case($d, CASE_LOWER);
        }
    }

    $pos = strpos($raw, '{');
    if ($pos !== false) {
        $sub = oc_extract_balanced($raw, $pos);
        if ($sub) {
            $sub = preg_replace('/,\s*(\]|\})/m', '$1', $sub);
            $d   = json_decode($sub, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($d)) {
                return array_change_key_case($d, CASE_LOWER);
            }
        }
    }
    return null;
}

// ============================================================================
// Text constraint helpers
// ============================================================================

/**
 * Truncate text to stay within OC_MAX_WORDS and OC_MAX_CHARS.
 * Truncates on word boundary.
 */
function oc_enforce_limits(string $text): string {
    $text = trim($text);

    // chars first
    if (strlen($text) > OC_MAX_CHARS) {
        $text = substr($text, 0, OC_MAX_CHARS);
        // snap back to last complete word
        $pos  = strrpos($text, ' ');
        if ($pos !== false) $text = substr($text, 0, $pos);
        $text = trim($text);
    }

    // words
    $words = preg_split('/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
    if (count($words) > OC_MAX_WORDS) {
        $text = implode(' ', array_slice($words, 0, OC_MAX_WORDS));
    }

    return trim($text);
}

/**
 * Count words in a string.
 */
function oc_word_count(string $text): int {
    return count(preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY));
}

// ============================================================================
// Fetch helpers
// ============================================================================

function oc_fetch_all_cinemagics(PDO $pdo): array {
    return $pdo->query(
        "SELECT id, name, description FROM cinemagics ORDER BY id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
}

function oc_fetch_sequences_in_cinemagic(PDO $pdo, int $cinemagicId): array {
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

function oc_sequence_is_in_cinemagic(PDO $pdo, int $sequenceId): bool {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM cinemagics_2_sequences WHERE sequence_id = ? LIMIT 1"
    );
    $stmt->execute([$sequenceId]);
    return (bool)$stmt->fetchColumn();
}

function oc_fetch_sequence(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare("SELECT * FROM narrative_sequences WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return null;
    $row['sequence_data_decoded'] = json_decode($row['sequence_data'] ?? '[]', true) ?: [];
    return $row;
}

/**
 * Fetch a single sketch row (id, name, description, description_raw).
 */
function oc_fetch_sketch(PDO $pdo, int $id): ?array {
    $stmt = $pdo->prepare(
        "SELECT id, name, description, description_raw FROM sketches WHERE id = ?"
    );
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Bulk-fetch sketches by array of IDs.
 * Returns assoc array keyed by id.
 */
function oc_fetch_sketches_bulk(PDO $pdo, array $ids): array {
    if (empty($ids)) return [];
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt         = $pdo->prepare(
        "SELECT id, name, description, description_raw FROM sketches WHERE id IN ($placeholders)"
    );
    $stmt->execute($ids);
    return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), null, 'id');
}

/**
 * All unique sketch IDs referenced in a sequence's sequence_data JSON.
 */
function oc_sketch_ids_from_sequence(array $sequenceDataDecoded): array {
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
 * Check if English overlay texts already exist for a sketch.
 */
function oc_sketch_has_overlay(PDO $pdo, int $sketchId): bool {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM sketch_overlay_texts
          WHERE sketch_id = ? AND language_code = 'en' LIMIT 1"
    );
    $stmt->execute([$sketchId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Check if English sequence overlay already exists.
 */
function oc_sequence_has_overlay(PDO $pdo, int $sequenceId): bool {
    $stmt = $pdo->prepare(
        "SELECT 1 FROM sequence_overlay_texts
          WHERE sequence_id = ? AND language_code = 'en' LIMIT 1"
    );
    $stmt->execute([$sequenceId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Delete existing English overlay texts for a sketch (for rerun).
 */
function oc_delete_sketch_overlay(PDO $pdo, int $sketchId): void {
    $stmt = $pdo->prepare(
        "DELETE FROM sketch_overlay_texts WHERE sketch_id = ? AND language_code = 'en'"
    );
    $stmt->execute([$sketchId]);
}

/**
 * Delete existing English sequence overlay (for rerun).
 */
function oc_delete_sequence_overlay(PDO $pdo, int $sequenceId): void {
    $stmt = $pdo->prepare(
        "DELETE FROM sequence_overlay_texts WHERE sequence_id = ? AND language_code = 'en'"
    );
    $stmt->execute([$sequenceId]);
}

/**
 * Insert a sketch_overlay_texts row (English, display_order).
 */
function oc_insert_sketch_overlay(PDO $pdo, int $sketchId, string $text, int $displayOrder): void {
    $stmt = $pdo->prepare(
        "INSERT INTO sketch_overlay_texts
             (sketch_id, text_content, display_order, language_code)
         VALUES (:sid, :txt, :ord, 'en')"
    );
    $stmt->bindValue(':sid', $sketchId, PDO::PARAM_INT);
    $stmt->bindValue(':txt', $text);
    $stmt->bindValue(':ord', $displayOrder, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * Upsert sequence_overlay_texts (English).
 */
function oc_upsert_sequence_overlay(PDO $pdo, int $sequenceId, ?string $name, ?string $description): void {
    $stmt = $pdo->prepare(
        "INSERT INTO sequence_overlay_texts
             (sequence_id, language_code, name_overlay, description_overlay)
         VALUES (:sid, 'en', :name, :desc)
         ON DUPLICATE KEY UPDATE
             name_overlay        = VALUES(name_overlay),
             description_overlay = VALUES(description_overlay)"
    );
    $stmt->bindValue(':sid', $sequenceId, PDO::PARAM_INT);
    $stmt->bindValue(':name', $name);
    $stmt->bindValue(':desc', $description);
    $stmt->execute();
}

// ============================================================================
// AI call wrapper (retry ×3)
// ============================================================================

function oc_ai_call(
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

/**
 * Build the prompt for generating overlay texts from a sketch description.
 *
 * The full world description primer is embedded so the AI produces contextually
 * rich, tonally correct overlay copy rather than generic captions.
 */
function oc_build_sketch_overlay_prompt(array $sketch): string {
    $id          = (int)$sketch['id'];
    $name        = $sketch['name'] ?? "Sketch #$id";
    $description = trim($sketch['description'] ?? '');
    $descRaw     = trim($sketch['description_raw'] ?? '');

    // Prefer the refined description; fall back to raw
    $sourceDesc = $description ?: $descRaw;

    $parts   = [];
    $parts[] = "=== TASK ===";
    $parts[] = "Write English scene overlay texts for a single animated sketch (scene) from";
    $parts[] = "The Anima Chronicles — a prestige science-fantasy anime series.";
    $parts[] = "";
    $parts[] = "HARD LIMITS: The TOTAL text across ALL lines must not exceed " . OC_MAX_WORDS . " words";
    $parts[] = "AND must not exceed " . OC_MAX_CHARS . " characters. Stay well within both limits.";
    $parts[] = "";
    $parts[] = "=== SCENE SOURCE ===";
    $parts[] = "Sketch ID   : $id";
    $parts[] = "Sketch Name : $name";
    $parts[] = "Description :";
    $parts[] = $sourceDesc;
    $parts[] = "";
    $parts[] = "=== WORLD PRIMER: THE ANIMA UNIVERSE ===";
    $parts[] = "Technology and spiritual energy coexist. Spectres and Revenants are sentient entities";
    $parts[] = "representing physics (time, gravity, light). They bond with hosts; Anima usage burns";
    $parts[] = "calories (The Tether). Seven civilisations:";
    $parts[] = "• Nova Terra: Solarpunk sky-city (3,000m altitude). Grown Iron-Wood spires. Radical trust,";
    $parts[] = "  bioluminescence, Vitalis (biology magic), Lumina (light magic). 150-year lifespans.";
    $parts[] = "• Crater City: Vertical megalopolis inside a crater. Polished black ceramic, UV/blacklight";
    $parts[] = "  illumination, clinical silence, life-threatening efficiency. Ghost-white fractal glows.";
    $parts[] = "• Tidalcross: Floating megacity at triple-current convergence. Hyper-dense port energy,";
    $parts[] = "  maritime chaos, 3.2M inhabitants on surface and underwater in the Blue Cathedral.";
    $parts[] = "• The Drift: Dual deserts — Scrap-Dunes and Crimson Wastes. Sand-ships on Momentum,";
    $parts[] = "  nomadic tribes, patchwork tech, reality distortions.";
    $parts[] = "• Shadow Scab: 300-year-old regenerated tropical jungle (150m trees). Sentient, armed";
    $parts[] = "  pacifism. 50k surface stewards; pale, patient, deep Vitalis bonds.";
    $parts[] = "• Vortex Station: Decaying orbital ring. NASA-punk. Air is money (Oxygen Credits).";
    $parts[] = "  Void-Born humans, dockworker cyborgs, zero-G industrial cathedrals.";
    $parts[] = "• Aetherion: Frozen North Pole. Post-human Energy Beings. Reality is negotiable;";
    $parts[] = "  cause and effect loop. The Shadow: dissolving into the infinite.";
    $parts[] = "• Emberveil: Five-nature forge city (lava, river, desert, delta, coast). Steam, friction,";
    $parts[] = "  argument, repair. Black stone, woven reed, storm-worn timber.";
    $parts[] = "";
    $parts[] = "ANIMA ENERGY VISUALS:";
    $parts[] = "Chrono=gold/silver rings; Gravita=deep purple lensing; Momentum=crimson streaks;";
    $parts[] = "Energia=electric blue arcs; Therma=heat haze/crystalline; Lumina=prismatic flares;";
    $parts[] = "Resonantia=air ripples; Magnetica=blue auroras; Fluidica=liquid currents;";
    $parts[] = "Cohesiva=amber lattices; Probabilis=glitching reality; Vitalis=emerald glowing veins;";
    $parts[] = "Noetica=silver mist/halos; Spatia=void black holes/Escher geometry;";
    $parts[] = "Oscilla=glowing spirals.";
    $parts[] = "";
    $parts[] = "TONE: Cinematic, mythic-elemental, prestige anime. Epic yet intimate. Never generic.";
    $parts[] = "AVOID overusing: neon, aurora, vortex, lattice, pulsing, whisper, crystal, drone,";
    $parts[] = "iridescent, quantum, bloom, tendril, circuitry, bioluminescent, holo.";
    $parts[] = "";
    $parts[] = "=== OVERLAY TEXT RULES ===";
    $parts[] = "1. Write 1–4 overlay lines (display_order 0, 1, 2, 3) that will appear as scene captions.";
    $parts[] = "2. Each line is a short, punchy, poetic caption — not a full sentence necessarily.";
    $parts[] = "   Think: chapter cards, intertitles, atmospheric labels, or one-line scene poetry.";
    $parts[] = "3. Lines may work as a unit (build on each other) or stand alone.";
    $parts[] = "4. TOTAL across all lines: ≤ " . OC_MAX_WORDS . " words, ≤ " . OC_MAX_CHARS . " characters.";
    $parts[] = "5. Language: English only. Tone: prestige animated series, not marketing copy.";
    $parts[] = "6. Draw directly from the scene description and the world's sensory/visual language.";
    $parts[] = "";
    $parts[] = "=== OUTPUT FORMAT ===";
    $parts[] = "Return ONLY a JSON object. Keys are display_order integers (as strings: \"0\", \"1\", …).";
    $parts[] = "Values are the overlay text strings.";
    $parts[] = "Example: {\"0\": \"The sky remembers falling.\", \"1\": \"She does not.\"}";
    $parts[] = "No markdown, no prose, no explanations outside the JSON.";

    return implode("\n", $parts);
}

/**
 * Build the prompt for generating a sequence overlay (name + description).
 */
function oc_build_sequence_overlay_prompt(array $sequence): string {
    $id   = (int)$sequence['id'];
    $name = $sequence['name'] ?? "Sequence #$id";
    $desc = trim($sequence['description'] ?? '');

    $parts   = [];
    $parts[] = "=== TASK ===";
    $parts[] = "Write English overlay texts for a narrative sequence (episode/chapter) from";
    $parts[] = "The Anima Chronicles — a prestige science-fantasy anime series.";
    $parts[] = "";
    $parts[] = "=== SEQUENCE SOURCE ===";
    $parts[] = "Sequence ID  : $id";
    $parts[] = "Sequence Name: $name";
    if ($desc) {
        $parts[] = "Description  :";
        $parts[] = $desc;
    }
    $parts[] = "";
    $parts[] = "=== WORLD PRIMER ===";
    $parts[] = "Seven civilisations inhabit this sci-fantasy world: Nova Terra (solarpunk sky-city),";
    $parts[] = "Crater City (blacklight megalopolis), Tidalcross (maritime megacity), The Drift (deserts),";
    $parts[] = "Shadow Scab (regenerated jungle), Vortex Station (orbital ring), Aetherion (polar enigma).";
    $parts[] = "Spiritual physics (Anima) governs this universe. Tone: epic, intimate, mythic-elemental.";
    $parts[] = "";
    $parts[] = "=== OUTPUT RULES ===";
    $parts[] = "name_overlay       : A compelling episode/chapter title for public display. Max 10 words.";
    $parts[] = "description_overlay: A 2–5 sentence synopsis for public display. Max 80 words.";
    $parts[] = "Both in English. Prestige animated series register — not marketing copy.";
    $parts[] = "";
    $parts[] = "Return ONLY strict JSON: {\"name_overlay\": \"...\", \"description_overlay\": \"...\"}";
    $parts[] = "No markdown, no prose outside the JSON.";

    return implode("\n", $parts);
}

// ============================================================================
// Core: compose overlay for a single sketch
// ============================================================================

function oc_compose_sketch(
    PDO              $pdo,
    GeneratorService $service,
    GeneratorConfig  $cfg,
    int              $sketchId,
    bool             $rerun
): bool {
    $sketch = oc_fetch_sketch($pdo, $sketchId);
    if (!$sketch) {
        echo C_RED . "  Sketch #$sketchId not found — skip.\n" . C_RESET;
        return false;
    }

    $name = $sketch['name'] ?? "Sketch #$sketchId";

    // Check source material
    $sourceDesc = trim($sketch['description'] ?? '') ?: trim($sketch['description_raw'] ?? '');
    if (!$sourceDesc) {
        echo "  sketch #$sketchId [$name]: " . C_YELLOW . "no description — skip\n" . C_RESET;
        return false;
    }

    // Check existing
    if (!$rerun && oc_sketch_has_overlay($pdo, $sketchId)) {
        echo "  sketch #$sketchId [$name]: " . C_GRAY . "cached\n" . C_RESET;
        return true;
    }

    echo "  sketch #$sketchId [$name]: composing... ";

    $prompt = oc_build_sketch_overlay_prompt($sketch);
    $raw    = oc_ai_call($service, $cfg, $prompt, 'C');

    if (!$raw) {
        echo C_RED . " FAIL (no response)\n" . C_RESET;
        return false;
    }

    $decoded = oc_decode_json($raw);
    if (!$decoded) {
        echo C_RED . " FAIL (JSON parse error)\n" . C_RESET;
        return false;
    }

    // Enforce limits across all lines combined
    $allText   = implode(' ', $decoded);
    $totalW    = oc_word_count($allText);
    $totalC    = strlen($allText);

    if ($totalW > OC_MAX_WORDS || $totalC > OC_MAX_CHARS) {
        echo C_YELLOW . " [OVER LIMIT: {$totalW}w/{$totalC}c — trimming] " . C_RESET;
        // Trim from the last line backwards
        $keys = array_keys($decoded);
        rsort($keys);
        foreach ($keys as $k) {
            $remaining = OC_MAX_CHARS - (strlen(implode(' ', $decoded)) - strlen($decoded[$k]));
            if ($remaining < 10) {
                unset($decoded[$k]);
            } else {
                $decoded[$k] = oc_enforce_limits($decoded[$k]);
            }
            $allText = implode(' ', $decoded);
            if (oc_word_count($allText) <= OC_MAX_WORDS && strlen($allText) <= OC_MAX_CHARS) break;
        }
    }

    if (empty($decoded)) {
        echo C_RED . " FAIL (empty after limit enforcement)\n" . C_RESET;
        return false;
    }

    // Delete existing en rows before inserting (handles both rerun and fresh)
    oc_delete_sketch_overlay($pdo, $sketchId);

    $saved = 0;
    foreach ($decoded as $orderKey => $text) {
        $displayOrder = (int)$orderKey;
        $text         = trim((string)$text);
        if (!$text) continue;
        oc_insert_sketch_overlay($pdo, $sketchId, $text, $displayOrder);
        $saved++;
    }

    $finalW = oc_word_count(implode(' ', $decoded));
    $finalC = strlen(implode(' ', $decoded));
    echo C_GREEN . " ✓ ($saved line(s), {$finalW}w/{$finalC}c)\n" . C_RESET;
    return true;
}

// ============================================================================
// Core: compose sequence overlay (name + description)
// ============================================================================

function oc_compose_sequence_overlay(
    PDO              $pdo,
    GeneratorService $service,
    GeneratorConfig  $cfg,
    array            $sequence,
    bool             $rerun
): void {
    $seqId = (int)$sequence['id'];

    if (!$rerun && oc_sequence_has_overlay($pdo, $seqId)) {
        echo "  [seq overlay] " . C_GRAY . "cached\n" . C_RESET;
        return;
    }

    echo "  [seq overlay] composing... ";

    $prompt = oc_build_sequence_overlay_prompt($sequence);
    $raw    = oc_ai_call($service, $cfg, $prompt, 'C');

    if (!$raw) {
        echo C_RED . " FAIL (no response)\n" . C_RESET;
        return;
    }

    $decoded = oc_decode_json($raw);
    if (!$decoded) {
        echo C_RED . " FAIL (JSON parse)\n" . C_RESET;
        return;
    }

    $nameOverlay = isset($decoded['name_overlay']) ? trim($decoded['name_overlay']) : null;
    $descOverlay = isset($decoded['description_overlay']) ? trim($decoded['description_overlay']) : null;

    // Apply limits to description overlay
    if ($descOverlay) {
        $descOverlay = oc_enforce_limits($descOverlay);
    }

    if ($rerun) {
        oc_delete_sequence_overlay($pdo, $seqId);
    }

    oc_upsert_sequence_overlay($pdo, $seqId, $nameOverlay, $descOverlay);
    echo C_GREEN . " ✓\n" . C_RESET;
}

// ============================================================================
// Core: process one full sequence
// ============================================================================

function oc_process_sequence(
    PDO              $pdo,
    GeneratorService $service,
    GeneratorConfig  $cfg,
    int              $sequenceId,
    bool             $rerun
): void {
    $sequence = oc_fetch_sequence($pdo, $sequenceId);
    if (!$sequence) {
        echo C_RED . "  Sequence #$sequenceId not found — skip.\n" . C_RESET;
        return;
    }

    echo C_CYAN . "\n  ── Sequence #$sequenceId: {$sequence['name']}" . C_RESET . "\n";

    // 1. Sequence overlay
    oc_compose_sequence_overlay($pdo, $service, $cfg, $sequence, $rerun);

    // 2. Sketch overlays
    $sketchIds = oc_sketch_ids_from_sequence($sequence['sequence_data_decoded']);

    if (empty($sketchIds)) {
        echo C_YELLOW . "  No sketches in sequence data.\n" . C_RESET;
        return;
    }

    echo "  Sketches in sequence: " . count($sketchIds) . "\n";

    foreach ($sketchIds as $sketchId) {
        oc_compose_sketch($pdo, $service, $cfg, $sketchId, $rerun);
    }
}

// ============================================================================
// Job helpers
// ============================================================================

function oc_make_job_seq(int $sequenceId, bool $rerun): array {
    return [
        'id'      => null,
        'payload' => json_encode([
            'sequence_id' => $sequenceId,
            'rerun'       => $rerun,
        ]),
    ];
}

function oc_make_job_sketch(int $sketchId, bool $rerun): array {
    return [
        'id'      => null,
        'payload' => json_encode([
            'sketch_id' => $sketchId,
            'rerun'     => $rerun,
        ]),
    ];
}

function oc_expand_json_payload(PDO $pdo, array $payload, bool $cliRerun): array {
    $rerun       = (bool)($payload['rerun'] ?? false) || $cliRerun;
    $sequenceId  = (int)($payload['sequence_id']  ?? 0);
    $cinemagicId = (int)($payload['cinemagic_id'] ?? 0);
    $sketchId    = (int)($payload['sketch_id']    ?? 0);

    if ($sketchId > 0) {
        return [oc_make_job_sketch($sketchId, $rerun)];
    }

    if ($sequenceId > 0) {
        return [oc_make_job_seq($sequenceId, $rerun)];
    }

    if ($cinemagicId > 0) {
        $seqs = oc_fetch_sequences_in_cinemagic($pdo, $cinemagicId);
        if (empty($seqs)) {
            echo C_YELLOW . "Cinemagic #$cinemagicId has no sequences.\n" . C_RESET;
            return [];
        }
        $jobs = [];
        foreach ($seqs as $s) {
            $jobs[] = oc_make_job_seq((int)$s['id'], $rerun);
        }
        return $jobs;
    }

    echo C_RED . "JSON payload must contain 'sketch_id', 'sequence_id', or 'cinemagic_id'.\n" . C_RESET;
    exit(1);
}

// ============================================================================
// MAIN — argument parsing
// ============================================================================

$opts = getopt('', ['seq::', 'cinemagic::', 'sketch::', 'rerun', 'qjobs::', 'json::']);

$hasSeqParam       = array_key_exists('seq',       $opts);
$hasCinemagicParam = array_key_exists('cinemagic', $opts);
$hasSketchParam    = array_key_exists('sketch',    $opts);
$hasQjobsParam     = array_key_exists('qjobs',    $opts);
$hasJsonParam      = array_key_exists('json',      $opts);

$seqIdCli       = isset($opts['seq'])       ? (int)trim($opts['seq'])        : null;
$cinemagicIdCli = isset($opts['cinemagic']) ? (int)trim($opts['cinemagic'])  : null;
$sketchIdCli    = isset($opts['sketch'])    ? (int)trim($opts['sketch'])     : null;
$qjobs          = isset($opts['qjobs'])     ? (int)$opts['qjobs']            : 0;
$rerun          = isset($opts['rerun']);
$jsonRaw        = isset($opts['json'])      ? trim($opts['json'])            : null;

// ── Bootstrap services ───────────────────────────────────────────────────────
$em   = $spw->getEntityManager();
$pdo  = $spw->getPDO();
$conn = $em->getConnection();

$repo   = $em->getRepository(GeneratorConfig::class);
$cfgObj = $repo->findOneBy(['configId' => OC_CONFIG_ID]);

if (!$cfgObj) {
    echo C_RED . "ERROR: Missing generator config '" . OC_CONFIG_ID . "'.\n";
    echo "Install via: cli_overlay_compose_config.sql\n" . C_RESET;
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
          WHERE job_type = 'overlay_compose'
            AND status   = 'pending'
          ORDER BY priority ASC, id ASC
          LIMIT " . $qjobs
    );
    if (empty($rawJobs)) {
        echo "No pending overlay_compose jobs.\n";
        exit(0);
    }
    $jobsToProcess = $rawJobs;

// ── MODE 2: JSON payload mode ────────────────────────────────────────────────
} elseif ($hasJsonParam && $jsonRaw) {

    $jsonPayload = json_decode($jsonRaw, true);
    if (!is_array($jsonPayload)) {
        echo C_RED . "ERROR: --json value is not valid JSON.\n" . C_RESET;
        exit(1);
    }
    $jobsToProcess = oc_expand_json_payload($pdo, $jsonPayload, $rerun);

// ── MODE 3: Direct CLI args ──────────────────────────────────────────────────
} elseif ($hasSketchParam && $sketchIdCli > 0) {
    // Direct sketch ID
    $jobsToProcess[] = oc_make_job_sketch($sketchIdCli, $rerun);

} elseif ($hasSeqParam && $seqIdCli > 0) {
    if (!oc_sequence_is_in_cinemagic($pdo, $seqIdCli)) {
        echo C_YELLOW . "Warning: Sequence #$seqIdCli is not linked to any cinemagic.\n" . C_RESET;
        $proceed = strtolower(trim(readline("Proceed anyway? [y/N]: ")));
        if ($proceed !== 'y') exit(0);
    }
    $jobsToProcess[] = oc_make_job_seq($seqIdCli, $rerun);

} elseif ($hasCinemagicParam && $cinemagicIdCli > 0) {
    $seqsInMag = oc_fetch_sequences_in_cinemagic($pdo, $cinemagicIdCli);
    if (empty($seqsInMag)) {
        echo C_YELLOW . "Cinemagic #$cinemagicIdCli has no sequences.\n" . C_RESET;
        exit(0);
    }
    foreach ($seqsInMag as $s) {
        $jobsToProcess[] = oc_make_job_seq((int)$s['id'], $rerun);
    }

// ── MODE 4: Interactive ───────────────────────────────────────────────────────
} else {

    echo C_CYAN . "\n✍  OVERLAY TEXT COMPOSER (English)\n" . C_RESET;
    echo C_GRAY . "Generates English sketch & sequence overlay texts from descriptions.\n" . C_RESET;

    // Sub-mode selection
    echo "\n" . C_CYAN . "── Selection Mode ──\n" . C_RESET;
    echo "  [1] Sequence mode   (list ALL narrative sequences, pick one or all)\n";
    echo "  [2] Cinemagic mode  (process ALL sequences in a cinemagic)\n";
    echo "  [3] Sketch ID mode  (enter a sketch ID directly)\n";
    $mode = (int)trim(readline("Select mode [1/2/3]: "));

    if ($mode === 3) {
        // ── Sketch ID mode ───────────────────────────────────────────────────
        $inputSketchId = (int)trim(readline("Enter sketch ID: "));
        if ($inputSketchId <= 0) { echo "Aborted.\n"; exit(0); }

        $rerunInput = strtolower(trim(readline("Force re-compose if overlay exists? [y/N]: ")));
        $rerun      = ($rerunInput === 'y');

        $jobsToProcess[] = oc_make_job_sketch($inputSketchId, $rerun);

    } elseif ($mode === 2) {
        // ── Cinemagic mode ───────────────────────────────────────────────────
        $allCinemagics = oc_fetch_all_cinemagics($pdo);
        if (empty($allCinemagics)) { echo C_RED . "No cinemagics found.\n" . C_RESET; exit(0); }

        echo "\n" . C_CYAN . "── Cinemagics ──\n" . C_RESET;
        foreach ($allCinemagics as $cm) {
            echo "  [{$cm['id']}] {$cm['name']}\n";
        }
        $pickedCinemagicId = (int)trim(readline("Select Cinemagic ID: "));
        if ($pickedCinemagicId <= 0) { echo "Aborted.\n"; exit(0); }

        $seqsInMag = oc_fetch_sequences_in_cinemagic($pdo, $pickedCinemagicId);
        if (empty($seqsInMag)) {
            echo C_YELLOW . "Cinemagic #$pickedCinemagicId has no sequences.\n" . C_RESET;
            exit(0);
        }

        echo "  Found " . count($seqsInMag) . " sequence(s).\n";
        $rerunInput = strtolower(trim(readline("Force re-compose existing overlays? [y/N]: ")));
        $rerun      = ($rerunInput === 'y');

        foreach ($seqsInMag as $s) {
            $jobsToProcess[] = oc_make_job_seq((int)$s['id'], $rerun);
        }

    } else {
        // ── Sequence mode (default / mode 1) ────────────────────────────────
        // Lists ALL narrative sequences regardless of cinemagic membership.
        $allSequences = $pdo->query(
            "SELECT id, name FROM narrative_sequences ORDER BY id ASC"
        )->fetchAll(PDO::FETCH_ASSOC);

        if (empty($allSequences)) {
            echo C_RED . "No narrative sequences found.\n" . C_RESET;
            exit(0);
        }

        echo "\n" . C_CYAN . "── Narrative Sequences ──\n" . C_RESET;
        echo "  [0] ALL sequences\n";
        foreach ($allSequences as $s) {
            echo "  [{$s['id']}] {$s['name']}\n";
        }
        $pickedSeqId = (int)trim(readline("Select Sequence ID (0 = all): "));

        $rerunInput = strtolower(trim(readline("Force re-compose existing overlays? [y/N]: ")));
        $rerun      = ($rerunInput === 'y');

        if ($pickedSeqId === 0) {
            foreach ($allSequences as $s) {
                $jobsToProcess[] = oc_make_job_seq((int)$s['id'], $rerun);
            }
        } else {
            $jobsToProcess[] = oc_make_job_seq($pickedSeqId, $rerun);
        }
    }
}

// ============================================================================
// Job loop
// ============================================================================

echo C_CYAN . "\n=== OVERLAY COMPOSER: " . count($jobsToProcess) . " job(s) ===\n" . C_RESET;

foreach ($jobsToProcess as &$jobRow) {
    $jobId  = $jobRow['id'] ?? null;
    $jobCfg = json_decode($jobRow['payload'] ?? '{}', true) ?: [];

    $sequenceId  = (int)($jobCfg['sequence_id']  ?? 0);
    $cinemagicId = (int)($jobCfg['cinemagic_id'] ?? 0);
    $sketchId    = (int)($jobCfg['sketch_id']    ?? 0);
    $doRerun     = (bool)($jobCfg['rerun'] ?? false) || $rerun;

    // Cinemagic-level queue jobs: expand on the fly
    if (!$sequenceId && !$sketchId && $cinemagicId) {
        $expandedSeqs = oc_fetch_sequences_in_cinemagic($pdo, $cinemagicId);
        foreach ($expandedSeqs as $s) {
            $jobsToProcess[] = [
                'id'      => null,
                'payload' => json_encode([
                    'sequence_id' => (int)$s['id'],
                    'rerun'       => $doRerun,
                ]),
            ];
        }
        if ($jobId) {
            $conn->executeStatement(
                "UPDATE forge_jobs SET status='done', finished_at=NOW() WHERE id=?",
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

        if ($sketchId > 0) {
            // ── Single sketch mode ──────────────────────────────────────────
            echo C_CYAN . "\n── Single Sketch #$sketchId" . C_RESET . "\n";
            oc_compose_sketch($pdo, $service, $cfgObj, $sketchId, $doRerun);

        } elseif ($sequenceId > 0) {
            // ── Sequence mode ───────────────────────────────────────────────
            oc_process_sequence($pdo, $service, $cfgObj, $sequenceId, $doRerun);

        } else {
            echo C_RED . "Invalid job payload — skip.\n" . C_RESET;
            if ($jobId) {
                $conn->executeStatement(
                    "UPDATE forge_jobs SET status='failed', error_msg='Invalid payload', finished_at=NOW() WHERE id=?",
                    [$jobId]
                );
            }
            continue;
        }

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
unset($jobRow);

echo "\n--- Overlay Composer done ---\n";
