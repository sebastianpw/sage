<?php
// Knowledge Graph -> Sketch Generator Runner
// - CLI-first
// - Accepts JSON config via --config=file.json or --config-json='...'
// - NEW: Accepts --qjobs=X to pull from database queue (forge_jobs)
// - Falls back to interactive prompts only when no config is provided

if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line.\n");
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../src/Core/AIProvider.php';

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
    echo "\n" . str_repeat("=", 70) . "\n";
    echo " " . strtoupper($text) . "\n";
    echo str_repeat("=", 70) . "\n";
}

function printRow(int $id, string $label, string $info = ''): void {
    echo sprintf("[%d] %-35s %s\n", $id, substr($label, 0, 35), $info);
}

function isInteractiveCli(): bool {
    return function_exists('posix_isatty') ? @posix_isatty(STDIN) : true;
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
        if ($schemaKey && isset($json[$schemaKey])) {
            return trim((string)$json[$schemaKey]);
        }
        foreach (['enriched_query', 'description', 'content', 'text', 'sketch', 'name'] as $key) {
            if (isset($json[$key])) {
                return trim((string)$json[$key]);
            }
        }
        if (array_is_list($json)) {
            return trim(implode("\n", $json));
        }
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

function getKgCatFamily($conn, int $topCatId): array {
    if ($topCatId <= 0) return [];
    $allCats = $conn->fetchAllAssociative("SELECT id, parent_id FROM kg_categories");
    $childrenMap = [];
    foreach ($allCats as $c) {
        $parent = (int)($c['parent_id'] ?? 0);
        $childrenMap[$parent][] = (int)$c['id'];
    }
    $family = [$topCatId];
    $queue = [$topCatId];
    while (!empty($queue)) {
        $curr = array_shift($queue);
        if (isset($childrenMap[$curr])) {
            foreach ($childrenMap[$curr] as $childId) {
                $family[] = $childId;
                $queue[] = $childId;
            }
        }
    }
    return array_values(array_unique(array_map('intval', $family)));
}

function getHistCounts($conn, array $nodeNames, string $entityType): array {
    $nodeNames = array_values(array_filter(array_map('strval', $nodeNames), fn($v) => $v !== ''));
    if (empty($nodeNames)) return ['new' => 0, 'hist' => 0];

    $placeholders = implode(',', array_fill(0, count($nodeNames), '?'));
    $params = array_merge($nodeNames, [$entityType]);
    $histNames = $conn->fetchFirstColumn(
        "SELECT DISTINCT entity_name FROM sketch_lore_history WHERE entity_name IN ($placeholders) AND entity_type = ?",
        $params
    );

    $histSet = array_flip($histNames ?: []);
    $hist = 0;
    foreach ($nodeNames as $n) {
        if (isset($histSet[$n])) $hist++;
    }
    return ['hist' => $hist, 'new' => count($nodeNames) - $hist];
}

function resolveUniqueSketchName($conn, string $name): string {
    $baseName = mb_substr($name, 0, 100);
    $uniqueName = $baseName;
    $counter = 1;

    while ($conn->fetchOne("SELECT id FROM sketches WHERE name = ?", [$uniqueName])) {
        $counter++;
        $suffix = " ({$counter})";
        $maxBaseLen = 100 - strlen($suffix);
        $uniqueName = mb_substr($baseName, 0, $maxBaseLen) . $suffix;
    }

    return $uniqueName;
}

function parseBool($value): bool {
    if (is_bool($value)) return $value;
    $v = strtolower(trim((string)$value));
    return in_array($v, ['1', 'true', 'yes', 'y', 'on'], true);
}

function normalizeTags($tags): array {
    if (is_string($tags)) {
        $tags = array_map('trim', explode(',', $tags));
    }
    if (!is_array($tags)) return [];
    $tags = array_values(array_filter(array_map('trim', $tags), fn($v) => $v !== ''));
    return array_values(array_unique($tags));
}

function loadRuntimeConfig(): array {
    $opts = getopt('', [
        'config:',
        'config-json::',
        'dry-run::',
    ]);

    $cfg = [
        'mode' => null,                 // 1 = by category, 2 = by node type
        'category_id' => 0,
        'node_type' => '',
        'history_filter' => 'new',      // new | all | hist
        'offset' => 0,
        'amount' => null,               // null => all remaining
        'generator_config_id' => null,
        'tags' => [],
        'confirm' => false,
        'delay_us' => 500000,
        'dry_run' => false,

        '_source' => 'interactive',     // interactive | file | inline
        '_strict' => false,             // true when JSON config is provided
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

/* ──────────────────────────────────────────────────────────────────────────
   Main
────────────────────────────────────────────────────────────────────────── */

try {
    global $spw;

    $conn = $spw->getEntityManager()->getConnection();
    $ai   = new AIProvider();

    $optsOuter = getopt('', ['qjobs::']);
    $qjobs = isset($optsOuter['qjobs']) ? (int)$optsOuter['qjobs'] : 0;
    
    $jobsToProcess = [];
    if ($qjobs > 0) {
        $jobsToProcess = $conn->fetchAllAssociative("SELECT * FROM forge_jobs WHERE job_type = 'kg_sketch' AND status = 'pending' ORDER BY priority ASC, id ASC LIMIT " . $qjobs);
        if (empty($jobsToProcess)) {
            echo "No pending kg_sketch jobs found.\n";
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
            echo "\n=== RUNNING QUEUE JOB #$jobId (kg_sketch) ===\n";
        } else {
            $cfg = loadRuntimeConfig();
        }

        try {
            printHeader("KG Node -> Sketch Auto-Generator (DB/JSON-CAPABLE)");

            $strict = (bool)($cfg['_strict'] ?? false);
            $interactive = !$strict && isInteractiveCli();

            // 1. SELECT MODE
            // --------------------------------------------------------
            if ($cfg['mode'] === null) {
                if ($strict) {
                    throw new RuntimeException("mode is required in JSON mode.");
                }

                echo "[1] By Category (Filter by KG Folder, then select Node Type)\n";
                echo "[2] By Node Type (Process all nodes of a specific type globally)\n";

                $modeIndex = (int)input("\nSelect Mode[1 or 2]: ");
                if (!in_array($modeIndex, [1, 2], true)) {
                    throw new RuntimeException("Invalid mode selection.");
                }
                $cfg['mode'] = $modeIndex;
            }

            $modeIndex = (int)$cfg['mode'];
            if (!in_array($modeIndex, [1, 2], true)) {
                throw new RuntimeException("Invalid mode value. Use 1 or 2.");
            }

            $selectedCatId = 0;
            $selectedType = '';
            $where = "WHERE status = 'active'";

            if ($modeIndex === 1) {
                $cats = $conn->fetchAllAssociative("SELECT id, name FROM kg_categories ORDER BY name ASC");
                array_unshift($cats, ['id' => 0, 'name' => '-- ALL KNOWLEDGE GRAPH CATEGORIES --']);

                if ($interactive) {
                    printHeader("Select KG Category");
                    foreach ($cats as $i => $c) {
                        $catId = (int)$c['id'];
                        if ($catId > 0) {
                            $fam = getKgCatFamily($conn, $catId);
                            $famIn = implode(',', array_map('intval', $fam));
                            $catTotal = (int)$conn->fetchOne("SELECT COUNT(*) FROM kg_nodes WHERE status = 'active' AND category_id IN ($famIn)");
                            $catNodeNames = $conn->fetchFirstColumn("SELECT name FROM kg_nodes WHERE status = 'active' AND category_id IN ($famIn)");
                            $histCount = empty($catNodeNames) ? 0 : (int)$conn->fetchOne(
                                "SELECT COUNT(DISTINCT entity_name) FROM sketch_lore_history WHERE entity_name IN (" . implode(',', array_fill(0, count($catNodeNames), '?')) . ")",
                                $catNodeNames
                            );
                            $newCount = $catTotal - $histCount;
                            printRow($i + 1, $c['name']);
                            echo sprintf("    Count: %4d  [new: %4d] [hist: %4d]\n", $catTotal, $newCount, $histCount);
                        } else {
                            $catTotal = (int)$conn->fetchOne("SELECT COUNT(*) FROM kg_nodes WHERE status = 'active'");
                            $allNodeNames = $conn->fetchFirstColumn("SELECT name FROM kg_nodes WHERE status = 'active'");
                            $histCount = empty($allNodeNames) ? 0 : (int)$conn->fetchOne(
                                "SELECT COUNT(DISTINCT entity_name) FROM sketch_lore_history WHERE entity_name IN (" . implode(',', array_fill(0, count($allNodeNames), '?')) . ")",
                                $allNodeNames
                            );
                            $newCount = $catTotal - $histCount;
                            printRow($i + 1, $c['name']);
                            echo sprintf("    Count: %4d  [new: %4d] [hist: %4d]\n", $catTotal, $newCount, $histCount);
                        }
                    }
                }

                if ($cfg['category_id'] === null || $cfg['category_id'] === '') {
                    if ($strict) {
                        throw new RuntimeException("category_id is required in JSON mode when mode=1.");
                    }
                    $catIndex = (int)input("\nSelect Category Number: ") - 1;
                    if (!isset($cats[$catIndex])) throw new RuntimeException("Invalid selection.");
                    $cfg['category_id'] = (int)$cats[$catIndex]['id'];
                }

                $selectedCatId = (int)$cfg['category_id'];
                $selectedCatName = $selectedCatId > 0
                    ? (string)$conn->fetchOne("SELECT name FROM kg_categories WHERE id = ?", [$selectedCatId])
                    : '-- ALL KNOWLEDGE GRAPH CATEGORIES --';

                echo "Selected Context: " . $selectedCatName . "\n";

                if ($selectedCatId > 0) {
                    $family = getKgCatFamily($conn, $selectedCatId);
                    $familySql = implode(',', array_map('intval', $family));
                    $where .= " AND category_id IN (" . $familySql . ")";
                }

                $types = $conn->fetchAllAssociative("SELECT node_type, COUNT(*) as cnt FROM kg_nodes $where GROUP BY node_type ORDER BY cnt DESC");
                if (empty($types)) throw new RuntimeException("No active nodes found in this category.");

                if ($interactive) {
                    printHeader("Select Node Type in Category");
                    foreach ($types as $i => $t) {
                        $nodeNames = $conn->fetchFirstColumn(
                            "SELECT name FROM kg_nodes $where AND node_type = ?",
                            [$t['node_type']]
                        );
                        $hc = getHistCounts($conn, $nodeNames, (string)$t['node_type']);
                        printRow($i + 1, ucfirst((string)$t['node_type']));
                        echo sprintf("    Count: %4d  [new: %4d] [hist: %4d]\n", $t['cnt'], $hc['new'], $hc['hist']);
                    }
                }

                if (!isset($cfg['node_type']) || trim((string)$cfg['node_type']) === '') {
                    if ($strict) {
                        throw new RuntimeException("node_type is required in JSON mode when mode=1.");
                    }
                    $typeIndex = (int)input("\nSelect Type Number: ") - 1;
                    if (!isset($types[$typeIndex])) throw new RuntimeException("Invalid selection.");
                    $cfg['node_type'] = $types[$typeIndex]['node_type'];
                }

                $selectedType = trim((string)$cfg['node_type']);
            } else {
                // MODE 2: BY GLOBAL NODE TYPE
                $types = $conn->fetchAllAssociative("SELECT node_type, COUNT(*) as cnt FROM kg_nodes WHERE status = 'active' GROUP BY node_type ORDER BY cnt DESC");
                if (empty($types)) throw new RuntimeException("No active nodes found in the Knowledge Graph.");

                if ($interactive) {
                    printHeader("Select Global Node Type");
                    foreach ($types as $i => $t) {
                        $nodeNames = $conn->fetchFirstColumn(
                            "SELECT name FROM kg_nodes WHERE status = 'active' AND node_type = ?",
                            [$t['node_type']]
                        );
                        $hc = getHistCounts($conn, $nodeNames, (string)$t['node_type']);
                        printRow($i + 1, ucfirst((string)$t['node_type']));
                        echo sprintf("    Count: %4d  [new: %4d] [hist: %4d]\n", $t['cnt'], $hc['new'], $hc['hist']);
                    }
                }

                if (!isset($cfg['node_type']) || trim((string)$cfg['node_type']) === '') {
                    if ($strict) {
                        throw new RuntimeException("node_type is required in JSON mode when mode=2.");
                    }
                    $typeIndex = (int)input("\nSelect Type Number: ") - 1;
                    if (!isset($types[$typeIndex])) throw new RuntimeException("Invalid selection.");
                    $cfg['node_type'] = $types[$typeIndex]['node_type'];
                }

                $selectedType = trim((string)$cfg['node_type']);
                $selectedCatId = 0; // Represents "Global"
            }

            if ($selectedType === '') {
                throw new RuntimeException("No node_type selected.");
            }

            // 3. DEFINE RANGE
            // --------------------------------------------------------
            $sql = "SELECT * FROM kg_nodes $where AND node_type = :type ORDER BY name ASC";
            $entityList = $conn->fetchAllAssociative($sql, ['type' => $selectedType]);

            $total = count($entityList);
            $scopeDesc = $modeIndex === 1 ? "in selected category" : "globally";

            $allNames = array_column($entityList, 'name');
            $hcFull = getHistCounts($conn, $allNames, $selectedType);
            echo "\nFound {$total} '{$selectedType}' nodes {$scopeDesc}.  [new: {$hcFull['new']}] [hist: {$hcFull['hist']}]\n";

            // 3a. SELECT HISTORY FILTER
            // --------------------------------------------------------
            if (!isset($cfg['history_filter']) || trim((string)$cfg['history_filter']) === '') {
                $cfg['history_filter'] = 'new';
            }

            $filterInput = strtolower(trim((string)$cfg['history_filter']));
            if (!in_array($filterInput, ['new', 'all', 'hist'], true)) {
                $map = ['1' => 'new', '2' => 'all', '3' => 'hist'];
                $filterInput = $map[$filterInput] ?? 'new';
            }

            if (!$strict && $interactive) {
                echo "\nProcess which nodes?\n";
                echo "[1] new  - Only nodes without a sketch_lore_history entry (default)\n";
                echo "[2] all  - All nodes regardless of history\n";
                echo "[3] hist - Only nodes that already have a sketch_lore_history entry\n";
            }

            $histNames = $conn->fetchFirstColumn(
                "SELECT DISTINCT entity_name FROM sketch_lore_history WHERE entity_type = ?",
                [$selectedType]
            );
            $histSet = array_flip($histNames ?: []);

            if ($filterInput === 'new') {
                $filterLabel = 'new';
                $entityList = array_values(array_filter($entityList, fn($n) => !isset($histSet[$n['name']])));
            } elseif ($filterInput === 'hist') {
                $filterLabel = 'hist';
                $entityList = array_values(array_filter($entityList, fn($n) => isset($histSet[$n['name']])));
            } else {
                $filterLabel = 'all';
            }

            $total = count($entityList);
            echo "Filter: $filterLabel — {$total} nodes to consider.\n";

            if ($total === 0) throw new RuntimeException("No nodes match the selected filter. Nothing to process.");

            if ($cfg['offset'] === null || $cfg['offset'] === '') {
                $cfg['offset'] = 0;
            }
            $offset = max(0, (int)$cfg['offset']);

            if ($strict) {
                $amountInput = $cfg['amount'];
                $amount = ($amountInput === '' || $amountInput === null)
                    ? ($total - $offset)
                    : (int)$amountInput;
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
            echo "\nQueue: Processing " . count($processList) . " nodes (from index $offset).\n";

            // 4. SELECT GENERATOR CONFIG
            // --------------------------------------------------------
            $configs = $conn->fetchAllAssociative("SELECT config_id, title, output_schema FROM generator_config WHERE active = 1 ORDER BY title ASC");

            if (!$strict && $interactive) {
                printHeader("Select Generator Config");
                foreach ($configs as $i => $c) {
                    printRow($i + 1, $c['title']);
                }

                $genConfigRow = null;
                while ($genConfigRow === null) {
                    $cfgInput = trim(input("\nSelect Config Number (or 'l' to list queued nodes): "));
                    if (strtolower($cfgInput) === 'l') {
                        echo "\n--- Queued Nodes (" . count($processList) . ") ---\n";
                        foreach ($processList as $idx => $n) {
                            echo sprintf("  %4d. %s\n", $idx + 1, $n['name']);
                        }
                        echo "--- End of List ---\n";
                        continue;
                    }
                    $cfgIndex = (int)$cfgInput - 1;
                    if (!isset($configs[$cfgIndex])) {
                        echo "Invalid selection, please try again.\n";
                        continue;
                    }
                    $genConfigRow = $configs[$cfgIndex];
                }
                $cfg['generator_config_id'] = $genConfigRow['config_id'];
            }

            if (empty($cfg['generator_config_id'])) {
                throw new RuntimeException("generator_config_id is required in JSON mode.");
            }

            $genConfigId = (string)$cfg['generator_config_id'];
            $genConfigFull = $conn->fetchAssociative("SELECT * FROM generator_config WHERE config_id = ?", [$genConfigId]);
            if (!$genConfigFull) {
                throw new RuntimeException("Generator config not found: {$genConfigId}");
            }

            $schema = json_decode($genConfigFull['output_schema'] ?? '{}', true);
            $targetSchemaKey = is_array($schema) && isset($schema['required'][0]) ? (string)$schema['required'][0] : null;

            $instructionsArr = json_decode($genConfigFull['instructions'] ?? '[]', true);
            if (!is_array($instructionsArr)) $instructionsArr = [];
            $systemPrompt = trim((string)($genConfigFull['system_role'] ?? '')) . "\n\n" . implode("\n", $instructionsArr);

            $model = (!empty($genConfigFull['model']) && $genConfigFull['model'] !== 'openai')
                ? (string)$genConfigFull['model']
                : AIProvider::getDefaultModel();

            // 5. TAGS SELECTION
            // --------------------------------------------------------
            $tags = normalizeTags($cfg['tags'] ?? []);
            $finalTagIds = [];

            if (!empty($tags)) {
                echo "\nResolving tags...\n";
                foreach ($tags as $tagName) {
                    $tagId = $conn->fetchOne("SELECT id FROM tags WHERE name = ?", [$tagName]);

                    if (!$tagId) {
                        echo "- Creating new tag: [{$tagName}]\n";
                        $conn->insert('tags', [
                            'name' => $tagName,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                            'show_in_ui' => 1
                        ]);
                        $tagId = $conn->lastInsertId();
                    } else {
                        echo "- Found existing tag: [{$tagName}]\n";
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

            foreach ($processList as $index => $node) {
                $count++;
                $name = (string)$node['name'];

                // --- UNIQUE NAME GENERATION ---
                $uniqueName = resolveUniqueSketchName($conn, $name);

                echo "\n[$count/" . count($processList) . "] Processing: $uniqueName ... ";

                // A. Build Context directly from KG Node and Linked Items
                $items = $conn->fetchAllAssociative(
                    "SELECT * FROM kg_node_items WHERE node_id = ? ORDER BY sort_order ASC",
                    [$node['id']]
                );

                $network = [];
                foreach ($items as $it) {
                    $lbl = $it['item_label'] ?? ('ID:' . ($it['item_id'] ?? ''));
                    $relStr = $lbl . " (" . ($it['item_type'] ?? 'unknown') . ")";
                    if (!empty($it['relationship'])) $relStr .= " - " . $it['relationship'];
                    if (!empty($it['note'])) $relStr .= ": " . $it['note'];
                    $network[] = $relStr;
                }

                $contextData = [
                    'identity' => [
                        'name' => $node['name'] ?? '',
                        'type' => $node['node_type'] ?? '',
                        'description' => $node['description'] ?? '',
                        'keywords' => $node['keywords'] ?? ''
                    ]
                ];

                $contextStr = "IDENTITY:\n" . json_encode($contextData['identity'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
                if (!empty($network)) {
                    $contextStr .= "RELATIONSHIPS:\n" . implode("\n", $network) . "\n\n";
                }
                if (!empty($node['content'])) {
                    $contextStr .= "CONTENT:\n" . $node['content'];
                }

                // B. Generate
                $userPrompt = "Please generate a rich, visual sketch description for the following entity.\n\nENTITY: $name\nTYPE: $selectedType\n\nCONTEXT:\n$contextStr";

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
                    $newSketchId = (int)$conn->lastInsertId();

                    // D. Insert History
                    $conn->insert('sketch_lore_history', [
                        'sketch_id' => $newSketchId,
                        'doc_id' => $selectedCatId,
                        'entity_type' => $selectedType,
                        'entity_name' => mb_substr($name, 0, 255),
                        'generator_config_id' => $genConfigFull['id'] ?? 0,
                        'prompt_used' => mb_substr($userPrompt, 0, 2000),
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
