<?php
// PLUSH Highlight Block -> Sketch Generator Runner
// - CLI-first
// - Accepts JSON config via --config=file.json or --config-json='...'
// - Accepts --qjobs=X to pull from database queue (forge_jobs, job_type='plush_sketch')
// - Falls back to interactive prompts when no config is provided
//
// SELECTION MODES:
//   mode=1  Single highlight  (drill down: story -> scene -> group -> block)
//   mode=2  Batch by story    (select story -> all english blocks in story)
//   mode=3  Batch by group    (drill down: story -> scene -> group -> all blocks)
//
// CONTEXT per block:
//   block.text_content
//   + description of each linked plush_highlight_block_entities entity
//     (characters / factions / locations / animas / sketches -> .description)
//     (kg_nodes -> .content)

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

/**
 * Fetch all linked entity context for a block.
 * Returns an array of ['type' => ..., 'label' => ..., 'context' => ...] entries.
 */
function fetchBlockEntityContext($conn, int $blockId): array {
    $entities = $conn->fetchAllAssociative(
        "SELECT entity_type, entity_id, entity_label FROM plush_highlight_block_entities WHERE block_id = ? ORDER BY entity_type, entity_label ASC",
        [$blockId]
    );

    $results = [];

    foreach ($entities as $ent) {
        $type  = (string)$ent['entity_type'];
        $id    = (int)$ent['entity_id'];
        $label = (string)$ent['entity_label'];

        if ($type === 'kg_nodes') {
            // kg_nodes: use content column
            $row = $conn->fetchAssociative(
                "SELECT name, description, content FROM kg_nodes WHERE id = ? AND status = 'active'",
                [$id]
            );
            if ($row) {
                $ctx = '';
                if (!empty($row['description'])) {
                    $ctx .= trim($row['description']);
                }
                if (!empty($row['content'])) {
                    $ctx .= ($ctx ? "\n\n" : '') . trim($row['content']);
                }
                $results[] = [
                    'type'    => 'kg_node',
                    'label'   => $row['name'] ?: $label,
                    'context' => $ctx,
                ];
            }
        } else {
            // characters / factions / locations / animas / sketches -> description column
            $allowed = ['characters', 'factions', 'locations', 'animas', 'sketches'];
            if (!in_array($type, $allowed, true)) continue;

            $row = $conn->fetchAssociative(
                "SELECT name, description FROM `{$type}` WHERE id = ?",
                [$id]
            );
            if ($row && !empty($row['description'])) {
                $results[] = [
                    'type'    => rtrim($type, 's'), // humanize: characters -> character
                    'label'   => $row['name'] ?: $label,
                    'context' => trim($row['description']),
                ];
            }
        }
    }

    return $results;
}

/**
 * Build the full query block for a single highlight block.
 * Returns [userPrompt, blockLabel]
 */
function buildBlockPrompt($conn, array $block, string $storyTitle, string $sceneTitle, string $groupLabel): array {
    $blockId     = (int)$block['id'];
    $textContent = trim($block['text_content'] ?? '');
    $order       = (int)$block['display_order'];

    // Derive a label for naming: "Story / Scene / Group #N"
    $groupPart  = $groupLabel ? " / {$groupLabel}" : '';
    $blockLabel = mb_substr("{$storyTitle} / {$sceneTitle}{$groupPart} #{$order}", 0, 90);

    // Fetch entity context
    $entityContexts = fetchBlockEntityContext($conn, $blockId);

    // Build prompt
    $entitySection = '';
    foreach ($entityContexts as $ec) {
        $entitySection .= "\n--- " . strtoupper($ec['type']) . ": " . $ec['label'] . " ---\n";
        $entitySection .= $ec['context'] . "\n";
    }

    $userPrompt  = "Please generate a rich, visual sketch description for the following story highlight.\n\n";
    $userPrompt .= "STORY: {$storyTitle}\n";
    $userPrompt .= "SCENE: {$sceneTitle}\n";
    if ($groupLabel) $userPrompt .= "GROUP: {$groupLabel}\n";
    $userPrompt .= "BLOCK ORDER: #{$order}\n\n";
    $userPrompt .= "HIGHLIGHT TEXT:\n{$textContent}\n";

    if ($entitySection) {
        $userPrompt .= "\nLINKED ENTITY CONTEXT:\n{$entitySection}";
    }

    return [$userPrompt, $blockLabel];
}

function loadRuntimeConfig(): array {
    $opts = getopt('', [
        'config:',
        'config-json::',
        'dry-run::',
    ]);

    $cfg = [
        'mode'                => null, // 1=single, 2=by story, 3=by group
        'story_id'            => null,
        'scene_id'            => null,
        'group_id'            => null,
        'block_id'            => null, // for single mode
        'history_filter'      => 'new',
        'offset'              => 0,
        'amount'              => null,
        'generator_config_id' => null,
        'tags'                => [],
        'confirm'             => false,
        'delay_us'            => 500000,
        'dry_run'             => false,

        '_source' => 'interactive',
        '_strict' => false,
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
   Story / Scene / Group helpers
────────────────────────────────────────────────────────────────────────── */

function fetchStories($conn): array {
    return $conn->fetchAllAssociative(
        "SELECT s.id, s.title,
                COUNT(DISTINCT b.id) AS block_count
         FROM plush_stories s
         LEFT JOIN plush_scenes sc ON sc.story_id = s.id
         LEFT JOIN plush_highlight_blocks b ON b.scene_id = sc.id AND b.language_code = 'en'
         GROUP BY s.id, s.title
         ORDER BY s.id ASC"
    );
}

function fetchScenesForStory($conn, int $storyId): array {
    return $conn->fetchAllAssociative(
        "SELECT sc.id, sc.title, sc.scene_order,
                COUNT(DISTINCT b.id) AS block_count
         FROM plush_scenes sc
         LEFT JOIN plush_highlight_blocks b ON b.scene_id = sc.id AND b.language_code = 'en'
         WHERE sc.story_id = ?
         GROUP BY sc.id, sc.title, sc.scene_order
         ORDER BY sc.scene_order ASC",
        [$storyId]
    );
}

function fetchGroupsForScene($conn, int $sceneId): array {
    return $conn->fetchAllAssociative(
        "SELECT g.id, g.label, g.group_order,
                COUNT(DISTINCT b.id) AS block_count
         FROM plush_highlight_groups g
         LEFT JOIN plush_highlight_blocks b ON b.scene_id = g.scene_id AND b.group_id = g.id AND b.language_code = 'en'
         WHERE g.scene_id = ?
         GROUP BY g.id, g.label, g.group_order
         ORDER BY g.group_order ASC",
        [$sceneId]
    );
}

function fetchBlocksForGroup($conn, int $sceneId, int $groupId): array {
    return $conn->fetchAllAssociative(
        "SELECT * FROM plush_highlight_blocks
         WHERE scene_id = ? AND group_id = ? AND language_code = 'en'
         ORDER BY display_order ASC",
        [$sceneId, $groupId]
    );
}

function fetchAllBlocksForStory($conn, int $storyId): array {
    return $conn->fetchAllAssociative(
        "SELECT b.*, sc.title AS scene_title, sc.scene_order,
                g.label AS group_label, g.group_order
         FROM plush_highlight_blocks b
         JOIN plush_scenes sc ON sc.id = b.scene_id
         LEFT JOIN plush_highlight_groups g ON g.id = b.group_id AND g.scene_id = b.scene_id
         WHERE sc.story_id = ? AND b.language_code = 'en'
         ORDER BY sc.scene_order ASC, b.group_id ASC, b.display_order ASC",
        [$storyId]
    );
}

function getHistoryIdsForBlocks($conn, array $blockIds): array {
    if (empty($blockIds)) return [];
    $placeholders = implode(',', array_fill(0, count($blockIds), '?'));
    $params = array_merge(array_map('strval', $blockIds), ['plush_highlight_block']);
    $rows = $conn->fetchFirstColumn(
        "SELECT DISTINCT entity_name FROM sketch_lore_history WHERE entity_name IN ($placeholders) AND entity_type = ?",
        $params
    );
    return array_flip($rows ?: []);
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
        $jobsToProcess = $conn->fetchAllAssociative(
            "SELECT * FROM forge_jobs WHERE job_type = 'plush_sketch' AND status = 'pending' ORDER BY priority ASC, id ASC LIMIT " . $qjobs
        );
        if (empty($jobsToProcess)) {
            echo "No pending plush_sketch jobs found.\n";
            exit(0);
        }
    } else {
        $jobsToProcess[] = ['id' => null, 'payload' => null];
    }

    foreach ($jobsToProcess as $jobRow) {
        $jobId = $jobRow['id'] ?? null;
        if ($jobId) {
            $conn->executeStatement(
                "UPDATE forge_jobs SET status = 'processing', started_at = NOW() WHERE id = ?",
                [$jobId]
            );
            $cfg = json_decode($jobRow['payload'], true) ?: [];
            $cfg['_strict'] = true;
            $cfg['_source'] = 'queue';
            echo "\n=== RUNNING QUEUE JOB #$jobId (plush_sketch) ===\n";
        } else {
            $cfg = loadRuntimeConfig();
        }

        try {
            printHeader("PLUSH Highlight Block -> Sketch Auto-Generator");

            $strict      = (bool)($cfg['_strict'] ?? false);
            $interactive = !$strict && isInteractiveCli();

            // ──────────────────────────────────────────────────────────────
            // 1. SELECT MODE
            // ──────────────────────────────────────────────────────────────
            if ($cfg['mode'] === null) {
                if ($strict) {
                    throw new RuntimeException("mode is required in JSON mode. Use 1=single, 2=by story, 3=by group.");
                }

                echo "[1] Single highlight  (pick one block, drill-down by story/scene/group)\n";
                echo "[2] Batch by story    (all blocks in a selected story)\n";
                echo "[3] Batch by group    (all blocks in a selected story/scene/group)\n";

                $modeIndex = (int)input("\nSelect Mode [1, 2, or 3]: ");
                if (!in_array($modeIndex, [1, 2, 3], true)) {
                    throw new RuntimeException("Invalid mode selection.");
                }
                $cfg['mode'] = $modeIndex;
            }

            $modeIndex = (int)$cfg['mode'];
            if (!in_array($modeIndex, [1, 2, 3], true)) {
                throw new RuntimeException("Invalid mode value. Use 1, 2, or 3.");
            }

            // ──────────────────────────────────────────────────────────────
            // 2. STORY SELECTION (all modes need a story)
            // ──────────────────────────────────────────────────────────────
            $stories = fetchStories($conn);
            if (empty($stories)) {
                throw new RuntimeException("No stories found in plush_stories.");
            }

            if ($interactive) {
                printHeader("Select Story");
                foreach ($stories as $i => $s) {
                    printRow($i + 1, $s['title'], "(blocks: {$s['block_count']})");
                }
            }

            if (empty($cfg['story_id'])) {
                if ($strict) throw new RuntimeException("story_id is required in JSON mode.");
                $storyIdx = (int)input("\nSelect Story Number: ") - 1;
                if (!isset($stories[$storyIdx])) throw new RuntimeException("Invalid story selection.");
                $cfg['story_id'] = (int)$stories[$storyIdx]['id'];
            }

            $selectedStoryId = (int)$cfg['story_id'];
            $selectedStory   = null;
            foreach ($stories as $s) {
                if ((int)$s['id'] === $selectedStoryId) { $selectedStory = $s; break; }
            }
            if (!$selectedStory) throw new RuntimeException("Story ID {$selectedStoryId} not found.");

            echo "Story: [{$selectedStory['title']}]\n";

            // ──────────────────────────────────────────────────────────────
            // 3. SCENE SELECTION (modes 1 and 3 need a scene)
            // ──────────────────────────────────────────────────────────────
            $selectedSceneId    = 0;
            $selectedSceneTitle = '';
            $selectedGroupId    = 0;
            $selectedGroupLabel = '';

            if ($modeIndex === 1 || $modeIndex === 3) {
                $scenes = fetchScenesForStory($conn, $selectedStoryId);
                if (empty($scenes)) throw new RuntimeException("No scenes found for this story.");

                if ($interactive) {
                    printHeader("Select Scene");
                    foreach ($scenes as $i => $sc) {
                        printRow($i + 1, $sc['title'], "(blocks: {$sc['block_count']})");
                    }
                }

                if (empty($cfg['scene_id'])) {
                    if ($strict) throw new RuntimeException("scene_id is required in JSON mode for mode 1 and 3.");
                    $sceneIdx = (int)input("\nSelect Scene Number: ") - 1;
                    if (!isset($scenes[$sceneIdx])) throw new RuntimeException("Invalid scene selection.");
                    $cfg['scene_id'] = (int)$scenes[$sceneIdx]['id'];
                }

                $selectedSceneId = (int)$cfg['scene_id'];
                foreach ($scenes as $sc) {
                    if ((int)$sc['id'] === $selectedSceneId) {
                        $selectedSceneTitle = (string)$sc['title'];
                        break;
                    }
                }
                if (!$selectedSceneTitle) throw new RuntimeException("Scene ID {$selectedSceneId} not found.");
                echo "Scene: [{$selectedSceneTitle}]\n";

                // ──────────────────────────────────────────────────────────
                // 4. GROUP SELECTION (modes 1 and 3)
                // ──────────────────────────────────────────────────────────
                $groups = fetchGroupsForScene($conn, $selectedSceneId);
                if (empty($groups)) throw new RuntimeException("No groups found for this scene.");

                if ($interactive) {
                    printHeader("Select Group");
                    foreach ($groups as $i => $g) {
                        $lbl = $g['label'] ?: "(unlabelled group #{$g['id']})";
                        printRow($i + 1, $lbl, "(blocks: {$g['block_count']})");
                    }
                }

                if (empty($cfg['group_id'])) {
                    if ($strict) throw new RuntimeException("group_id is required in JSON mode for mode 1 and 3.");
                    $groupIdx = (int)input("\nSelect Group Number: ") - 1;
                    if (!isset($groups[$groupIdx])) throw new RuntimeException("Invalid group selection.");
                    $cfg['group_id'] = (int)$groups[$groupIdx]['id'];
                }

                $selectedGroupId = (int)$cfg['group_id'];
                foreach ($groups as $g) {
                    if ((int)$g['id'] === $selectedGroupId) {
                        $selectedGroupLabel = (string)($g['label'] ?? '');
                        break;
                    }
                }
                echo "Group: [" . ($selectedGroupLabel ?: "(unlabelled)") . "]\n";
            }

            // ──────────────────────────────────────────────────────────────
            // 5. BUILD PROCESS LIST
            // ──────────────────────────────────────────────────────────────
            $processList = [];

            if ($modeIndex === 1) {
                // Single block mode: list blocks in the selected group
                $groupBlocks = fetchBlocksForGroup($conn, $selectedSceneId, $selectedGroupId);
                if (empty($groupBlocks)) throw new RuntimeException("No blocks found in this group.");

                if ($interactive) {
                    printHeader("Select Highlight Block");
                    foreach ($groupBlocks as $i => $b) {
                        $preview = mb_substr(trim($b['text_content']), 0, 55);
                        printRow($i + 1, "#{$b['display_order']}  {$preview}");
                    }
                }

                if (empty($cfg['block_id'])) {
                    if ($strict) throw new RuntimeException("block_id is required in JSON mode for mode 1.");
                    $blockIdx = (int)input("\nSelect Block Number: ") - 1;
                    if (!isset($groupBlocks[$blockIdx])) throw new RuntimeException("Invalid block selection.");
                    $cfg['block_id'] = (int)$groupBlocks[$blockIdx]['id'];
                }

                $selectedBlockId = (int)$cfg['block_id'];
                $found = null;
                foreach ($groupBlocks as $b) {
                    if ((int)$b['id'] === $selectedBlockId) { $found = $b; break; }
                }
                if (!$found) throw new RuntimeException("Block ID {$selectedBlockId} not found in selected group.");

                // Enrich with scene/story/group context
                $found['_story_title'] = (string)$selectedStory['title'];
                $found['_scene_title'] = $selectedSceneTitle;
                $found['_group_label'] = $selectedGroupLabel;
                $processList[] = $found;

            } elseif ($modeIndex === 2) {
                // Batch by story: all english blocks in the story
                $allBlocks = fetchAllBlocksForStory($conn, $selectedStoryId);
                if (empty($allBlocks)) throw new RuntimeException("No blocks found in this story.");

                foreach ($allBlocks as &$b) {
                    $b['_story_title'] = (string)$selectedStory['title'];
                    $b['_scene_title'] = (string)($b['scene_title'] ?? '');
                    $b['_group_label'] = (string)($b['group_label'] ?? '');
                }
                unset($b);
                $processList = $allBlocks;

            } else {
                // Mode 3: batch by group
                $groupBlocks = fetchBlocksForGroup($conn, $selectedSceneId, $selectedGroupId);
                if (empty($groupBlocks)) throw new RuntimeException("No blocks found in this group.");

                foreach ($groupBlocks as &$b) {
                    $b['_story_title'] = (string)$selectedStory['title'];
                    $b['_scene_title'] = $selectedSceneTitle;
                    $b['_group_label'] = $selectedGroupLabel;
                }
                unset($b);
                $processList = $groupBlocks;
            }

            $totalBeforeFilter = count($processList);

            // ──────────────────────────────────────────────────────────────
            // 6. HISTORY FILTER (skip for single-block mode)
            // ──────────────────────────────────────────────────────────────
            if ($modeIndex !== 1) {
                $filterInput = strtolower(trim((string)($cfg['history_filter'] ?? 'new')));
                if (!in_array($filterInput, ['new', 'all', 'hist'], true)) {
                    $map = ['1' => 'new', '2' => 'all', '3' => 'hist'];
                    $filterInput = $map[$filterInput] ?? 'new';
                }

                if (!$strict && $interactive) {
                    echo "\nProcess which blocks?\n";
                    echo "[1] new  - Only blocks without a sketch_lore_history entry (default)\n";
                    echo "[2] all  - All blocks regardless of history\n";
                    echo "[3] hist - Only blocks that already have a sketch_lore_history entry\n";
                    $filterRaw = input("Choice [1/2/3, default=1]: ");
                    if (trim($filterRaw) !== '') {
                        $map = ['1' => 'new', '2' => 'all', '3' => 'hist'];
                        $filterInput = $map[$filterRaw] ?? $filterInput;
                    }
                    $cfg['history_filter'] = $filterInput;
                }

                $allBlockIds = array_column($processList, 'id');
                $histSet = getHistoryIdsForBlocks($conn, array_map('strval', $allBlockIds));

                if ($filterInput === 'new') {
                    $processList = array_values(array_filter($processList, fn($b) => !isset($histSet[(string)$b['id']])));
                } elseif ($filterInput === 'hist') {
                    $processList = array_values(array_filter($processList, fn($b) => isset($histSet[(string)$b['id']])));
                }
                // 'all' => keep everything

                $histCount = count(array_filter($allBlockIds, fn($id) => isset($histSet[(string)$id])));
                $newCount  = $totalBeforeFilter - $histCount;
                echo "\nFound {$totalBeforeFilter} blocks.  [new: {$newCount}] [hist: {$histCount}]\n";
                echo "Filter: {$filterInput} — " . count($processList) . " blocks to consider.\n";

                if (empty($processList)) throw new RuntimeException("No blocks match the selected filter. Nothing to process.");

                // Offset / Amount
                $offset = max(0, (int)($cfg['offset'] ?? 0));
                if ($strict) {
                    $amountInput = $cfg['amount'];
                    $available   = count($processList) - $offset;
                    $amount      = ($amountInput === '' || $amountInput === null)
                        ? $available
                        : (int)$amountInput;
                    if ($amount <= 0 || $amount > $available) $amount = $available;
                } else {
                    $amountInput = input("Amount to process (default All, offset={$offset}): ");
                    $available   = count($processList) - $offset;
                    $amount      = ($amountInput === '') ? $available : (int)$amountInput;
                    if ($amount <= 0 || $amount > $available) $amount = $available;
                }
                $processList = array_slice($processList, $offset, $amount);
            }

            echo "\nQueue: Processing " . count($processList) . " block(s).\n";

            // ──────────────────────────────────────────────────────────────
            // 7. SELECT GENERATOR CONFIG
            // ──────────────────────────────────────────────────────────────
            $configs = $conn->fetchAllAssociative(
                "SELECT config_id, title, output_schema FROM generator_config WHERE active = 1 ORDER BY title ASC"
            );

            if (!$strict && $interactive) {
                printHeader("Select Generator Config");
                foreach ($configs as $i => $c) {
                    printRow($i + 1, $c['title']);
                }

                $genConfigRow = null;
                while ($genConfigRow === null) {
                    $cfgInput = trim(input("\nSelect Config Number (or 'l' to list queued blocks): "));
                    if (strtolower($cfgInput) === 'l') {
                        echo "\n--- Queued Blocks (" . count($processList) . ") ---\n";
                        foreach ($processList as $idx => $b) {
                            $preview = mb_substr(trim($b['text_content']), 0, 60);
                            echo sprintf("  %4d. [Block #%d] %s\n", $idx + 1, $b['id'], $preview);
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

            $genConfigId   = (string)$cfg['generator_config_id'];
            $genConfigFull = $conn->fetchAssociative(
                "SELECT * FROM generator_config WHERE config_id = ?",
                [$genConfigId]
            );
            if (!$genConfigFull) {
                throw new RuntimeException("Generator config not found: {$genConfigId}");
            }

            $schema          = json_decode($genConfigFull['output_schema'] ?? '{}', true);
            $targetSchemaKey = is_array($schema) && isset($schema['required'][0])
                ? (string)$schema['required'][0]
                : null;

            $instructionsArr = json_decode($genConfigFull['instructions'] ?? '[]', true);
            if (!is_array($instructionsArr)) $instructionsArr = [];
            $systemPrompt    = trim((string)($genConfigFull['system_role'] ?? '')) . "\n\n" . implode("\n", $instructionsArr);

            $model = (!empty($genConfigFull['model']) && $genConfigFull['model'] !== 'openai')
                ? (string)$genConfigFull['model']
                : AIProvider::getDefaultModel();

            // ──────────────────────────────────────────────────────────────
            // 8. TAGS
            // ──────────────────────────────────────────────────────────────
            $tags        = normalizeTags($cfg['tags'] ?? []);
            $finalTagIds = [];

            if (!empty($tags)) {
                echo "\nResolving tags...\n";
                foreach ($tags as $tagName) {
                    $tagId = $conn->fetchOne("SELECT id FROM tags WHERE name = ?", [$tagName]);
                    if (!$tagId) {
                        echo "- Creating new tag: [{$tagName}]\n";
                        $conn->insert('tags', [
                            'name'       => $tagName,
                            'created_at' => date('Y-m-d H:i:s'),
                            'updated_at' => date('Y-m-d H:i:s'),
                            'show_in_ui' => 1,
                        ]);
                        $tagId = $conn->lastInsertId();
                    } else {
                        echo "- Found existing tag: [{$tagName}]\n";
                    }
                    $finalTagIds[] = (int)$tagId;
                }
            }
            echo "Mapped " . count($finalTagIds) . " tag(s) for this batch.\n";

            // ──────────────────────────────────────────────────────────────
            // 9. CONFIRM & RUN
            // ──────────────────────────────────────────────────────────────
            printHeader("Starting Generation...");

            if ($strict) {
                if (!parseBool($cfg['confirm'])) {
                    throw new RuntimeException("confirm must be true in JSON/DB mode.");
                }
            } else {
                $confirm = input("Ready to generate " . count($processList) . " sketch(es)? [y/N]: ");
                if (strtolower($confirm) !== 'y') throw new RuntimeException("Aborted.");
            }

            $count    = 0;
            $sqlSketch = "INSERT INTO sketches
                (`name`, `description`, `created_at`, `updated_at`, `order`, `regenerate_images`, `img2img`, `cnmap`)
                VALUES (:name, :desc, :created, :updated, 0, 0, 0, 0)";

            foreach ($processList as $index => $block) {
                $count++;
                $blockId    = (int)$block['id'];
                $storyTitle = (string)$block['_story_title'];
                $sceneTitle = (string)$block['_scene_title'];
                $groupLabel = (string)$block['_group_label'];

                // Build prompt + derive label
                [$userPrompt, $blockLabel] = buildBlockPrompt(
                    $conn, $block, $storyTitle, $sceneTitle, $groupLabel
                );

                $uniqueName = resolveUniqueSketchName($conn, $blockLabel);

                echo "\n[$count/" . count($processList) . "] Block #{$blockId} → \"{$uniqueName}\" ... ";

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

                    // Insert sketch
                    $conn->executeStatement($sqlSketch, [
                        'name'    => $uniqueName,
                        'desc'    => $finalText,
                        'created' => date('Y-m-d H:i:s'),
                        'updated' => date('Y-m-d H:i:s'),
                    ]);
                    $newSketchId = (int)$conn->lastInsertId();

                    // Insert history — keyed on block ID as string entity_name
                    $conn->insert('sketch_lore_history', [
                        'sketch_id'           => $newSketchId,
                        'doc_id'              => $selectedStoryId,
                        'entity_type'         => 'plush_highlight_block',
                        'entity_name'         => (string)$blockId,
                        'generator_config_id' => $genConfigFull['id'] ?? 0,
                        'prompt_used'         => mb_substr($userPrompt, 0, 2000),
                        'created_at'          => date('Y-m-d H:i:s'),
                    ]);

                    // Link tags
                    foreach ($finalTagIds as $tid) {
                        $conn->executeStatement(
                            "INSERT IGNORE INTO tags_2_sketches (from_id, to_id) VALUES (?, ?)",
                            [$tid, $newSketchId]
                        );
                    }

                    echo "Done (sketch ID: {$newSketchId})";

                } catch (Throwable $e) {
                    echo "Error: " . $e->getMessage();
                }

                usleep((int)($cfg['delay_us'] ?? 500000));
            }

            printHeader("Batch Complete");
            echo "Generated {$count} sketch(es).\n";

            if ($jobId) {
                $conn->executeStatement(
                    "UPDATE forge_jobs SET status = 'done', finished_at = NOW() WHERE id = ?",
                    [$jobId]
                );
            }

        } catch (Throwable $jobEx) {
            echo "\nJOB ERROR: " . $jobEx->getMessage() . "\n";
            if ($jobId) {
                $conn->executeStatement(
                    "UPDATE forge_jobs SET status = 'failed', error_msg = ?, finished_at = NOW() WHERE id = ?",
                    [substr($jobEx->getMessage(), 0, 5000), $jobId]
                );
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
