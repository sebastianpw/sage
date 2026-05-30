<?php
// public/cli_md_curator_compose.php
// PART 3: COMPOSER (Synthesizer)
// - Select multiple existing EXTRACTED docs (from md_doc_chunks)
// - Merges their chunks into a new "Composition" document
// - Runs the robust Assembly logic to create a merged Analysis

if (php_sapi_name() !== 'cli') die("CLI only\n");

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Entity\GeneratorConfig;

// ANSI Colors
const C_RESET   = "\033[0m";
const C_GREEN   = "\033[32m";
const C_YELLOW  = "\033[33m";
const C_CYAN    = "\033[36m";
const C_BLUE    = "\033[34m";
const C_RED     = "\033[31m";
const C_GRAY    = "\033[90m";
const C_WHITE   = "\033[97m";

// CONFIG
$curatorConfigId = 'md_curator_v1'; 

// Initialize DB
$em = $spw->getEntityManager();
$repo = $em->getRepository(GeneratorConfig::class);
$configLore = $repo->findOneBy(['configId' => $curatorConfigId]);
if (!$configLore) die(C_RED . "Error: Config $curatorConfigId not found.\n" . C_RESET);

// ----------------- STEP 1: INTERACTIVE SELECTION (FILTERED) -----------------

echo "\n" . C_CYAN . "🎹 MD CURATOR: COMPOSER" . C_RESET . "\n";

// *** UPDATED QUERY: Only fetch docs that have chunks ***
$sql = "
    SELECT DISTINCT d.id, d.name 
    FROM documentations d
    INNER JOIN md_doc_chunks c ON d.id = c.doc_id
    WHERE d.is_active = 1
    ORDER BY d.id DESC
";
$stmt = $pdo->query($sql);
$allDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($allDocs)) die(C_RED . "No extracted documents found (Run extract script first).\n" . C_RESET);

$selectedIds = [];
$done = false;

while (!$done) {
    echo "\n" . C_WHITE . "Available Extracted Documents:" . C_RESET . "\n";
    foreach ($allDocs as $d) {
        $id = $d['id'];
        $name = substr($d['name'], 0, 50);
        if (in_array($id, $selectedIds)) {
            // Greyed out if selected
            echo C_GRAY . "  [x] #$id $name (Selected)" . C_RESET . "\n";
        } else {
            echo "  [$id] $name\n";
        }
    }

    echo "\n" . C_YELLOW . "Current Selection: " . implode(', ', $selectedIds) . C_RESET . "\n";
    $input = readline("Enter ID to add (or press ENTER to finish): ");
    $input = trim($input);

    if ($input === '') {
        if (count($selectedIds) < 1) {
            echo C_RED . "Please select at least one document.\n" . C_RESET;
            continue;
        }
        $done = true;
    } else {
        $id = (int)$input;
        $found = false;
        foreach ($allDocs as $d) { if ($d['id'] == $id) $found = true; }
        
        if ($found) {
            if (!in_array($id, $selectedIds)) {
                $selectedIds[] = $id;
                echo C_GREEN . "Added #$id.\n" . C_RESET;
            } else {
                echo C_YELLOW . "Already selected.\n" . C_RESET;
            }
            
            $more = readline("Add another? [Y/n]: ");
            if (strtolower(trim($more)) === 'n') $done = true;
        } else {
            echo C_RED . "Invalid ID (or doc has no chunks).\n" . C_RESET;
        }
    }
}

// ----------------- STEP 2: CREATE COMPOSITION CONTAINER -----------------

// Ask for name
$defaultName = "Composition: " . count($selectedIds) . " Docs (" . date('M d H:i') . ")";
$nameInput = readline("Name for new Composition [$defaultName]: ");
$finalName = trim($nameInput) ?: $defaultName;

// Ask for Category
echo "\nTarget Category:\n";
$catStmt = $pdo->query("SELECT id, name FROM documentation_categories ORDER BY name ASC");
$cats = $catStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($cats as $c) { echo "  [{$c['id']}] {$c['name']}\n"; }
$catId = (int)readline("Category ID: ");

echo "\n" . C_CYAN . "Creating Composition..." . C_RESET . "\n";

// Insert into documentations (The Virtual Document)
$contentSummary = "Composed from Document IDs: " . implode(', ', $selectedIds);

$insDoc = $pdo->prepare("INSERT INTO documentations (name, content, category_id, type, is_active, created_at) VALUES (?, ?, ?, 'composition', 1, NOW())");
$insDoc->execute([$finalName, $contentSummary, $catId]);
$newDocId = $pdo->lastInsertId();

echo "Created Document #$newDocId.\n";

// ----------------- STEP 3: CLONE CHUNKS -----------------

echo "Cloning chunks...\n";

$newChunkIndex = 0;
$totalChunks = 0;

foreach ($selectedIds as $srcId) {
    // Fetch source chunks
    $chkStmt = $pdo->prepare("SELECT lore_raw, show_raw FROM md_doc_chunks WHERE doc_id = ? ORDER BY chunk_index ASC");
    $chkStmt->execute([$srcId]);
    $chunks = $chkStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($chunks as $chunk) {
        $insChunk = $pdo->prepare("INSERT INTO md_doc_chunks (doc_id, chunk_index, lore_raw, show_raw, updated_at) VALUES (?, ?, ?, ?, NOW())");
        $insChunk->execute([
            $newDocId,
            $newChunkIndex,
            $chunk['lore_raw'],
            $chunk['show_raw']
        ]);
        $newChunkIndex++;
        $totalChunks++;
    }
    echo "  - Imported chunks from Doc #$srcId\n";
}

echo C_GREEN . "Successfully cloned $totalChunks chunks to Doc #$newDocId.\n" . C_RESET;

// ----------------- STEP 4: AGGREGATE (ROBUST LOGIC) -----------------

echo "\n" . C_CYAN . "Starting Assembly for Composition..." . C_RESET . "\n";

// --- HELPERS (Robust) ---
function safe_str($input) {
    if (is_string($input)) return $input;
    if (is_numeric($input)) return (string)$input;
    if (is_null($input)) return '';
    if (is_array($input)) {
        return implode(' ', array_map(function($v) {
            return is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v;
        }, $input));
    }
    return json_encode($input, JSON_UNESCAPED_UNICODE);
}

function safe_array($v){ return is_array($v) ? $v : ($v === null ? [] : [$v]); }

function ensure_list($v) {
    if ($v === null) return [];
    if (!is_array($v)) return [$v];
    if (empty($v)) return [];
    if (array_keys($v) !== range(0, count($v) - 1)) return [$v];
    return $v;
}

function flatten_to_strings($input) {
    $result = [];
    if (is_null($input)) return $result;
    if (is_string($input) || is_numeric($input)) return [(string)$input];
    if (is_array($input)) {
        foreach ($input as $item) $result = array_merge($result, flatten_to_strings($item));
        return $result;
    }
    if (is_object($input)) {
        $arr = (array)$input;
        if (!empty($arr['name'])) return [(string)$arr['name']];
        if (!empty($arr['description'])) return [(string)$arr['description']];
        return [json_encode($arr, JSON_UNESCAPED_UNICODE)];
    }
    return $result;
}

function force_string_block($input) {
    $flat = flatten_to_strings($input);
    return implode("\n", array_map('trim', $flat));
}

function safe_merge($target, $source) {
    if (!is_array($target)) $target = [];
    foreach (ensure_list($source) as $item) $target[] = $item;
    return $target;
}

function safe_merge_with_source(array $target, $source, int $chunkIndex) {
    foreach (ensure_list($source) as $item) {
        if (is_array($item)) {
            $item['__src_chunk'] = $chunkIndex;
            $target[] = $item;
        } elseif (is_object($item)) {
            $arr = (array)$item;
            $arr['__src_chunk'] = $chunkIndex;
            $target[] = $arr;
        } else {
            $target[] = ['value' => (string)$item, '__src_chunk' => $chunkIndex];
        }
    }
    return $target;
}

function unique_objects_by_key(array $items, ?string $key = null) {
    $seen = []; $out = [];
    foreach ($items as $it) {
        $k = null;
        if ($key !== null && is_array($it) && isset($it[$key])) $k = safe_str($it[$key]);
        else $k = json_encode($it, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $out[] = $it;
    }
    return $out;
}

function normalize_name_key($name) {
    $s = safe_str($name);
    $s = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    if ($s === false) $s = (string)$name;
    $s = strtolower($s);
    $s = preg_replace('/\b(mr|mrs|ms|dr|sir|lady|lord|capt|prof)\.?\b/u', '', $s);
    $s = preg_replace('/[^a-z0-9]+/', '', $s);
    return trim($s);
}

function merge_characters_canonical(array $characters) {
    $map = [];
    foreach ($characters as $ch) {
        $entry = is_string($ch) ? ['name' => $ch] : (array)$ch;
        $name = $entry['name'] ?? ($entry['title'] ?? null);
        if (is_array($name)) $name = safe_str($name);
        if (!$name) $name = json_encode($entry, JSON_UNESCAPED_UNICODE);
        $key = normalize_name_key($name);
        if ($key === '') $key = md5(safe_str($name));

        if (!isset($map[$key])) {
            $map[$key] = ['name' => $name, 'aliases' => [], 'roles' => [], 'description' => '', 'sources' => [], 'raw' => []];
        }
        if (!empty($entry['alias'])) $map[$key]['aliases'][] = safe_str($entry['alias']);
        if (!empty($entry['aliases']) && is_array($entry['aliases'])) $map[$key]['aliases'] = array_merge($map[$key]['aliases'], $entry['aliases']);
        if (!empty($entry['role'])) $map[$key]['roles'][] = safe_str($entry['role']);
        if (!empty($entry['roles']) && is_array($entry['roles'])) $map[$key]['roles'] = array_merge($map[$key]['roles'], $entry['roles']);
        if (!empty($entry['description'])) {
            $desc = safe_str($entry['description']);
            if (strlen($desc) > strlen($map[$key]['description'])) $map[$key]['description'] = $desc;
        }
        if (isset($entry['__src_chunk'])) $map[$key]['sources'][] = (int)$entry['__src_chunk'];
        $map[$key]['raw'][] = $entry;
    }
    foreach ($map as $k => &$v) {
        $v['aliases'] = array_values(array_unique(array_filter(array_map('trim', array_map('safe_str', $v['aliases'])))));
        $v['roles'] = array_values(array_unique(array_filter(array_map('trim', array_map('safe_str', $v['roles'])))));
        $v['sources'] = array_values(array_unique($v['sources']));
        rsort($v['sources']);
    }
    return array_values($map);
}

function finalize_episode_concepts(array $episodes) {
    usort($episodes, function($a, $b) {
        $ia = isset($a['__src_chunk']) ? (int)$a['__src_chunk'] : 0;
        $ib = isset($b['__src_chunk']) ? (int)$b['__src_chunk'] : 0;
        return $ib <=> $ia;
    });
    $unique = []; $seen = [];
    foreach ($episodes as $ep) {
        $title = is_array($ep) && !empty($ep['title']) ? $ep['title'] : (is_string($ep) ? $ep : json_encode($ep));
        $title = safe_str($title);
        $title_normal = preg_replace('/[^a-z0-9]+/i', '', strtolower(@iconv('UTF-8','ASCII//TRANSLIT', $title) ?: $title));
        if ($title_normal === '') $title_normal = md5($title);
        if (isset($seen[$title_normal])) continue;
        $seen[$title_normal] = true;
        if (isset($ep['__src_chunk'])) unset($ep['__src_chunk']);
        $unique[] = $ep;
    }
    return $unique;
}

function finalize_scene_hooks(array $hooks) {
    $out = []; $seen = [];
    foreach ($hooks as $h) {
        $k = is_array($h) ? safe_str($h['title'] ?? '') . '|' . safe_str($h['description'] ?? '') : safe_str($h);
        if (trim($k) === '' || trim($k) === '|') continue;
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        if (is_array($h) && isset($h['__src_chunk'])) unset($h['__src_chunk']);
        $out[] = $h;
    }
    return $out;
}

function extract_json_safe($raw) {
    if (!is_string($raw)) $raw = (string)$raw;
    $raw = trim($raw);
    if ($raw === '') return null;
    if (preg_match('/```json\s*(.*?)\s*```/si', $raw, $m)) {
        $cand = trim($m[1]); $dec = json_decode($cand, true);
        if (json_last_error() === JSON_ERROR_NONE) return $dec;
    }
    $first = strpos($raw, '{');
    if ($first === false) { $dec = json_decode($raw, true); return (json_last_error() === JSON_ERROR_NONE) ? $dec : null; }
    $len = strlen($raw); $depth = 0; $start = null;
    for ($i = $first; $i < $len; $i++) {
        $ch = $raw[$i];
        if ($ch === '{') { if ($depth === 0) $start = $i; $depth++; } 
        elseif ($ch === '}') { $depth--; if ($depth === 0 && $start !== null) {
            $candidate = substr($raw, $start, $i - $start + 1);
            $dec = json_decode($candidate, true);
            if (json_last_error() === JSON_ERROR_NONE) return $dec;
        }}
    }
    $dec = json_decode($raw, true);
    return (json_last_error() === JSON_ERROR_NONE) ? $dec : null;
}

function extract_readable_bible($bestNarr, $raw_by_chunk, $aggLists, $aggEntities) {
    $parts = [];
    if (!empty($bestNarr) && is_array($bestNarr)) {
        $parts[] = "=== Narrative Architecture ===";
        if (!empty($bestNarr['core_conflict'])) $parts[] = "Core conflict: " . trim(safe_str($bestNarr['core_conflict']));
        if (!empty($bestNarr['central_metaphor'])) $parts[] = "Central metaphor: " . trim(safe_str($bestNarr['central_metaphor']));
        if (!empty($bestNarr['philosophical_stakes'])) $parts[] = "Philosophical stakes: " . trim(safe_str($bestNarr['philosophical_stakes']));
        if (!empty($bestNarr['readiness_score'])) $parts[] = "Readiness score: " . (float)$bestNarr['readiness_score'];
        $parts[] = "";
    }
    foreach ($raw_by_chunk['lore_raw'] as $idx => $raw) {
        $raw = safe_str($raw);
        if (strlen(trim($raw)) < 200) continue;
        if (preg_match('/(plot outline|narrative architecture|series bible|series arc)/i', $raw)) {
            $paras = preg_split('/\n\s*\n/', trim($raw));
            if (!empty($paras[0])) { $parts[] = "=== Plot outline (from chunk #" . $idx . ") ==="; $parts[] = trim($paras[0]); $parts[] = ""; break; }
        }
    }
    if (empty($parts) && !empty($aggLists['summary'])) {
        $parts[] = "=== Summary ==="; $parts[] = implode("\n\n", array_slice($aggLists['summary'], 0, 6)); $parts[] = "";
    }
    if (!empty($raw_by_chunk['episode_concepts_all'])) {
        $parts[] = "=== Episode Concepts (sample) ===";
        $examples = array_slice($raw_by_chunk['episode_concepts_all'], 0, 8);
        foreach ($examples as $ex) {
            $t = is_array($ex) && !empty($ex['title']) ? $ex['title'] : (is_string($ex) ? $ex : json_encode($ex));
            $parts[] = "- " . trim(safe_str($t));
        }
        $parts[] = "";
    }
    return trim(implode("\n", $parts));
}

function select_best_narrative(array $candidates) {
    if (empty($candidates)) return null;
    $uniq = unique_objects_by_key($candidates, 'core_conflict');
    usort($uniq, function($a, $b) {
        $sa = $a['readiness_score'] ?? null; $sb = $b['readiness_score'] ?? null;
        if ($sa !== $sb) return ($sa > $sb) ? -1 : 1;
        return 0;
    });
    $best = $uniq[0];
    if (isset($best['__src_chunk'])) unset($best['__src_chunk']);
    return $best;
}

function get_key_insensitive($arr, $possibleKeys) {
    if (!is_array($arr)) return null;
    foreach ($possibleKeys as $k) { if (isset($arr[$k])) return $arr[$k]; }
    $normalizedKeys = [];
    foreach ($arr as $k => $v) {
        $norm = strtolower(str_replace([' ', '_', '-'], '', $k));
        $normalizedKeys[$norm] = $v;
    }
    foreach ($possibleKeys as $target) {
        $normTarget = strtolower(str_replace([' ', '_', '-'], '', $target));
        if (isset($normalizedKeys[$normTarget])) return $normalizedKeys[$normTarget];
    }
    return null;
}

// --- EXECUTE AGGREGATION ---

$cStmt = $pdo->prepare("SELECT * FROM md_doc_chunks WHERE doc_id = ? ORDER BY chunk_index ASC");
$cStmt->execute([$newDocId]);
$dbChunks = $cStmt->fetchAll(PDO::FETCH_ASSOC);

$aggEntities = ['characters' => [], 'locations' => [], 'factions' => [], 'artifacts' => []];
$aggLists = ['summary' => [], 'timeline' => [], 'tech_magic' => [], 'themes' => [], 'moods' => [], 'scores' => []];
$aggShow = ['visual_keywords' => [], 'scene_hooks' => [], 'notes' => [], 'scores' => [], 'episodes' => [], 'narrative' => []];
$raw_by_chunk = ['lore_raw' => [], 'show_raw' => [], 'narrative_engines_all' => [], 'episode_concepts_all' => [], 'scene_hooks_all' => [], 'production_notes_by_chunk' => []];

foreach ($dbChunks as $row) {
    $idx = (int)$row['chunk_index'];
    $rawLore = $row['lore_raw'];
    $rawShow = $row['show_raw'];
    $raw_by_chunk['lore_raw'][$idx] = $rawLore;
    $raw_by_chunk['show_raw'][$idx] = $rawShow;

    if ($rawLore) {
        $dataLore = extract_json_safe($rawLore);
        if ($dataLore) {
            if (!empty($dataLore['summary'])) $aggLists['summary'][] = force_string_block($dataLore['summary']);
            if (!empty($dataLore['entities'])) {
                $ent = $dataLore['entities'];
                if (isset($ent['characters'])) {
                    $aggEntities['characters'] = safe_merge($aggEntities['characters'], $ent['characters']);
                    $aggEntities['locations'] = safe_merge($aggEntities['locations'], $ent['locations'] ?? []);
                    $aggEntities['factions'] = safe_merge($aggEntities['factions'], $ent['factions'] ?? []);
                    $aggEntities['artifacts'] = safe_merge($aggEntities['artifacts'], $ent['artifacts'] ?? []);
                } else if (is_array($ent) && isset($ent[0]['type'])) {
                    foreach ($ent as $e) {
                        $type = strtolower($e['type'] ?? '');
                        if (strpos($type, 'char') !== false) $aggEntities['characters'][] = $e;
                        elseif (strpos($type, 'loc') !== false) $aggEntities['locations'][] = $e;
                        elseif (strpos($type, 'fac') !== false || strpos($type, 'group') !== false) $aggEntities['factions'][] = $e;
                        else $aggEntities['artifacts'][] = $e;
                    }
                }
            }
            $loreSrc = $dataLore['lore_points'] ?? ($dataLore['production_assessment'] ?? []);
            if (empty($loreSrc)) $loreSrc = $dataLore;
            $timeline = get_key_insensitive($loreSrc, ['timeline_events', 'historical_events', 'Timeline Events', 'HISTORICAL_EVENTS']);
            if ($timeline) $aggLists['timeline'] = array_merge($aggLists['timeline'], flatten_to_strings($timeline));
            $tech = get_key_insensitive($loreSrc, ['technology_magic', 'Technology', 'magic_system']);
            if ($tech) $aggLists['tech_magic'] = array_merge($aggLists['tech_magic'], flatten_to_strings($tech));
            $themesObj = get_key_insensitive($dataLore, ['thematics']);
            if ($themesObj) {
                $themes = get_key_insensitive($themesObj, ['themes', 'Themes']);
                if ($themes) $aggLists['themes'] = array_merge($aggLists['themes'], flatten_to_strings($themes));
                $mood = get_key_insensitive($themesObj, ['mood', 'Mood']);
                if ($mood) $aggLists['moods'][] = force_string_block($mood);
            }
            $uScore = $dataLore['narrative_utility'] ?? ($dataLore['production_assessment']['readiness_score'] ?? null);
            if ($uScore !== null) $aggLists['scores'][] = (float)$uScore;
        }
    }

    if ($rawShow) {
        $dataShow = extract_json_safe($rawShow);
        if ($dataShow) {
            $vis = get_key_insensitive($dataShow, ['visual_keywords', 'VISUAL_KEYWORDS', 'visuals', 'Visual Keywords']);
            if ($vis) $aggShow['visual_keywords'] = array_merge($aggShow['visual_keywords'], flatten_to_strings($vis));
            $hooks = get_key_insensitive($dataShow, ['scene_hooks', 'SCENE_HOOKS', 'Scene Hooks']);
            if ($hooks) {
                foreach (safe_array($hooks) as $h) {
                    if (is_string($h)) $entry = ['title' => $h]; else $entry = (array)$h;
                    $entry['__src_chunk'] = $idx;
                    $aggShow['scene_hooks'][] = $entry;
                    $raw_by_chunk['scene_hooks_all'][] = $entry;
                }
            }
            $notes = get_key_insensitive($dataShow, ['production_notes', 'PRODUCTION_NOTES', 'Production Notes']);
            if ($notes) {
                $noteBlock = ['note' => force_string_block($notes), '__src_chunk' => $idx];
                $aggShow['notes'][] = $noteBlock;
                $raw_by_chunk['production_notes_by_chunk'][] = $noteBlock;
            }
            $narr = get_key_insensitive($dataShow, ['narrative_engine', 'NARRATIVE_ENGINE', 'Narrative Engine']);
            if ($narr) {
                $aggShow['narrative'] = safe_merge_with_source($aggShow['narrative'], $narr, $idx);
                foreach (ensure_list($narr) as $nItem) $raw_by_chunk['narrative_engines_all'][] = $nItem;
            }
            $ep = get_key_insensitive($dataShow, ['episode_concepts', 'EPISODE_CONCEPTS', 'Episode Concepts']);
            if ($ep) {
                $aggShow['episodes'] = safe_merge_with_source($aggShow['episodes'], $ep, $idx);
                foreach (ensure_list($ep) as $eItem) $raw_by_chunk['episode_concepts_all'][] = $eItem;
            }
            if (isset($dataShow['readiness_score'])) $aggShow['scores'][] = (float)$dataShow['readiness_score'];
        }
    }
}

$finalEntities_obj = [
    'characters' => merge_characters_canonical($aggEntities['characters'] ?? []),
    'locations' => array_values(unique_objects_by_key($aggEntities['locations'] ?? [])),
    'factions' => array_values(unique_objects_by_key($aggEntities['factions'] ?? [])),
    'artifacts' => array_values(unique_objects_by_key($aggEntities['artifacts'] ?? []))
];
$finalEntities = json_encode($finalEntities_obj, JSON_UNESCAPED_UNICODE);

$finalLore = json_encode([
    'timeline_events' => array_slice(array_values(array_unique($aggLists['timeline'])), 0, 50),
    'technology_magic' => array_slice(array_values(array_unique($aggLists['tech_magic'])), 0, 50)
], JSON_UNESCAPED_UNICODE);

$finalThemes = json_encode([
    'themes' => array_values(array_unique($aggLists['themes'])),
    'mood' => implode(', ', array_unique($aggLists['moods']))
], JSON_UNESCAPED_UNICODE);

$finalShowrunner_arr = [];
$finalShowrunner_arr['visual_keywords'] = array_slice(array_values(array_unique($aggShow['visual_keywords'])), 0, 50);
$finalShowrunner_arr['scene_hooks'] = finalize_scene_hooks($aggShow['scene_hooks'] ?? []);
$finalShowrunner_arr['production_notes'] = implode("\n\n---\n\n", array_map(function($n){ return $n['note'] ?? ''; }, $aggShow['notes']));
$finalShowrunner_arr['readiness_score'] = !empty($aggShow['scores']) ? array_sum($aggShow['scores']) / count($aggShow['scores']) : 0;
$bestNarr = select_best_narrative($aggShow['narrative'] ?? []);
$finalShowrunner_arr['narrative_engine'] = $bestNarr;
$finalShowrunner_arr['episode_concepts'] = finalize_episode_concepts($aggShow['episodes'] ?? []);

$finalShowrunner_arr['narrative_engines_all'] = $raw_by_chunk['narrative_engines_all'];
$finalShowrunner_arr['episode_concepts_all'] = $raw_by_chunk['episode_concepts_all'];
$finalShowrunner_arr['scene_hooks_all'] = $raw_by_chunk['scene_hooks_all'];
$finalShowrunner_arr['production_notes_by_chunk'] = $raw_by_chunk['production_notes_by_chunk'];
$finalShowrunner_arr['lore_raw_by_chunk'] = $raw_by_chunk['lore_raw'];
$finalShowrunner_arr['show_raw_by_chunk'] = $raw_by_chunk['show_raw'];

$readable_bible = extract_readable_bible($finalShowrunner_arr['narrative_engine'] ?? null, $raw_by_chunk, $aggLists, $finalEntities_obj);
$finalShowrunner_arr['series_bible'] = $readable_bible;

$cast_brief = [];
foreach (array_slice($finalEntities_obj['characters'], 0, 12) as $c) {
    $cast_brief[] = trim(($c['name'] ?? '') . ($c['roles'] ? ' — ' . implode(', ', $c['roles']) : ''));
}
$finalShowrunner_arr['cast_brief'] = $cast_brief;
$finalShowrunner_arr['extracted_entities'] = $finalEntities_obj;

$finalShowrunner = json_encode($finalShowrunner_arr, JSON_UNESCAPED_UNICODE);
$finalSummary = implode("\n\n", $aggLists['summary']);
$finalScore = !empty($aggLists['scores']) ? array_sum($aggLists['scores']) / count($aggLists['scores']) : 0;

$ins = $pdo->prepare("
    INSERT INTO md_doc_analysis 
    (doc_id, summary, entities, lore_points, thematics, narrative_utility, showrunner_analysis, series_bible, generator_config_id)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE 
        summary=VALUES(summary), entities=VALUES(entities), lore_points=VALUES(lore_points), 
        thematics=VALUES(thematics), narrative_utility=VALUES(narrative_utility), 
        showrunner_analysis=VALUES(showrunner_analysis), series_bible=VALUES(series_bible), 
        analyzed_at=NOW()
");

$cfgIdForSave = (method_exists($configLore,'getId')) ? $configLore->getId() : null;

$ins->execute([
    $newDocId, $finalSummary, $finalEntities, $finalLore, $finalThemes, $finalScore, $finalShowrunner, $readable_bible, $cfgIdForSave
]);

echo C_BLUE . " [ASSEMBLED]" . C_RESET;
echo "\n" . C_GREEN . "Composition #$newDocId created successfully!\n" . C_RESET;