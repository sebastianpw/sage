<?php
// public/cli_sequence_curator.php
// Showrunner V9 — Sequence Curation Pass
// -------------------------------------------------------
// Second curation pass: reads sketch_analysis and maps
// each sketch to controlled sequencer vocabulary, saving
// results into sketch_sequence_analysis.
//
// Run via: bash/auto_sequence_curate.sh
// Or directly: php cli_sequence_curator.php [limit]
// -------------------------------------------------------

if (php_sapi_name() !== 'cli') die("CLI only\n");

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;

// -------------------------------------------------------
// HELPERS
// -------------------------------------------------------

/**
 * Convert any scalar / nested array / stringable object into clean prompt text.
 * - Arrays are flattened recursively.
 * - Empty values are removed.
 * - Duplicate leaf values are removed while preserving order.
 */
function spw_flatten_prompt_value($value): string
{
    $parts = [];

    $walk = function ($v) use (&$parts, &$walk) {
        if (is_array($v)) {
            foreach ($v as $item) {
                $walk($item);
            }
            return;
        }

        if (is_object($v)) {
            if (method_exists($v, '__toString')) {
                $s = trim((string)$v);
                if ($s !== '') {
                    $parts[] = $s;
                }
            }
            return;
        }

        if ($v === null) {
            return;
        }

        if (is_bool($v)) {
            $s = $v ? 'true' : 'false';
        } elseif (is_scalar($v)) {
            $s = trim((string)$v);
        } else {
            return;
        }

        if ($s !== '') {
            $parts[] = $s;
        }
    };

    $walk($value);

    if (empty($parts)) {
        return '';
    }

    $parts = array_values(array_unique($parts));
    return implode(', ', $parts);
}

function spw_prompt_append(string &$promptInput, string $label, $value, ?int $limit = null): void
{
    $text = spw_flatten_prompt_value($value);
    if ($text === '') {
        return;
    }

    if ($limit !== null) {
        $text = substr($text, 0, $limit);
    }

    $promptInput .= $label . $text . "\n";
}

// -------------------------------------------------------
// CONFIG
// -------------------------------------------------------
$limit           = isset($argv[1]) ? (int)$argv[1] : 1000;
$curatorConfigId = 'sketch_sequence_curator_v1';

// Allowed enum values for validation
$ENUMS = [
    'narrative_function' => ['REVELATION','DECISION','ESCALATION','REVERSAL','BREATHER','MIRROR','COMPLICATION','FALSE_RESOLUTION','FORM_BEAT','COMIC_RELIEF'],
    'layer'              => ['plot','character','world','theme'],
    'energy'             => ['advance','pause','turn'],
    'position'           => ['opener','mid','closer','any'],
    'standalone'         => ['yes','needs-context','provides-context'],
    'intensity'          => ['low','medium','high'],
    'shot_scale'         => ['establishing','master','medium','close-up','insert','cutaway'],
    'edit_relationship'  => ['leads-to-action','follows-action','self-contained','bridge'],
    'structure_type'     => ['linear','episodic','non-linear','circular','surreal'],
    'fabula_position'    => ['early','mid','late','ambiguous'],
    'syuzhet_position'   => ['early','mid','late','ambiguous'],
    'character_presence' => ['none','background','featured','protagonist'],
    'world_specificity'  => ['world-locked','world-flavored','universal'],
];

// Bitmask maps
$NF_BITS = [
    'REVELATION'       => 1,
    'DECISION'         => 2,
    'ESCALATION'       => 4,
    'REVERSAL'         => 8,
    'BREATHER'         => 16,
    'MIRROR'           => 32,
    'COMPLICATION'     => 64,
    'FALSE_RESOLUTION' => 128,
    'FORM_BEAT'        => 256,
    'COMIC_RELIEF'     => 512,
];
$LAYER_BITS = [
    'plot'      => 1,
    'character' => 2,
    'world'     => 4,
    'theme'     => 8,
];

// -------------------------------------------------------
// INIT
// -------------------------------------------------------
$em     = $spw->getEntityManager();
$repo   = $em->getRepository(GeneratorConfig::class);
$config = $repo->findOneBy(['configId' => $curatorConfigId]);

if (!$config) die("Error: Generator config '$curatorConfigId' not found in DB.\n");

$aiProvider = $spw->getAIProvider();
$service    = new GeneratorService(
    $aiProvider,
    new SchemaValidator(),
    new ResponseNormalizer(),
    $spw->getFileLogger()
);

echo "--- 🎬 SEQUENCE CURATOR STARTED ---\n";
echo "Config: $curatorConfigId | Batch: $limit\n\n";

// -------------------------------------------------------
// FIND SKETCHES: have sketch_analysis but no sequence profile
// -------------------------------------------------------
$sql = "
    SELECT
        s.id,
        s.name,
        s.description,
        sa.entities,
        sa.classification,
        sa.scoring,
        sa.thematics,
        sa.recommendations,
        sa.overall_quality
    FROM sketches s
    INNER JOIN sketch_analysis sa ON s.id = sa.sketch_id
    LEFT JOIN  sketch_sequence_analysis ssa ON s.id = ssa.sketch_id
    WHERE ssa.id IS NULL 
    ORDER BY s.id DESC
    LIMIT :limit
";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$sketches = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($sketches)) die("✅ All sketches already have a sequence profile.\n");

echo "Found " . count($sketches) . " sketches to profile.\n\n";

// -------------------------------------------------------
// PROCESS
// -------------------------------------------------------
foreach ($sketches as $sketch) {
    $id = $sketch['id'];
    echo "Processing Sketch #$id: " . substr((string)($sketch['name'] ?? ''), 0, 35) . "... ";

    // Decode existing curation
    $entities        = json_decode($sketch['entities']        ?? '{}', true) ?: [];
    $classification  = json_decode($sketch['classification']  ?? '{}', true) ?: [];
    $scoring         = json_decode($sketch['scoring']         ?? '{}', true) ?: [];
    $thematics       = json_decode($sketch['thematics']       ?? '{}', true) ?: [];
    $recommendations = json_decode($sketch['recommendations'] ?? '{}', true) ?: [];

    // Build flat input text — same pattern as cli_auto_curator.php
    $characters = spw_flatten_prompt_value($entities['characters'] ?? []);
    $locations   = spw_flatten_prompt_value($entities['locations']  ?? []);
    $artifacts   = spw_flatten_prompt_value($entities['artifacts']  ?? []);
    $themes      = spw_flatten_prompt_value($thematics['primary_themes'] ?? []);

    $promptInput  = "Name: " . spw_flatten_prompt_value($sketch['name'] ?? '') . "\n";
    $promptInput .= "Description: " . spw_flatten_prompt_value($sketch['description'] ?? '') . "\n";

    if ($characters !== '') $promptInput .= "Characters: $characters\n";
    if ($locations !== '')  $promptInput .= "Locations: $locations\n";
    if ($artifacts !== '')  $promptInput .= "Artifacts: $artifacts\n";

    spw_prompt_append($promptInput, "Narrative Function (existing): ", $classification['narrative_function'] ?? null);
    spw_prompt_append($promptInput, "Emotional Tone: ", $classification['emotional_tone'] ?? null);
    spw_prompt_append($promptInput, "Visual Style: ", $classification['visual_style'] ?? null, 300);

    if ($themes !== '') {
        $promptInput .= "Themes: $themes\n";
    }

    spw_prompt_append($promptInput, "Symbolic Meaning: ", $thematics['symbolic_meaning'] ?? null, 200);
    spw_prompt_append($promptInput, "Potential Use: ", $recommendations['potential_use'] ?? null, 200);

    if (!empty($scoring['narrative_completeness'])) {
        $promptInput .= "Narrative Completeness: " . spw_flatten_prompt_value($scoring['narrative_completeness']) . "/10\n";
    }
    if (!empty($scoring['visual_impact'])) {
        $promptInput .= "Visual Impact: " . spw_flatten_prompt_value($scoring['visual_impact']) . "/10\n";
    }

    try {
        // Call AI
        $result      = $service->generate($config, ['entity_name' => $promptInput]);
        $rawResponse = $result->getRawResponse();

        // JSON repair — same pattern as original curator
        $jsonStr = $rawResponse;
        if (preg_match('/```json\s*(.*?)\s*```/s', $rawResponse, $m)) {
            $jsonStr = $m[1];
        } elseif (preg_match('/\{.*\}/s', $rawResponse, $m)) {
            $jsonStr = $m[0];
        }

        $data = json_decode($jsonStr, true);
        if (!$data && $result->isSuccess()) {
            $data = $result->getData();
        }

        if (!$data || !is_array($data)) {
            echo "\033[31m[FAIL] Invalid JSON\033[0m\n";
            echo "    -> Raw: " . substr((string)$rawResponse, 0, 300) . "\n";
            continue;
        }

        // -------------------------------------------------------
        // VALIDATE & SANITIZE enum fields
        // -------------------------------------------------------
        $valid = true;

        // Multi-value arrays
        foreach (['narrative_function', 'layer'] as $field) {
            if (empty($data[$field]) || !is_array($data[$field])) {
                echo "\033[31m[FAIL] Missing or invalid field: $field\033[0m\n";
                $valid = false;
                break;
            }
            // Filter to allowed values only
            $data[$field] = array_values(array_filter(
                array_slice($data[$field], 0, 3),
                fn($v) => in_array($v, $ENUMS[$field], true)
            ));
            if (empty($data[$field])) {
                echo "\033[31m[FAIL] No valid enum values for: $field\033[0m\n";
                $valid = false;
                break;
            }
        }
        if (!$valid) continue;

        // Single-value enum fields — fall back to safe default if invalid
        $singleFields = ['energy','position','standalone','intensity','shot_scale',
                         'edit_relationship','structure_type','fabula_position',
                         'syuzhet_position','character_presence','world_specificity'];
        foreach ($singleFields as $field) {
            $val = $data[$field] ?? null;
            if (!$val || !in_array($val, $ENUMS[$field], true)) {
                // Assign first allowed value as safe default and lower confidence
                $data[$field] = $ENUMS[$field][0];
                $data['confidence'] = min((float)($data['confidence'] ?? 0.5), 0.5);
            }
        }

        // -------------------------------------------------------
        // COMPUTE BITMASKS
        // -------------------------------------------------------
        $nfMask = 0;
        foreach ($data['narrative_function'] as $nf) {
            $nfMask |= ($NF_BITS[$nf] ?? 0);
        }

        $layerMask = 0;
        foreach ($data['layer'] as $l) {
            $layerMask |= ($LAYER_BITS[$l] ?? 0);
        }

        // -------------------------------------------------------
        // SAVE
        // -------------------------------------------------------
        $ins = $pdo->prepare("
            INSERT INTO sketch_sequence_analysis (
                sketch_id,
                narrative_function, layer,
                energy, position, standalone, intensity,
                shot_scale, edit_relationship, structure_type,
                fabula_position, syuzhet_position,
                character_presence, world_specificity,
                narrative_function_mask, layer_mask,
                short_logline, connective_hint,
                confidence, generator_config_id
            ) VALUES (
                :sketch_id,
                :narrative_function, :layer,
                :energy, :position, :standalone, :intensity,
                :shot_scale, :edit_relationship, :structure_type,
                :fabula_position, :syuzhet_position,
                :character_presence, :world_specificity,
                :nf_mask, :layer_mask,
                :short_logline, :connective_hint,
                :confidence, :config_id
            )
            ON DUPLICATE KEY UPDATE
                narrative_function       = VALUES(narrative_function),
                layer                    = VALUES(layer),
                energy                   = VALUES(energy),
                position                 = VALUES(position),
                standalone               = VALUES(standalone),
                intensity                = VALUES(intensity),
                shot_scale               = VALUES(shot_scale),
                edit_relationship        = VALUES(edit_relationship),
                structure_type           = VALUES(structure_type),
                fabula_position          = VALUES(fabula_position),
                syuzhet_position         = VALUES(syuzhet_position),
                character_presence       = VALUES(character_presence),
                world_specificity        = VALUES(world_specificity),
                narrative_function_mask  = VALUES(narrative_function_mask),
                layer_mask               = VALUES(layer_mask),
                short_logline            = VALUES(short_logline),
                connective_hint          = VALUES(connective_hint),
                confidence               = VALUES(confidence),
                generator_config_id      = VALUES(generator_config_id),
                updated_at               = NOW()
        ");

        $ins->execute([
            ':sketch_id'          => $id,
            ':narrative_function' => json_encode($data['narrative_function']),
            ':layer'              => json_encode($data['layer']),
            ':energy'             => $data['energy'],
            ':position'           => $data['position'],
            ':standalone'         => $data['standalone'],
            ':intensity'          => $data['intensity'],
            ':shot_scale'         => $data['shot_scale'],
            ':edit_relationship'  => $data['edit_relationship'],
            ':structure_type'     => $data['structure_type'],
            ':fabula_position'    => $data['fabula_position'],
            ':syuzhet_position'   => $data['syuzhet_position'],
            ':character_presence' => $data['character_presence'],
            ':world_specificity'  => $data['world_specificity'],
            ':nf_mask'            => $nfMask,
            ':layer_mask'         => $layerMask,
            ':short_logline'      => substr(spw_flatten_prompt_value($data['short_logline'] ?? ''), 0, 200),
            ':connective_hint'    => substr(spw_flatten_prompt_value($data['connective_hint'] ?? ''), 0, 240),
            ':confidence'         => round((float)($data['confidence'] ?? 0.5), 3),
            ':config_id'          => $config->getId(),
        ]);

        $conf = round((float)($data['confidence'] ?? 0), 2);
        $nfs  = implode('+', $data['narrative_function']);
        $confColor = $conf >= 0.8 ? "\033[32m" : ($conf >= 0.6 ? "\033[33m" : "\033[31m");
        echo "{$confColor}[DONE] $nfs | " . $data['energy'] . " | " . $data['intensity'] . " | conf:{$conf}\033[0m\n";

        sleep(1);

    } catch (Exception $e) {
        echo "\033[31m[ERROR] " . $e->getMessage() . "\033[0m\n";
    }
}

echo "\n--- Batch Complete ---\n";
?>
