<?php
// Lore -> Sketch Generator Runner
// - CLI-first
// - Accepts JSON config via --config=file.json or --config-json='...'
// - NEW: Accepts --qjobs=X to pull from database queue (forge_jobs)
// - Falls back to interactive prompts only when no config is provided

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Service/LoreAccessService.php';
require_once __DIR__ . '/../src/Core/AIProvider.php';

use App\Service\LoreAccessService;
use App\Core\AIProvider;

/* ──────────────────────────────────────────────────────────────────────────
   Utilities
────────────────────────────────────────────────────────────────────────── */

function input(string $prompt = ''): string {
    echo $prompt;
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    return trim((string)$line);
}

function printHeader(string $text): void {
    echo "\n" . str_repeat("=", 60) . "\n";
    echo " " . strtoupper($text) . "\n";
    echo str_repeat("=", 60) . "\n";
}

function printRow(int $id, string $label, string $info = ''): void {
    echo sprintf("[%d] %-30s %s\n", $id, substr($label, 0, 30), $info);
}

function isInteractiveCli(): bool {
    return function_exists('posix_isatty') ? @posix_isatty(STDIN) : true;
}

function parseBool($value): bool {
    if (is_bool($value)) return $value;
    $v = strtolower(trim((string)$value));
    return in_array($v, ['1', 'true', 'yes', 'y', 'on'], true);
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

function normalizeTags($tags): array {
    if (is_string($tags)) {
        $tags = array_map('trim', explode(',', $tags));
    }
    if (!is_array($tags)) return [];
    $tags = array_values(array_filter(array_map('trim', $tags), fn($v) => $v !== ''));
    return array_values(array_unique($tags));
}

// Helper to clean AI output (Markdown code blocks or JSON)
function cleanAIOutput(string $text, ?string $schemaKey = null): string {
    $originalText = trim($text);

    $jsonStr = $originalText;
    if (preg_match('/```(?:json)?\s*(.*?)\s*```/is', $originalText, $matches)) {
        $jsonStr = $matches[1];
    } elseif (preg_match('/\{.*\}/is', $originalText, $matches)) {
        $jsonStr = $matches[0];
    }

    $json = json_decode($jsonStr, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($json)) {
        if ($schemaKey && isset($json[$schemaKey])) return trim((string)$json[$schemaKey]);
        foreach (['enriched_query', 'description', 'content', 'text', 'sketch', 'name'] as $key) {
            if (isset($json[$key])) return trim((string)$json[$key]);
        }
        if (array_is_list($json)) return trim(implode("\n", $json));
        return trim(implode("\n", array_values($json)));
    }

    $cleaned = $originalText;
    $cleaned = preg_replace('/^\s*(?:```json\s*)?\{\s*"[^"]+"\s*:\s*"/is', '', $cleaned);
    $cleaned = preg_replace('/"\s*\}\s*(?:```\s*)?$/is', '', $cleaned);
    $cleaned = preg_replace('/^\s*```(?:json)?\s*/is', '', $cleaned);
    $cleaned = preg_replace('/\s*```\s*$/is', '', $cleaned);

    $cleaned = str_replace('\"', '"', $cleaned);
    $cleaned = str_replace('\n', "\n", $cleaned);
    $cleaned = str_replace('\\\\', '\\', $cleaned);

    return trim($cleaned);
}

function resolveUniqueSketchName($conn, string $name): string {
    $baseName = substr($name, 0, 100);
    $uniqueName = $baseName;
    $counter = 1;

    while ($conn->fetchOne("SELECT id FROM sketches WHERE name = ?", [$uniqueName])) {
        $counter++;
        $suffix = " ($counter)";
        $maxBaseLen = 100 - strlen($suffix);
        $uniqueName = substr($baseName, 0, $maxBaseLen) . $suffix;
    }

    return $uniqueName;
}

function loadRuntimeConfig(): array {
    $opts = getopt('', [
        'config:',
        'config-json::',
        'dry-run::',
    ]);

    $cfg = [
        'doc_id' => null,
        'group_key' => '',
        'offset' => 0,
        'amount' => null,
        'generator_config_id' => null,
        'tags' => [],
        'confirm' => false,
        'delay_us' => 500000,
        'dry_run' => false,

        '_source' => 'interactive', // interactive | file | inline
        '_strict' => false,         // true when JSON config is provided
    ];

    if (isset($opts['config'])) {
        $fileCfg = readJsonFile($opts['config']);
        $cfg = array_merge($cfg, $fileCfg);
        $cfg['_source'] = 'file';
        $cfg['_strict'] = true;
    } elseif (array_key_exists('config-json', $opts) && is_string($opts['config-json']) && trim($opts['config-json']) !== '') {
        $jsonCfg = json_decode($opts['config-json'], true);
        if (!is_array($jsonCfg)) {
            throw new RuntimeException("Invalid JSON passed to --config-json.");
        }
        $cfg = array_merge($cfg, $jsonCfg);
        $cfg['_source'] = 'inline';
        $cfg['_strict'] = true;
    }

    if (isset($opts['dry-run'])) {
        $cfg['dry_run'] = parseBool($opts['dry-run']);
    }

    return $cfg;
}

function getAvailableGroups(LoreAccessService $loreService, array $categories): array {
    $availableCats = [];
    $storyEngine = $loreService->getStoryEngine();

    foreach ($categories as $cat) {
        $count = 0;
        if (in_array($cat, ['episodes', 'scene_hooks'], true)) {
            $count = count($storyEngine[$cat] ?? []);
        } else {
            $items = $loreService->queryEntities($cat);
            $count = count($items);
        }

        if ($count > 0) {
            $availableCats[] = ['key' => $cat, 'count' => $count];
        }
    }

    return $availableCats;
}

function buildEntityList(LoreAccessService $loreService, string $catKey): array {
    $storyEngine = $loreService->getStoryEngine();

    if (in_array($catKey, ['episodes', 'scene_hooks'], true)) {
        return $storyEngine[$catKey] ?? [];
    }

    return $loreService->queryEntities($catKey);
}

/* ──────────────────────────────────────────────────────────────────────────
   Main
────────────────────────────────────────────────────────────────────────── */

try {
    global $spw;

    $conn = $spw->getEntityManager()->getConnection();
    $ai = new AIProvider();
    $loreService = new LoreAccessService($spw->getPdo());

    $optsOuter = getopt('', ['qjobs::']);
    $qjobs = isset($optsOuter['qjobs']) ? (int)$optsOuter['qjobs'] : 0;
    
    $jobsToProcess = [];
    if ($qjobs > 0) {
        $jobsToProcess = $conn->fetchAllAssociative("SELECT * FROM forge_jobs WHERE job_type = 'lore_sketch' AND status = 'pending' ORDER BY priority ASC, id ASC LIMIT " . $qjobs);
        if (empty($jobsToProcess)) {
            echo "No pending lore_sketch jobs found.\n";
            exit(0);
        }
    } else {
        $jobsToProcess[] = ['id' => null, 'payload' => null];
    }

    foreach ($jobsToProcess as $jobRow) {
        $jobId = $jobRow['id'] ?? null;
        if ($jobId) {
            $conn->executeStatement("UPDATE forge_jobs SET status = 'processing', started_at = NOW() WHERE id = ?", [$jobId]);
            $cfg = json_decode($jobRow['payload'], true) ?: [];
            $cfg['_strict'] = true;
            $cfg['_source'] = 'queue';
            echo "\n=== RUNNING QUEUE JOB #$jobId (lore_sketch) ===\n";
        } else {
            $cfg = loadRuntimeConfig();
        }

        try {
            printHeader("Lore -> Sketch Auto-Generator");

            $strict = (bool)($cfg['_strict'] ?? false);
            $interactive = !$strict && isInteractiveCli();

            // 1. SELECT CONTEXT DOCUMENT
            // --------------------------------------------------------
            $docs = $conn->fetchAllAssociative("
                SELECT d.id, d.name, d.keywords, c.name as cat_name 
                FROM documentations d 
                JOIN md_doc_analysis da ON d.id = da.doc_id 
                LEFT JOIN documentation_categories c ON d.category_id = c.id
                WHERE d.is_active = 1
                ORDER BY d.updated_at DESC
            ");

            if (empty($docs)) throw new RuntimeException("No analyzed documentation found.");

            if ($interactive) {
                echo "Available Lore Contexts:\n";
                foreach ($docs as $i => $d) {
                    printRow($i + 1, (string)$d['name'], "(" . ($d['cat_name'] ?? 'Uncategorized') . ")");
                }
            }

            if (empty($cfg['doc_id'])) {
                if ($strict) {
                    throw new RuntimeException("doc_id is required in JSON/DB mode.");
                }
                $docIndex = (int)input("\nSelect Document Number: ") - 1;
                if (!isset($docs[$docIndex])) throw new RuntimeException("Invalid selection.");
                $cfg['doc_id'] = (int)$docs[$docIndex]['id'];
            }

            $docId = (int)$cfg['doc_id'];
            $selectedDoc = null;
            foreach ($docs as $d) {
                if ((int)$d['id'] === $docId) {
                    $selectedDoc = $d;
                    break;
                }
            }
            if (!$selectedDoc) {
                throw new RuntimeException("Selected document not found: {$docId}");
            }

            echo "Loading context for: " . $selectedDoc['name'] . "...\n";
            $loreService->loadDoc($selectedDoc['id']);

            // 2. SELECT CATEGORY / GROUP
            // --------------------------------------------------------
            $categories = ['characters', 'locations', 'factions', 'artifacts', 'episodes', 'scene_hooks'];
            $availableCats = getAvailableGroups($loreService, $categories);

            if (empty($availableCats)) throw new RuntimeException("No entities found in this document.");

            if ($interactive) {
                printHeader("Select Entity Group");
                foreach ($availableCats as $i => $c) {
                    printRow($i + 1, ucfirst((string)$c['key']), "Count: " . (int)$c['count']);
                }
            }

            if (empty($cfg['group_key'])) {
                if ($strict) {
                    throw new RuntimeException("group_key is required in JSON/DB mode.");
                }
                $catIndex = (int)input("\nSelect Group Number: ") - 1;
                if (!isset($availableCats[$catIndex])) throw new RuntimeException("Invalid selection.");
                $cfg['group_key'] = $availableCats[$catIndex]['key'];
            }

            $catKey = trim((string)$cfg['group_key']);
            $validKeys = array_column($availableCats, 'key');
            if (!in_array($catKey, $validKeys, true)) {
                if ($strict) {
                    throw new RuntimeException("group_key '{$catKey}' is not available in the selected document.");
                }
                throw new RuntimeException("Invalid group selection.");
            }

            // 3. DEFINE RANGE
            // --------------------------------------------------------
            $entityList = buildEntityList($loreService, $catKey);

            $total = count($entityList);
            echo "\nGroup '{$catKey}' has {$total} items.\n";

            if (empty($cfg['offset']) && $cfg['offset'] !== 0) {
                $cfg['offset'] = 0;
            }
            $offset = max(0, (int)$cfg['offset']);

            if ($strict) {
                $amountInput = $cfg['amount'];
                $amount = ($amountInput === '' || $amountInput === null) ? ($total - $offset) : (int)$amountInput;
                if ($amount <= 0 || $amount > ($total - $offset)) {
                    $amount = $total - $offset;
                }
            } else {
                $amountInput = input("Amount to process (default All): ");
                $amount = ($amountInput === '') ? ($total - $offset) : (int)$amountInput;
                if ($amount <= 0 || $amount > ($total - $offset)) {
                    $amount = $total - $offset;
                }
            }

            $processList = array_slice($entityList, $offset, $amount);
            echo "\nQueue: Processing " . count($processList) . " items (from index $offset).\n";

            // 4. SELECT GENERATOR CONFIG
            // --------------------------------------------------------
            $configs = $conn->fetchAllAssociative("SELECT config_id, title, output_schema FROM generator_config WHERE active = 1 ORDER BY title ASC");

            if ($interactive) {
                printHeader("Select Generator Config");
                foreach ($configs as $i => $c) {
                    printRow($i + 1, (string)$c['title']);
                }
            }

            if (empty($cfg['generator_config_id'])) {
                if ($strict) {
                    throw new RuntimeException("generator_config_id is required in JSON/DB mode.");
                }
                $cfgIndex = (int)input("\nSelect Config Number: ") - 1;
                if (!isset($configs[$cfgIndex])) throw new RuntimeException("Invalid selection.");
                $cfg['generator_config_id'] = $configs[$cfgIndex]['config_id'];
            }

            $genConfigId = (string)$cfg['generator_config_id'];
            $genConfigRow = null;
            foreach ($configs as $c) {
                if ((string)$c['config_id'] === $genConfigId) {
                    $genConfigRow = $c;
                    break;
                }
            }
            if (!$genConfigRow) {
                throw new RuntimeException("Generator config not found in active list: {$genConfigId}");
            }

            $targetSchemaKey = null;
            $schema = json_decode($genConfigRow['output_schema'] ?? '{}', true);
            if (isset($schema['required'][0])) {
                $targetSchemaKey = $schema['required'][0];
            }

            $genConfigFull = $conn->fetchAssociative("SELECT * FROM generator_config WHERE config_id = ?", [$genConfigId]);
            if (!$genConfigFull) {
                throw new RuntimeException("Full generator config not found: {$genConfigId}");
            }

            $instructionsArr = json_decode($genConfigFull['instructions'] ?? '[]', true);
            if (!is_array($instructionsArr)) $instructionsArr = [];
            $systemPrompt = trim((string)($genConfigFull['system_role'] ?? '')) . "\n\n" . implode("\n", $instructionsArr);
            $model = (!empty($genConfigFull['model']) && $genConfigFull['model'] !== 'openai')
                ? (string)$genConfigFull['model']
                : AIProvider::getDefaultModel();

            // 5. TAGS SELECTION
            // --------------------------------------------------------
            $defaultKeywords = trim((string)($selectedDoc['keywords'] ?? ''));
            $tags = normalizeTags($cfg['tags'] ?? []);

            if (empty($tags)) {
                $tags = normalizeTags($defaultKeywords);
            }

            $finalTagIds = [];
            if (!empty($tags)) {
                echo "\nResolving tags...\n";
                foreach ($tags as $tagName) {
                    $tagId = $conn->fetchOne("SELECT id FROM tags WHERE name = ?", [$tagName]);

                    if (!$tagId) {
                        echo "- Creating new tag: [$tagName]\n";
                        $conn->insert('tags', [
                            'name' => $tagName,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                            'show_in_ui' => 1
                        ]);
                        $tagId = $conn->lastInsertId();
                    } else {
                        echo "- Found existing tag: [$tagName]\n";
                    }

                    $finalTagIds[] = (int)$tagId;
                }
            }
            echo "Mapped " . count($finalTagIds) . " tags for this batch.\n";

            // 6. PROCESSING LOOP
            // --------------------------------------------------------
            printHeader("Starting Generation...");

            if ($strict) {
                if (!parseBool($cfg['confirm'])) {
                    throw new RuntimeException("confirm must be true in JSON/DB mode.");
                }
            } else {
                $confirm = input("Ready to generate " . count($processList) . " sketches? [y/N]: ");
                if (strtolower($confirm) !== 'y') throw new RuntimeException("Aborted.");
            }

            $count = 0;
            $sqlSketch = "INSERT INTO sketches 
                (`name`, `description`, `created_at`, `updated_at`, `order`, `regenerate_images`, `img2img`, `cnmap`) 
                VALUES (:name, :desc, :created, :updated, 0, 0, 0, 0)";

            foreach ($processList as $index => $item) {
                $count++;

                $name = '';
                if (is_array($item)) {
                    $name = $item['name'] ?? ($item['title'] ?? ($item['event'] ?? 'Unknown'));
                } else {
                    $name = (string)$item;
                }

                // --- UNIQUE NAME GENERATION ---
                $baseName = substr($name, 0, 100);
                $uniqueName = $baseName;
                $counter = 1;

                while ($conn->fetchOne("SELECT id FROM sketches WHERE name = ?", [$uniqueName])) {
                    $counter++;
                    $suffix = " ($counter)";
                    $maxBaseLen = 100 - strlen($suffix);
                    $uniqueName = substr($baseName, 0, $maxBaseLen) . $suffix;
                }

                echo "\n[$count/" . count($processList) . "] Processing: $uniqueName ... ";

                // A. Build Context
                $contextData = $loreService->buildAgentContext($name);
                $contextStr = "";

                if ($contextData) {
                    $contextStr = "IDENTITY:\n" . json_encode($contextData['identity'], JSON_UNESCAPED_UNICODE) . "\n";
                    $contextStr .= "ATTRIBUTES:\n" . json_encode($contextData['identity']['core_attributes'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
                } else {
                    $contextStr = "DETAILS:\n" . json_encode($item, JSON_UNESCAPED_UNICODE);
                }

                // B. Generate
                $userPrompt = "Please generate a rich, visual sketch description for the following entity.\n\nENTITY: $name\nTYPE: $catKey\n\nCONTEXT:\n$contextStr";

                try {
                    if (!empty($cfg['dry_run'])) {
                        echo "DRY RUN\n";
                        continue;
                    }

                    $rawOutput = $ai->sendPrompt($model, $userPrompt, $systemPrompt, ['temperature' => 0.7]);

                    if (empty($rawOutput)) {
                        echo "Failed (Empty API Response).\n";
                        continue;
                    }

                    $finalText = cleanAIOutput((string)$rawOutput, $targetSchemaKey);

                    // C. Insert Sketch
                    $conn->executeStatement($sqlSketch, [
                        'name' => $uniqueName,
                        'desc' => $finalText,
                        'created' => date('Y-m-d H:i:s'),
                        'updated' => date('Y-m-d H:i:s')
                    ]);
                    $newSketchId = $conn->lastInsertId();

                    // D. Insert History
                    $conn->insert('sketch_lore_history', [
                        'sketch_id' => $newSketchId,
                        'doc_id' => $selectedDoc['id'],
                        'entity_type' => $catKey,
                        'entity_name' => substr($name, 0, 255),
                        'generator_config_id' => $genConfigFull['id'] ?? 0,
                        'prompt_used' => substr($userPrompt, 0, 2000),
                        'created_at' => date('Y-m-d H:i:s')
                    ]);

                    // E. Link Tags
                    foreach ($finalTagIds as $tid) {
                        $conn->executeStatement(
                            "INSERT IGNORE INTO tags_2_sketches (from_id, to_id) VALUES (?, ?)",
                            [$tid, $newSketchId]
                        );
                    }

                    echo "Done (ID: $newSketchId)";
                } catch (Throwable $e) {
                    echo "Error: " . $e->getMessage();
                }

                usleep((int)($cfg['delay_us'] ?? 500000));
            }

            printHeader("Batch Complete");
            echo "Generated $count sketches.\n";

            if ($jobId) {
                $conn->executeStatement("UPDATE forge_jobs SET status = 'done', finished_at = NOW() WHERE id = ?", [$jobId]);
            }
        } catch (Throwable $jobEx) {
            echo "\nJOB ERROR: " . $jobEx->getMessage() . "\n";
            if ($jobId) {
                $conn->executeStatement("UPDATE forge_jobs SET status = 'failed', error_msg = ?, finished_at = NOW() WHERE id = ?", [substr($jobEx->getMessage(), 0, 5000), $jobId]);
            } else {
                throw $jobEx;
            }
        }
    } // end foreach jobs
} catch (Throwable $e) {
    echo "\nCRITICAL ERROR: " . $e->getMessage() . "\n";
    if (isset($conn) && method_exists($conn, 'close')) {
        $conn->close();
    }
    exit(1);
}
