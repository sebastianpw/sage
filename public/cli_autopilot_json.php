<?php
// public/cli_autopilot_json.php
// Infinite Creative Autopilot for Sketches
// Usage:
//   Interactive: php public/cli_autopilot_json.php
//   Batch:       php public/cli_autopilot_json.php [GenID] [Amount]
//   JSON:        php public/cli_autopilot_json.php --config=job.json
//   JSON inline: php public/cli_autopilot_json.php --config-json='{"desc_gen_id":12,"amount":5,...}'

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

// --- DEFAULTS ---
const DEFAULT_NAME_GEN_HASH = '9bf6de291765e2ced28589de857a9f0b';

// ANSI Colors
const C_RESET   = "\033[0m";
const C_RED     = "\033[31m";
const C_GREEN   = "\033[32m";
const C_YELLOW  = "\033[33m";
const C_BLUE    = "\033[34m";
const C_CYAN    = "\033[36m";
const C_GRAY    = "\033[90m";
const C_WHITE   = "\033[1m";

// --- INGREDIENT DEFAULTS ---
$ingredientsConfig = [
    'template'    => ['Sketch Templates', 85],
    'interaction' => ['Interactions',     60],
    'style'       => ['Style Profiles',   50],
    'anivoc'      => ['Anime Visual Vocab', 70],
    'character'   => ['Characters',       40],
    'location'    => ['Locations',        30],
    'vehicle'     => ['Vehicles',         20],
    'artifact'    => ['Artifacts',        15],
];

// --- INIT ---
$em = $spw->getEntityManager();
$conn = $em->getConnection();
$repo = $em->getRepository(GeneratorConfig::class);

$logger = $spw->getFileLogger();
$aiProvider = $spw->getAIProvider();
if (!$aiProvider) {
    $aiProvider = new AIProvider($logger);
}

$generatorService = new GeneratorService(
    $aiProvider,
    new SchemaValidator(),
    new ResponseNormalizer(),
    $logger
);

function input(string $prompt = ''): string {
    echo $prompt;
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    return trim((string)$line);
}

function isInteractiveCli(): bool {
    return function_exists('posix_isatty') ? @posix_isatty(STDIN) : true;
}

function parseBool($value): bool {
    if (is_bool($value)) return $value;
    $v = strtolower(trim((string)$value));
    return in_array($v, ['1', 'true', 'yes', 'y', 'on'], true);
}

function printHeader(string $text): void {
    echo "\n" . str_repeat("=", 42) . "\n";
    echo "   " . $text . "\n";
    echo str_repeat("=", 42) . "\n";
}

function readJsonFile(string $path): array {
    if (!is_file($path)) {
        throw new RuntimeException("Config file not found: {$path}");
    }
    $raw = file_get_contents($path);
    $data = json_decode($raw ?: '', true);
    if (!is_array($data)) {
        throw new RuntimeException("Invalid JSON in config file: {$path}");
    }
    return $data;
}

function normalizeProbabilityConfig(array $input, array $defaults): array {
    $out = [];
    foreach ($defaults as $key => $defaultValue) {
        $val = $input[$key] ?? $defaultValue;
        $out[$key] = max(0, min(100, (int)$val));
    }
    return $out;
}

function getRapidGenerators($conn): array {
    $sql = "
        SELECT g.id, g.title, g.model
        FROM generator_config g
        JOIN generator_config_to_display_area map ON g.id = map.generator_config_id
        JOIN generator_config_display_area da ON map.display_area_id = da.id
        WHERE da.area_key = 'rapidcreate' AND g.active = 1
        ORDER BY g.title ASC
    ";
    return $conn->fetchAllAssociative($sql) ?: [];
}

function ensureConfigRevision($conn, GeneratorConfig $config): ?array {
    if (!$config) return null;

    $snapshot = [
        'system_role'   => $config->getSystemRole(),
        'instructions'  => $config->getInstructions(),
        'parameters'    => $config->getParameters(),
        'output_schema' => $config->getOutputSchema(),
        'oracle_config' => $config->getOracleConfig(),
        'model'         => $config->getModel()
    ];

    $jsonSnapshot = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $hash = md5($jsonSnapshot);

    $hStmt = $conn->prepare("SELECT id FROM generator_config_history WHERE generator_config_id = ? AND config_hash = ?");
    $hStmt->bindValue(1, $config->getId());
    $hStmt->bindValue(2, $hash);
    $existing = $hStmt->executeQuery()->fetchOne();

    if ($existing) {
        return ['db_id' => $config->getId(), 'history_id' => $existing];
    }

    $iStmt = $conn->prepare("INSERT INTO generator_config_history (generator_config_id, config_hash, snapshot_data, created_at) VALUES (?, ?, ?, NOW())");
    $iStmt->bindValue(1, $config->getId());
    $iStmt->bindValue(2, $hash);
    $iStmt->bindValue(3, $jsonSnapshot);
    $iStmt->executeStatement();

    return ['db_id' => $config->getId(), 'history_id' => $conn->lastInsertId()];
}

function fetchRandomRow($conn, string $table): ?array {
    $row = $conn->fetchAssociative("SELECT * FROM `$table` ORDER BY RAND() LIMIT 1");
    return $row ?: null;
}

function loadRuntimeConfig(array $argv, array $ingredientsConfig): array {
    $opts = getopt('', [
        'config:',
        'config-json::',
        'dry-run::',
    ]);

    $defaults = [];
    foreach ($ingredientsConfig as $key => $conf) {
        $defaults[$key] = (int)$conf[1];
    }

    $cfg = [
        'desc_gen_id' => null,
        'amount' => 0,
        'probabilities' => $defaults,
        'dry_run' => false,

        '_source' => 'interactive', // interactive | positional | file | inline
        '_strict' => false,         // true only for JSON modes
    ];

    if (isset($opts['config'])) {
        $fileCfg = readJsonFile($opts['config']);
        $cfg = array_replace_recursive($cfg, $fileCfg);
        $cfg['_source'] = 'file';
        $cfg['_strict'] = true;
    } elseif (array_key_exists('config-json', $opts) && is_string($opts['config-json']) && trim($opts['config-json']) !== '') {
        $jsonCfg = json_decode($opts['config-json'], true);
        if (!is_array($jsonCfg)) {
            throw new RuntimeException("Invalid JSON passed to --config-json.");
        }
        $cfg = array_replace_recursive($cfg, $jsonCfg);
        $cfg['_source'] = 'inline';
        $cfg['_strict'] = true;
    } else {
        $args = array_slice($argv, 1);
        if (!empty($args)) {
            $cfg['_source'] = 'positional';
            $cfg['desc_gen_id'] = isset($args[0]) ? (int)$args[0] : null;
            $cfg['amount'] = isset($args[1]) ? (int)$args[1] : 0;
            $cfg['probabilities'] = $defaults;
        }
    }

    if (isset($opts['dry-run'])) {
        $cfg['dry_run'] = parseBool($opts['dry-run']);
    }

    $cfg['probabilities'] = normalizeProbabilityConfig(
        is_array($cfg['probabilities'] ?? null) ? $cfg['probabilities'] : [],
        $defaults
    );

    return $cfg;
}

try {
    $cfg = loadRuntimeConfig($argv, $ingredientsConfig);

    $descGenId = $cfg['desc_gen_id'];
    $amount = (int)($cfg['amount'] ?? 0);
    $probs = $cfg['probabilities'];

    printHeader("Sketch Autopilot");

    $interactive = !($cfg['_strict'] ?? false) && isInteractiveCli();

    // Interactive mode only when no config and no positional batch args.
    if ($interactive && ($cfg['_source'] === 'interactive')) {
        echo "\n" . C_CYAN . "==========================================" . C_RESET . "\n";
        echo C_CYAN . "   🤖 SKETCH AUTOPILOT (Kitchen Edition)" . C_RESET . "\n";
        echo C_CYAN . "==========================================" . C_RESET . "\n\n";

        $gens = getRapidGenerators($conn);
        if (empty($gens)) {
            die(C_RED . "No generators found tagged with 'rapidcreate'.\n" . C_RESET);
        }

        echo C_WHITE . "Available Generators:" . C_RESET . "\n";
        foreach ($gens as $gen) {
            printf("  [" . C_GREEN . "%3d" . C_RESET . "] %s " . C_GRAY . "(%s)" . C_RESET . "\n", $gen['id'], $gen['title'], $gen['model']);
        }
        echo "\n";

        while (!$descGenId) {
            $input = readline("Select Generator ID: ");
            if (is_numeric($input)) {
                foreach ($gens as $g) {
                    if ((int)$g['id'] === (int)$input) {
                        $descGenId = (int)$input;
                        break;
                    }
                }
            }
            if (!$descGenId) {
                echo C_RED . "Invalid ID. Try again.\n" . C_RESET;
            }
        }

        $inputAmt = readline("How many sketches? (0 for infinite): ");
        $amount = (int)$inputAmt;

        echo "\n" . C_WHITE . "Ingredient Probabilities (Press Enter for Default):" . C_RESET . "\n";
        foreach ($ingredientsConfig as $key => $conf) {
            $label = $conf[0];
            $default = (int)$conf[1];
            $input = readline(sprintf("  %-20s [Default %3d%%]: ", $label, $default));
            if (trim($input) !== '' && is_numeric($input)) {
                $val = max(0, min(100, (int)$input));
            } else {
                $val = $default;
            }
            $probs[$key] = $val;
        }
    } else {
        if ($descGenId === null || $descGenId <= 0) {
            if (($cfg['_strict'] ?? false) === true) {
                throw new RuntimeException("desc_gen_id is required in JSON mode.");
            }
            $gens = getRapidGenerators($conn);
            if (empty($gens)) {
                die(C_RED . "No generators found tagged with 'rapidcreate'.\n" . C_RESET);
            }

            echo "\n" . C_CYAN . "🤖 SKETCH AUTOPILOT" . C_RESET . "\n";
            echo C_WHITE . "Available Generators:" . C_RESET . "\n";
            foreach ($gens as $gen) {
                printf("  [" . C_GREEN . "%3d" . C_RESET . "] %s " . C_GRAY . "(%s)" . C_RESET . "\n", $gen['id'], $gen['title'], $gen['model']);
            }
            echo "\n";

            while (!$descGenId) {
                $input = readline("Select Generator ID: ");
                if (is_numeric($input)) {
                    foreach ($gens as $g) {
                        if ((int)$g['id'] === (int)$input) {
                            $descGenId = (int)$input;
                            break;
                        }
                    }
                }
                if (!$descGenId) {
                    echo C_RED . "Invalid ID. Try again.\n" . C_RESET;
                }
            }
        }

        if ($amount < 0) {
            $amount = 0;
        }

        if (($cfg['_source'] ?? '') === 'positional') {
            // batch mode: keep defaults for ingredient probabilities
        } elseif (($cfg['_strict'] ?? false) !== true && $interactive && $amount === 0 && empty($cfg['amount'])) {
            $inputAmt = readline("How many sketches? (0 for infinite): ");
            $amount = (int)$inputAmt;

            echo "\n" . C_WHITE . "Ingredient Probabilities (Press Enter for Default):" . C_RESET . "\n";
            foreach ($ingredientsConfig as $key => $conf) {
                $label = $conf[0];
                $default = (int)$conf[1];
                $input = readline(sprintf("  %-20s [Default %3d%%]: ", $label, $default));
                if (trim($input) !== '' && is_numeric($input)) {
                    $val = max(0, min(100, (int)$input));
                } else {
                    $val = $default;
                }
                $probs[$key] = $val;
            }
        }
    }

    // Validation
    $descConfig = $repo->find($descGenId);
    if (!$descConfig) {
        die(C_RED . "Error: Generator ID $descGenId not found.\n" . C_RESET);
    }

    $nameConfig = $repo->findOneBy(['configId' => DEFAULT_NAME_GEN_HASH]);
    if (!$nameConfig) {
        die(C_RED . "Error: Default Name Generator (Hash) not found.\n" . C_RESET);
    }

    $nameGenId = $nameConfig->getId();

    echo C_GRAY . "------------------------------------------" . C_RESET . "\n";
    echo " Config:      " . C_WHITE . $descConfig->getTitle() . C_RESET . "\n";
    echo " Target:      " . C_WHITE . ($amount > 0 ? "$amount sketches" : "Infinite") . C_RESET . "\n";
    echo " Ingredients: ";
    foreach ($probs as $k => $v) {
        echo "$k=" . C_YELLOW . "$v%" . C_RESET . " ";
    }
    echo "\n" . C_GRAY . "------------------------------------------" . C_RESET . "\n\n";

    if ($amount === 0) {
        echo C_YELLOW . "Starting Infinite Loop... (Ctrl+C to stop)" . C_RESET . "\n\n";
    }

    $counter = 0;

    while (true) {
        if ($amount > 0 && $counter >= $amount) {
            echo C_GREEN . "\n✅ Target of $amount sketches reached. Exiting.\n" . C_RESET;
            break;
        }

        $counter++;
        $progressStr = ($amount > 0) ? "[$counter/$amount]" : "[$counter]";
        echo C_BLUE . "$progressStr Generating..." . C_RESET . "\n";

        try {
            // 1. Gather ingredients
            $ingredients = [];
            $contextParts = [];
            $logParts = [];

            $legacyTemplateId = null;
            $legacyInteractionId = null;

            if (rand(1, 100) <= $probs['template']) {
                $row = fetchRandomRow($conn, 'sketch_templates');
                if ($row) {
                    $legacyTemplateId = (int)$row['id'];
                    $prompt = $row['example_prompt'] . (!empty($row['core_idea']) ? " (Core: {$row['core_idea']})" : "");
                    $ingredients[] = [
                        'type' => 'sketch_template',
                        'id' => (int)$row['id'],
                        'prompt' => $prompt,
                        'snapshot' => $row
                    ];
                    $contextParts[] = "Visual Style/Template: " . $prompt;
                    $logParts[] = "Tpl";
                }
            }

            if (rand(1, 100) <= $probs['interaction']) {
                $row = fetchRandomRow($conn, 'interactions');
                if ($row) {
                    $legacyInteractionId = (int)$row['id'];
                    $prompt = $row['example_prompt'] . " ({$row['description']})";
                    $ingredients[] = [
                        'type' => 'interaction',
                        'id' => (int)$row['id'],
                        'prompt' => $prompt,
                        'snapshot' => $row
                    ];
                    $contextParts[] = "Interaction: " . $prompt;
                    $logParts[] = "Int";
                }
            }

            if (rand(1, 100) <= $probs['style']) {
                $row = $conn->fetchAssociative("SELECT * FROM style_profiles WHERE convert_result IS NOT NULL AND convert_result != '' ORDER BY RAND() LIMIT 1");
                if ($row) {
                    $prompt = "Visual Style: " . $row['convert_result'];
                    $json = json_decode($row['convert_result'], true);
                    if (json_last_error() === JSON_ERROR_NONE && !empty($json['textualStylePrompt'])) {
                        $prompt = "Visual Style: " . $json['textualStylePrompt'];
                    }

                    $ingredients[] = [
                        'type' => 'style_profile',
                        'id' => (int)$row['id'],
                        'prompt' => $prompt,
                        'snapshot' => $row
                    ];
                    $contextParts[] = $prompt;
                    $logParts[] = "Style";
                }
            }

            if (rand(1, 100) <= $probs['anivoc']) {
                $catRow = fetchRandomRow($conn, 'anivoc_categories');
                if ($catRow) {
                    $tName = $catRow['table_name'];
                    $row = fetchRandomRow($conn, $tName);
                    if ($row) {
                        $prompt = $row['name'] . " - " . strip_tags($row['description']);
                        $ingredients[] = [
                            'type' => $tName,
                            'id' => (int)$row['id'],
                            'prompt' => $prompt,
                            'snapshot' => $row
                        ];
                        $contextParts[] = "Visual Element ({$catRow['name']}): " . $prompt;
                        $logParts[] = "AniVoc";
                    }
                }
            }

            $entityTypes = [
                'character' => 'characters',
                'location'  => 'locations',
                'vehicle'   => 'vehicles',
                'artifact'  => 'artifacts'
            ];

            foreach ($entityTypes as $key => $table) {
                if (rand(1, 100) <= $probs[$key]) {
                    $row = fetchRandomRow($conn, $table);
                    if ($row) {
                        $desc = trim(strip_tags($row['description'] ?? ''));
                        $prompt = ucfirst($key) . ": " . $row['name'] . ($desc ? " - $desc" : "");
                        $ingredients[] = [
                            'type' => $table,
                            'id' => (int)$row['id'],
                            'prompt' => $prompt,
                            'snapshot' => $row
                        ];
                        $contextParts[] = $prompt;
                        $logParts[] = ucfirst($key);
                    }
                }
            }

            if (empty($contextParts)) {
                $contextParts[] = "Theme: A purely random scene.";
                $logParts[] = "Pure Random";
            }

            $finalContext = implode("\n\n", $contextParts);
            echo "    Recipe:  " . C_GRAY . implode(" + ", $logParts) . C_RESET . "\n";

            // 2. Generate description
            echo "    ⚡ Desc... ";
            $descResult = $generatorService->generate($descConfig, [
                'entity_name' => $finalContext,
                'random_seed' => rand(1, 9999999)
            ]);

            if (!$descResult->isSuccess()) {
                throw new Exception("AI Generation failed");
            }

            $descData = $descResult->getData();
            $description = is_array($descData)
                ? ($descData['description'] ?? $descData['text'] ?? json_encode($descData))
                : (string)$descData;

            echo C_GREEN . "OK" . C_RESET . "\n";

            // 3. Generate name
            echo "    ⚡ Name... ";
            $nameContext = $description;
            if (!empty($logParts)) {
                $nameContext .= " (" . implode(" + ", $logParts) . ")";
            }

            $nameResult = $generatorService->generate($nameConfig, [
                'entity_name' => $nameContext,
                'entity_type' => 'sketch',
                'random_seed' => rand(1, 9999999)
            ]);

            $nameData = $nameResult->getData();
            $name = is_array($nameData)
                ? ($nameData['name'] ?? $nameData['text'] ?? "Untitled")
                : (string)$nameData;

            $name = trim($name, '"\'');
            echo C_GREEN . "OK: $name" . C_RESET . "\n";

            // 4. Save sketch
            echo "    💾 Saving... ";
            $conn->beginTransaction();

            try {
                $sql = "INSERT INTO sketches (name, description, `order`, created_at, updated_at) VALUES (?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                $stmt->bindValue(1, $name);
                $stmt->bindValue(2, $description);
                $stmt->bindValue(3, 0);
                $now = (new DateTime())->format('Y-m-d H:i:s');
                $stmt->bindValue(4, $now);
                $stmt->bindValue(5, $now);
                $stmt->executeStatement();
                $newId = $conn->lastInsertId();

                $descRev = ensureConfigRevision($conn, $descConfig);
                $nameRev = ensureConfigRevision($conn, $nameConfig);

                $iStmt = $conn->prepare("INSERT INTO sketch_ingredients (sketch_id, ingredient_type, source_id, prompt_fragment, snapshot_data, sort_order) VALUES (?, ?, ?, ?, ?, ?)");
                $orderIdx = 0;

                $iStmt->executeStatement([$newId, 'generator_config_desc', $descGenId, 'Used for description', null, $orderIdx++]);
                $iStmt->executeStatement([$newId, 'generator_config_name', $nameGenId, 'Used for name', null, $orderIdx++]);

                foreach ($ingredients as $ing) {
                    $iStmt->executeStatement([
                        $newId,
                        $ing['type'],
                        $ing['id'],
                        $ing['prompt'],
                        json_encode($ing['snapshot'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        $orderIdx++
                    ]);
                }

                $metaSql = "INSERT INTO meta_sketches
                    (sketch_id, desc_gen_config_id, desc_gen_history_id, name_gen_config_id, name_gen_history_id, sketch_template_id, interaction_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

                $mStmt = $conn->prepare($metaSql);
                $mStmt->bindValue(1, $newId);
                $mStmt->bindValue(2, $descRev ? $descRev['db_id'] : $descGenId);
                $mStmt->bindValue(3, $descRev ? $descRev['history_id'] : null);
                $mStmt->bindValue(4, $nameRev ? $nameRev['db_id'] : $nameGenId);
                $mStmt->bindValue(5, $nameRev ? $nameRev['history_id'] : null);
                $mStmt->bindValue(6, $legacyTemplateId);
                $mStmt->bindValue(7, $legacyInteractionId);
                $mStmt->executeStatement();

                $conn->commit();
                echo C_GREEN . "Saved ID: $newId" . C_RESET . "\n";
            } catch (Throwable $saveEx) {
                $conn->rollBack();
                throw $saveEx;
            }

            if ($counter < $amount || $amount === 0) {
                echo C_GRAY . "    Cooling down (2s)..." . C_RESET . "\n\n";
                sleep(2);
            }
        } catch (Throwable $e) {
            if ($conn->isTransactionActive()) {
                $conn->rollBack();
            }
            echo C_RED . "\n    ❌ ERROR: " . $e->getMessage() . C_RESET . "\n";
            sleep(5);
        }
    }

} catch (Throwable $e) {
    echo "\nCRITICAL ERROR: " . $e->getMessage() . "\n";
    if (isset($conn) && method_exists($conn, 'close')) {
        $conn->close();
    }
    exit(1);
}