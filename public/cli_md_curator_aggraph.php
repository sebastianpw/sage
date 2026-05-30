<?php
// public/cli_md_curator_aggraph.php
// AG → md_doc_analysis SYNC
//
// Reads ag_nodes + ag_node_items for a given doc (or all docs),
// parses the structured markdown back into entity/showrunner JSON,
// and writes it into md_doc_analysis.entities + showrunner_analysis
// WITHOUT touching any other column (summary, lore_points, thematics,
// narrative_utility, series_bible, analyzed_at, is_locked, etc.).
//
// Usage:
//   php cli_md_curator_aggraph.php              → interactive doc selector
//   php cli_md_curator_aggraph.php --doc=42     → single doc
//   php cli_md_curator_aggraph.php --all        → all docs with ag_nodes
//   php cli_md_curator_aggraph.php --dry-run    → print JSON, don't write

if (php_sapi_name() !== 'cli') die("CLI only\n");

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

// ── ANSI ──
const C_RESET  = "\033[0m";
const C_GREEN  = "\033[32m";
const C_YELLOW = "\033[33m";
const C_CYAN   = "\033[36m";
const C_RED    = "\033[31m";
const C_GRAY   = "\033[90m";
const C_BLUE   = "\033[34m";

// ── CLI args ──
$targetDocId = null;
$processAll  = false;
$dryRun      = false;

foreach ($argv as $arg) {
    if (strpos($arg, '--doc=') === 0)  $targetDocId = (int)substr($arg, 6);
    if ($arg === '--all')              $processAll  = true;
    if ($arg === '--dry-run')          $dryRun      = true;
}

echo C_CYAN . "\n🔄 AG → MD_DOC_ANALYSIS SYNC" . C_RESET . "\n";
if ($dryRun) echo C_YELLOW . "  [DRY RUN — no writes]\n" . C_RESET;
echo "\n";

// ── Node type → entities array key ──
// World types land in md_doc_analysis.entities
// Story types land in md_doc_analysis.showrunner_analysis
const WORLD_TYPE_MAP = [
    'character' => 'characters',
    'location'  => 'locations',
    'faction'   => 'factions',
    'artifact'  => 'artifacts',
    'object'    => 'objects',
    'role'      => 'roles',
];
const STORY_TYPE_MAP = [
    'episode'    => 'episode_concepts',
    'scene_hook' => 'scene_hooks',
    'narrative'  => 'narrative_engine',
];

// ══════════════════════════════════════════════════════════════
// MARKDOWN → ENTITY PARSER
// Reverses $convert_entity_to_markdown() deterministically.
// ══════════════════════════════════════════════════════════════

/**
 * Parse a structured markdown string back into an associative entity array.
 *
 * The markdown format produced by the aggregator is:
 *
 *   [description paragraph]
 *
 *   **Logline:** text          (optional hoisted scalars)
 *   **Core conflict:** text
 *   **Summary:** text
 *
 *   **Aliases:** val1, val2
 *   **Roles:** val1, val2
 *
 *   ### Section Name
 *   - **Key:** value
 *   - item
 *   ...
 *
 * Returns a plain PHP array matching the original entity shape.
 */
function parse_entity_markdown(string $md): array {
    if (trim($md) === '') return [];

    $lines = explode("\n", $md);
    $entity = [];

    // ── Split into preamble and sections ──
    // Preamble = everything before the first ### heading
    $preambleLines = [];
    $sections      = [];   // [ ['title'=>string, 'lines'=>[]] ]
    $currentSection = null;

    foreach ($lines as $line) {
        if (preg_match('/^### (.+)$/', $line, $m)) {
            if ($currentSection !== null) {
                $sections[] = $currentSection;
            }
            $currentSection = ['title' => trim($m[1]), 'lines' => []];
        } elseif ($currentSection !== null) {
            $currentSection['lines'][] = $line;
        } else {
            $preambleLines[] = $line;
        }
    }
    if ($currentSection !== null) $sections[] = $currentSection;

    // ── Parse preamble ──
    // Hoisted bold-key lines: **Key:** value
    // Aliases / Roles lines
    // Everything else accumulates into description
    $descLines = [];
    $inDesc    = true; // first non-empty, non-bold block is description

    foreach ($preambleLines as $line) {
        // **Aliases:** val1, val2
        if (preg_match('/^\*\*Aliases:\*\*\s*(.+)$/i', $line, $m)) {
            $entity['aliases'] = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
            $inDesc = false;
            continue;
        }
        // **Roles:** val1, val2
        if (preg_match('/^\*\*Roles:\*\*\s*(.+)$/i', $line, $m)) {
            $entity['roles'] = array_values(array_filter(array_map('trim', explode(',', $m[1]))));
            $inDesc = false;
            continue;
        }
        // **Key:** value  (other hoisted scalars: logline, core_conflict, summary)
        if (preg_match('/^\*\*([^*]+):\*\*\s*(.+)$/', $line, $m)) {
            $key = key_to_snake(trim($m[1]));
            $val = trim($m[2]);
            // blockquote continuation handled below; these are always single-line here
            $entity[$key] = $val;
            $inDesc = false;
            continue;
        }
        // Blockquote line > ... (continuation of a long scalar hoisted above)
        if (preg_match('/^>\s?(.*)$/', $line, $m)) {
            // Attach to the last non-desc key added
            // (rare edge case — just append to description if unclear)
            $descLines[] = trim($m[1]);
            continue;
        }
        // Separator line from multi-chunk smart concatenation
        if (trim($line) === '---') {
            $descLines[] = ''; // preserve paragraph break
            continue;
        }
        // Everything else: description
        $descLines[] = $line;
    }

    $description = trim(implode("\n", $descLines));
    if ($description !== '') {
        $entity['description'] = $description;
    }

    // ── Parse sections ──
    foreach ($sections as $sec) {
        $title = $sec['title'];         // e.g. "Attributes", "Timeline & Events", "Relationships"
        $key   = key_to_snake($title);  // e.g. "attributes", "timeline_events", "relationships"

        // Normalise known aliases
        if ($key === 'timeline_amp_events' || $key === 'timeline_events') $key = 'timeline';
        if ($key === 'timeline___events')   $key = 'timeline';

        $parsed = parse_section_lines($sec['lines']);

        if (!empty($parsed)) {
            $entity[$key] = $parsed;
        }
    }

    return $entity;
}

/**
 * Parse the content lines of a ### section back into a PHP value.
 *
 * The renderer produces three kinds of nested structures inside a section:
 *
 *   1. Flat:     - **Key:** scalar_value
 *   2. Sub-list: - **Key:**\n  - item1\n  - item2
 *   3. Nested:   \n**Key**\n - **SubKey:** val\n - **SubKey2:** val
 *                (standalone **Key** heading followed by un-indented children)
 *
 * Case 3 is what broke characters: the renderer emits a blank line then
 * **Physical** then un-indented "- **Height:** tall" lines.  The old parser
 * treated those child lines as new top-level keys, losing the parent.
 *
 * Fix: when we see a standalone **Key** heading we enter "nested mode" and
 * collect ALL subsequent lines until the next standalone heading or a blank
 * line that is followed by another heading/bullet at the same level.
 * Those collected lines are then parsed recursively as a sub-section.
 */
function parse_section_lines(array $lines): mixed {
    // Strip blank lines at start/end
    while (!empty($lines) && trim($lines[0])   === '') array_shift($lines);
    while (!empty($lines) && trim(end($lines)) === '') array_pop($lines);

    if (empty($lines)) return [];

    // ── Pre-pass: group lines into segments ──
    // A segment is either:
    //   'bullet'  → a single "- ..." line (flat bullet or keyed bullet)
    //   'nested'  → a standalone **Key** heading + its child lines
    //   'blockquote' → > lines belonging to the previous key
    //   'text'    → plain text lines
    //
    // We build a flat list of "tokens" then process them.

    $tokens = []; // each: ['type', 'line'|'key'+'children']

    $i = 0;
    $n = count($lines);
    $linesArr = array_values($lines);

    while ($i < $n) {
        $line = $linesArr[$i];

        // Standalone **Key** heading (no colon at end of bold span)
        // Matches:  **Anything here**   but NOT  - **Key:** value
        if (preg_match('/^\*\*([^*:]+)\*\*\s*$/', trim($line), $m)) {
            $key = key_to_snake(trim($m[1]));
            $children = [];
            $i++;
            // Collect following lines until we hit:
            //   - Another standalone **Key** heading  (next sibling nested block)
            //   - An un-indented bullet "^- "         (sibling flat key or list item)
            //   - A separator ---
            // Un-indented bullets are siblings at depth=0; nested children
            // are always indented (depth+1) by the renderer.
            while ($i < $n) {
                $next = $linesArr[$i];
                // Stop at next standalone **Key** heading
                if (preg_match('/^\*\*([^*:]+)\*\*\s*$/', trim($next))) break;
                // Stop at un-indented bullet (sibling, not child)
                if (preg_match('/^- /', $next)) break;
                // Stop at separator
                if (trim($next) === '---') break;
                // Strip one level of indentation (2 spaces) added by depth+1.
                // Use a safe strip of leading whitespace on keyed bullets only
                // so that deeper nesting (depth+2) also degrades gracefully.
                $stripped = preg_replace('/^  /', '', $next);
                $children[] = $stripped;
                $i++;
            }
            $tokens[] = ['type' => 'nested', 'key' => $key, 'children' => $children];
            continue;
        }

        // *Item N* pseudo-heading (complex array items from render_recursive)
        if (preg_match('/^\*Item \d+\*$/', trim($line))) {
            $i++;
            continue; // skip — items' content follows as normal lines
        }

        // Separator
        if (trim($line) === '---') {
            $tokens[] = ['type' => 'sep'];
            $i++;
            continue;
        }

        // Everything else: emit as a raw line token
        $tokens[] = ['type' => 'line', 'line' => $line];
        $i++;
    }

    // ── Process tokens into result ──
    $result  = [];
    $hasKeys = false;

    // Mini-flush for keyed bullet with pending sub-items
    $pendingKey  = null;
    $pendingVals = [];

    $flushPending = function() use (&$result, &$pendingKey, &$pendingVals, &$hasKeys) {
        if ($pendingKey === null) return;
        if (count($pendingVals) === 1) {
            $result[$pendingKey] = $pendingVals[0];
        } elseif (count($pendingVals) > 1) {
            $result[$pendingKey] = $pendingVals;
        } else {
            $result[$pendingKey] = true;
        }
        $hasKeys     = true;
        $pendingKey  = null;
        $pendingVals = [];
    };

    foreach ($tokens as $tok) {

        if ($tok['type'] === 'sep') {
            $flushPending();
            continue;
        }

        // ── Nested **Key** block → recurse ──
        if ($tok['type'] === 'nested') {
            $flushPending();
            $sub = parse_section_lines($tok['children']);
            if (!empty($sub)) {
                $result[$tok['key']] = $sub;
                $hasKeys = true;
            } else {
                // Empty nested block — store as empty array so the key survives
                $result[$tok['key']] = [];
                $hasKeys = true;
            }
            continue;
        }

        // ── Line tokens ──
        $line = $tok['line'];

        // Timeline item:  - **[date]** text
        if (preg_match('/^- \*\*\[([^\]]*)\]\*\*\s*(.*)$/', $line, $m)) {
            $flushPending();
            $result[] = ['date' => trim($m[1]), 'text' => trim($m[2])];
            continue;
        }

        // Keyed bullet with inline value:  - **Key:** value
        if (preg_match('/^(\s*)- \*\*([^*]+):\*\*\s*(.+)$/', $line, $m)) {
            $flushPending();
            $key = key_to_snake(trim($m[2]));
            $result[$key] = trim($m[3]);
            $hasKeys = true;
            continue;
        }

        // Keyed bullet with no inline value:  - **Key:**
        if (preg_match('/^(\s*)- \*\*([^*]+):\*\*\s*$/', $line, $m)) {
            $flushPending();
            $pendingKey  = key_to_snake(trim($m[2]));
            $pendingVals = [];
            $hasKeys     = true;
            continue;
        }

        // Indented sub-item under a pending key:    - item
        if (preg_match('/^(\s{2,})- (.+)$/', $line, $m) && $pendingKey !== null) {
            $pendingVals[] = trim($m[2]);
            continue;
        }

        // Blockquote continuation:  > text
        if (preg_match('/^(\s*)>\s?(.*)$/', $line, $m)) {
            if ($pendingKey !== null) {
                $pendingVals[] = trim($m[2]);
            }
            continue;
        }

        // Plain bullet:  - text
        if (preg_match('/^(\s*)- (.+)$/', $line, $m)) {
            $flushPending();
            $result[] = trim($m[2]);
            continue;
        }

        // Blank line: flush pending key
        if (trim($line) === '') {
            if ($pendingKey !== null && !empty($pendingVals)) {
                $flushPending();
            }
            continue;
        }

        // Fallback: plain text
        if ($pendingKey !== null) {
            $pendingVals[] = trim($line);
        } else {
            $result[] = trim($line);
        }
    }
    $flushPending();

    if (empty($result)) return [];

    // ── Determine if result is assoc or list ──
    if ($hasKeys) {
        // Collapse single-element sub-arrays to scalar where appropriate
        foreach ($result as $k => $v) {
            if (is_string($k) && is_array($v) && count($v) === 1 && is_string($v[0])) {
                $result[$k] = $v[0];
            }
        }
        return $result;
    }

    return array_values($result);
}

/**
 * Convert a display label ("Timeline & Events", "Core conflict") to snake_case key.
 */
function key_to_snake(string $label): string {
    $s = strtolower(trim($label));
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    return trim($s, '_');
}

// ══════════════════════════════════════════════════════════════
// RELATIONSHIP EXTRACTOR
// Reads ag_node_items edges for a node and rebuilds relationships array
// ══════════════════════════════════════════════════════════════

function extract_relationships_from_edges(PDO $pdo, int $nodeId, int $docId): array {
    $stmt = $pdo->prepare("
        SELECT item_label, relationship, note
        FROM ag_node_items
        WHERE node_id = ? AND doc_id = ? AND relationship != 'mentions'
        ORDER BY sort_order ASC, id ASC
    ");
    $stmt->execute([$nodeId, $docId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($rows)) return [];

    $rels = [];
    foreach ($rows as $r) {
        $rel = ['target' => $r['item_label'], 'type' => $r['relationship'] ?: 'related_to'];
        if (!empty($r['note'])) $rel['note'] = $r['note'];
        $rels[] = $rel;
    }
    return $rels;
}

// ══════════════════════════════════════════════════════════════
// ENTITIES JSON BUILDER
// Takes all ag_nodes for a doc and builds the entities + showrunner
// arrays that md_doc_analysis expects
// ══════════════════════════════════════════════════════════════

function build_entities_json(PDO $pdo, int $docId): array {
    $stmt = $pdo->prepare("
        SELECT id, name, node_type, content
        FROM ag_nodes
        WHERE doc_id = ? AND status = 'active'
        ORDER BY node_type ASC, name ASC
    ");
    $stmt->execute([$docId]);
    $nodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // World entities → entities JSON
    $entities = [
        'characters' => [],
        'locations'  => [],
        'factions'   => [],
        'artifacts'  => [],
        'objects'    => [],
        'roles'      => [],
    ];

    // Story entities → showrunner_analysis JSON fragments
    $episodeConcepts = [];
    $sceneHooks      = [];
    $narrativeEngine = null;

    foreach ($nodes as $n) {
        $type    = $n['node_type'];
        $content = $n['content'] ?? '';

        // Parse the markdown back into an entity array
        $entity = parse_entity_markdown($content);

        // Always ensure name is set from the ag_nodes.name column
        // (canonical — not dependent on markdown parse)
        $entity['name'] = $n['name'];

        // Rebuild relationships from ag_node_items edges
        $rels = extract_relationships_from_edges($pdo, (int)$n['id'], $docId);
        if (!empty($rels)) {
            $entity['relationships'] = $rels;
        }

        // Route to correct output bucket
        if (isset(WORLD_TYPE_MAP[$type])) {
            $key = WORLD_TYPE_MAP[$type];
            $entities[$key][] = $entity;

        } elseif ($type === 'episode') {
            // episode_concepts expects title + logline/description
            $ep = ['title' => $n['name']];
            if (!empty($entity['description'])) $ep['logline']     = $entity['description'];
            if (!empty($entity['logline']))      $ep['logline']     = $entity['logline'];
            foreach ($entity as $k => $v) {
                if (!in_array($k, ['name', 'description', 'logline', 'relationships'])) $ep[$k] = $v;
            }
            $episodeConcepts[] = $ep;

        } elseif ($type === 'scene_hook') {
            $hook = ['title' => $n['name']];
            if (!empty($entity['description'])) $hook['description'] = $entity['description'];
            foreach ($entity as $k => $v) {
                if (!in_array($k, ['name', 'description', 'relationships'])) $hook[$k] = $v;
            }
            $sceneHooks[] = $hook;

        } elseif ($type === 'narrative') {
            // narrative_engine is a single object, not an array
            $narrativeEngine = $entity;
            $narrativeEngine['name'] = $n['name'];
        }
        // 'note' and other unmapped types are intentionally skipped —
        // they were manually created in AG and have no corresponding
        // slot in md_doc_analysis.entities
    }

    return [
        'entities'   => $entities,
        'showrunner' => [
            'episode_concepts' => $episodeConcepts,
            'scene_hooks'      => $sceneHooks,
            'narrative_engine' => $narrativeEngine,
        ],
    ];
}

// ══════════════════════════════════════════════════════════════
// SHOWRUNNER MERGE
// We only update the story-entity keys inside showrunner_analysis,
// leaving all other keys (visual_keywords, production_notes,
// readiness_score, series_bible, cast_brief, lore_raw_by_chunk etc.)
// completely untouched.
// ══════════════════════════════════════════════════════════════

function merge_showrunner(array $existing, array $agStory): array {
    $merged = $existing;

    if (!empty($agStory['episode_concepts'])) {
        $merged['episode_concepts'] = $agStory['episode_concepts'];
    }
    if (!empty($agStory['scene_hooks'])) {
        $merged['scene_hooks'] = $agStory['scene_hooks'];
    }
    if ($agStory['narrative_engine'] !== null) {
        $merged['narrative_engine'] = $agStory['narrative_engine'];
    }

    return $merged;
}

// ══════════════════════════════════════════════════════════════
// PROCESS ONE DOC
// ══════════════════════════════════════════════════════════════

function process_doc(PDO $pdo, int $docId, bool $dryRun): void {
    // Check ag_nodes exist for this doc
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ag_nodes WHERE doc_id = ? AND status = 'active'");
    $countStmt->execute([$docId]);
    $count = (int)$countStmt->fetchColumn();

    if ($count === 0) {
        echo C_GRAY . "  Doc #$docId — no active ag_nodes, skipping\n" . C_RESET;
        return;
    }

    // Fetch existing md_doc_analysis row
    $stmt = $pdo->prepare("SELECT entities, showrunner_analysis FROM md_doc_analysis WHERE doc_id = ?");
    $stmt->execute([$docId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo C_YELLOW . "  Doc #$docId — no md_doc_analysis row found, skipping\n" . C_RESET;
        return;
    }

    // Build new JSON from AG tables
    $built = build_entities_json($pdo, $docId);

    // Entities: full replacement (all world-entity arrays rebuilt from AG)
    $newEntitiesJson = json_encode($built['entities'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    // Showrunner: merge only the story-entity keys, keep everything else
    $existingShowrunner = [];
    if (!empty($row['showrunner_analysis'])) {
        $decoded = json_decode($row['showrunner_analysis'], true);
        if (is_array($decoded)) $existingShowrunner = $decoded;
    }
    $newShowrunner     = merge_showrunner($existingShowrunner, $built['showrunner']);
    $newShowrunnerJson = json_encode($newShowrunner, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    // Stats for display
    $entityCounts = [];
    foreach ($built['entities'] as $key => $arr) {
        if (!empty($arr)) $entityCounts[] = count($arr) . ' ' . $key;
    }
    $storyCounts = [];
    if (!empty($built['showrunner']['episode_concepts'])) $storyCounts[] = count($built['showrunner']['episode_concepts']) . ' episodes';
    if (!empty($built['showrunner']['scene_hooks']))      $storyCounts[] = count($built['showrunner']['scene_hooks']) . ' hooks';
    if ($built['showrunner']['narrative_engine'])         $storyCounts[] = '1 narrative';

    echo C_CYAN . "  Doc #$docId" . C_RESET
       . " ($count nodes) → "
       . C_GREEN . implode(', ', $entityCounts) . C_RESET;
    if (!empty($storyCounts)) echo " | story: " . implode(', ', $storyCounts);
    echo "\n";

    if ($dryRun) {
        // Print a sample of what would be written
        $sample = array_slice($built['entities']['characters'] ?? $built['entities']['locations'] ?? [], 0, 1);
        if ($sample) {
            echo C_GRAY . "    Sample entity:\n";
            $lines = explode("\n", json_encode($sample[0], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
            foreach (array_slice($lines, 0, 12) as $l) echo "      $l\n";
            if (count($lines) > 12) echo "      ... (" . (count($lines) - 12) . " more lines)\n";
            echo C_RESET;
        }
        return;
    }

    // Write back — ONLY entities and showrunner_analysis columns
    $upd = $pdo->prepare("
        UPDATE md_doc_analysis
        SET entities = ?, showrunner_analysis = ?
        WHERE doc_id = ?
    ");
    $upd->execute([$newEntitiesJson, $newShowrunnerJson, $docId]);

    echo C_BLUE . "    [WRITTEN]\n" . C_RESET;
}

// ══════════════════════════════════════════════════════════════
// MAIN
// ══════════════════════════════════════════════════════════════

// Determine which docs to process
$docIds = [];

if ($targetDocId) {
    $docIds = [$targetDocId];

} elseif ($processAll) {
    $stmt = $pdo->query("
        SELECT DISTINCT ag.doc_id
        FROM ag_nodes ag
        JOIN md_doc_analysis mda ON mda.doc_id = ag.doc_id
        WHERE ag.status = 'active'
        ORDER BY ag.doc_id ASC
    ");
    $docIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Found " . count($docIds) . " docs with ag_nodes.\n\n";

} else {
    // Interactive selector
    $stmt = $pdo->query("
        SELECT ag.doc_id, d.name, COUNT(ag.id) as node_count
        FROM ag_nodes ag
        JOIN documentations d ON d.id = ag.doc_id
        JOIN md_doc_analysis mda ON mda.doc_id = ag.doc_id
        WHERE ag.status = 'active'
        GROUP BY ag.doc_id, d.name
        ORDER BY d.name ASC
    ");
    $docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($docs)) {
        die(C_RED . "No docs with ag_nodes found.\n" . C_RESET);
    }

    $map = []; $i = 1;
    echo "  [0] " . C_YELLOW . "Process ALL docs" . C_RESET . "\n";
    foreach ($docs as $d) {
        echo "  [$i] {$d['name']} (ID: {$d['doc_id']}, {$d['node_count']} nodes)\n";
        $map[$i] = (int)$d['doc_id'];
        $i++;
    }

    $sel = null;
    while ($sel === null) {
        $input = readline("\nSelect [0-" . ($i - 1) . "]: ");
        $val   = (int)$input;
        if ($val === 0) {
            $docIds = array_values($map);
            $sel    = 0;
        } elseif (isset($map[$val])) {
            $docIds = [$map[$val]];
            $sel    = $val;
        }
    }
    echo "\n";
}

if (empty($docIds)) {
    die(C_RED . "No docs to process.\n" . C_RESET);
}

// Process
$processed = 0;
foreach ($docIds as $docId) {
    process_doc($pdo, (int)$docId, $dryRun);
    $processed++;
}

echo "\n" . C_GREEN . "✓ Done. $processed doc(s) processed.\n" . C_RESET;
