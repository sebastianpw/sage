<?php
// public/cli_md_curator_aggregate.php
// PART 2: AGGREGATOR (Assembler) - ENHANCED LORE EXTRACTION
// - Robust JSON extraction (multiple-start balanced-brace search + fenced blocks + repairs)
// - Wrapper unwrapping for nested 'analysis', 'chunk_analysis', 'extraction' keys
// - ENHANCED: Comprehensive lore metadata capture (lore_rules, anima_types, visual_details, etc.)
// - Safe JSON encoding utilities
// - Defensive merging/normalization for LLM outputs
// - NEW: Skips locked documents and auto-populates ag_nodes via Smart Concatenation

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

// CONFIG
$limit = 100;
$targetCategoryId = null;
$curatorConfigId = 'md_curator_v1';

// Parse CLI args
foreach ($argv as $arg) {
    if (strpos($arg, '--cat=') === 0) $targetCategoryId = (int)substr($arg, 6);
    if (strpos($arg, '--limit=') === 0) $limit = (int)substr($arg, 8);
}

// Initialize Entity Manager
$em = $spw->getEntityManager();

// ----------------- HELPERS -----------------

function safe_str($input) {
    if (is_string($input)) return $input;
    if (is_numeric($input)) return (string)$input;
    if (is_null($input)) return '';
    if (is_array($input)) {
        return implode(' ', array_map(function($v) {
            if (is_array($v) || is_object($v)) return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            return (string)$v;
        }, $input));
    }
    if (is_object($input)) {
        return json_encode((array)$input, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
    return (string)$input;
}
function safe_array($v){ return is_array($v) ? $v : ($v === null ? [] : [$v]); }

function ensure_list($v) {
    if ($v === null) return [];
    if (!is_array($v)) return [$v];
    if (empty($v)) return[];
    if (array_keys($v) !== range(0, count($v) - 1)) return [$v];
    return $v;
}

function flatten_to_strings($input) {
    $result =[];
    if (is_null($input)) return $result;
    if (is_string($input) || is_numeric($input)) return [(string)$input];
    if (is_array($input)) {
        foreach ($input as $item) $result = array_merge($result, flatten_to_strings($item));
        return $result;
    }
    if (is_object($input)) {
        $arr = (array)$input;
        if (!empty($arr['name'])) return [(string)$arr['name']];
        if (!empty($arr['description'])) return[(string)$arr['description']];
        return[json_encode($arr, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR)];
    }
    return $result;
}

function force_string_block($input) {
    $flat = flatten_to_strings($input);
    return implode("\n", array_map('trim', $flat));
}

function safe_merge($target, $source) {
    if (!is_array($target)) $target =[];
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
            $target[] =['value' => (string)$item, '__src_chunk' => $chunkIndex];
        }
    }
    return $target;
}

function unique_objects_by_key(array $items, ?string $key = null) {
    $seen =[]; $out =[];
    foreach ($items as $it) {
        if ($key !== null && is_array($it) && isset($it[$key])) {
            $k = safe_str($it[$key]);
        } else {
            $k = @json_encode($it, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if ($k === false) $k = md5(serialize($it));
        }
        if (isset($seen[$k])) continue;
        $seen[$k] = true;
        $out[] = $it;
    }
    return $out;
}

function normalize_name_key($name) {
    $s = safe_str($name);
    $sAscii = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
    if ($sAscii === false) $sAscii = $s;
    $s = strtolower($sAscii);
    $s = preg_replace('/\b(mr|mrs|ms|dr|sir|lady|lord|capt|prof)\.?\b/u', '', $s);
    $s = preg_replace('/[^a-z0-9]+/', '', $s);
    return trim($s);
}

function merge_characters_canonical(array $characters) {
    $map =[];
    foreach ($characters as $ch) {
        $entry = is_string($ch) ? ['name' => $ch] : (array)$ch;
        $name = $entry['name'] ?? ($entry['title'] ?? null);
        if (is_array($name)) $name = safe_str($name);
        if (!$name) $name = json_encode($entry, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR);
        $key = normalize_name_key($name);
        if ($key === '') $key = md5(safe_str($name));
        if (!isset($map[$key])) {
            $map[$key] =['name' => $name, 'aliases' => [], 'roles' => [], 'description' => '', 'sources' =>[], 'raw' => []];
        }
        if (!empty($entry['alias'])) $map[$key]['aliases'][] = safe_str($entry['alias']);
        if (!empty($entry['aliases']) && is_array($entry['aliases'])) $map[$key]['aliases'] = array_merge($map[$key]['aliases'], array_map('safe_str', $entry['aliases']));
        if (!empty($entry['role'])) $map[$key]['roles'][] = safe_str($entry['role']);
        if (!empty($entry['roles']) && is_array($entry['roles'])) $map[$key]['roles'] = array_merge($map[$key]['roles'], array_map('safe_str', $entry['roles']));
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
    $unique = []; $seen =[];
    foreach ($episodes as $ep) {
        $title = is_array($ep) && !empty($ep['title']) ? $ep['title'] : (is_string($ep) ? $ep : json_encode($ep, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR));
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
    $out = []; $seen =[];
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

function utf8ize($mixed) {
    if (is_array($mixed)) {
        $out = [];
        foreach ($mixed as $k => $v) $out[$k] = utf8ize($v);
        return $out;
    }
    if (is_object($mixed)) {
        $arr = (array)$mixed;
        return utf8ize($arr);
    }
    if (is_string($mixed)) {
        if (!mb_check_encoding($mixed, 'UTF-8')) {
            return mb_convert_encoding($mixed, 'UTF-8', 'UTF-8');
        }
        return $mixed;
    }
    return $mixed;
}
function safe_json_encode($data, $options = JSON_UNESCAPED_UNICODE, $depth = 512) {
    $data = utf8ize($data);
    $enc = json_encode($data, $options, $depth);
    if ($enc !== false) return $enc;
    $enc = json_encode($data, $options | JSON_PARTIAL_OUTPUT_ON_ERROR, $depth);
    if ($enc !== false) {
        error_log("[md_curator_aggregate] json_encode partial output used: " . json_last_error_msg());
        return $enc;
    }
    $convert = function($x) use (&$convert) {
        if (is_array($x)) {
            $out =[];
            foreach ($x as $k => $v) $out[$k] = $convert($v);
            return $out;
        }
        if (is_object($x)) {
            $out =[];
            foreach ((array)$x as $k => $v) $out[$k] = $convert($v);
            return $out;
        }
        return safe_str($x);
    };
    $clean = $convert($data);
    $enc = json_encode($clean, JSON_UNESCAPED_UNICODE|JSON_PARTIAL_OUTPUT_ON_ERROR, $depth);
    if ($enc === false) {
        $err = json_last_error_msg();
        error_log("[md_curator_aggregate] FATAL json_encode failure: " . $err);
        return '{}';
    }
    return $enc;
}

function get_all_positions($text, $char) {
    $positions =[];
    $len = strlen($text);
    for ($i = 0; $i < $len; $i++) {
        if ($text[$i] === $char) $positions[] = $i;
    }
    return $positions;
}

function extract_json_safe($raw) {
    if (!is_string($raw)) $raw = (string)$raw;
    $raw = trim($raw);
    if ($raw === '') return null;

    $raw = preg_replace('/^\x{FEFF}/u', '', $raw);
    $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-8');

    $repair_common = function($candidate) {
        $candidate = str_replace("''", "'", $candidate);
        $candidate = str_replace(["\xE2\x80\x98", "\xE2\x80\x99", "\xE2\x80\x9C", "\xE2\x80\x9D"], ["'", "'", '"', '"'], $candidate);
        $candidate = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $candidate);
        $candidate = str_replace("\xE2\x80\xA6", '...', $candidate);
        return $candidate;
    };

    $try_decode = function($candidate) use ($repair_common) {
        $candidate = trim($candidate);
        if ($candidate === '') return null;
        $candidate = $repair_common($candidate);

        $d = json_decode($candidate, true);
        if (json_last_error() === JSON_ERROR_NONE) return $d;

        $fixed = preg_replace('/,\s*(\]|\})/m', '$1', $candidate);
        $d = json_decode($fixed, true);
        if (json_last_error() === JSON_ERROR_NONE) return $d;

        if (strpos($candidate, '"') === false && strpos($candidate, "'") !== false) {
            $repl = preg_replace('/\'([^\']*)\'/', '"$1"', $candidate);
            $d = json_decode($repl, true);
            if (json_last_error() === JSON_ERROR_NONE) return $d;
        }
        return null;
    };

    $extract_balanced_from = function($text, $startPos) {
        $len = strlen($text);
        $inString = false;
        $escaped = false;
        $depth = 0;
        for ($i = $startPos; $i < $len; $i++) {
            $ch = $text[$i];
            if ($inString) {
                if ($escaped) { $escaped = false; continue; }
                if ($ch === '\\') { $escaped = true; continue; }
                if ($ch === '"') { $inString = false; continue; }
            } else {
                if ($ch === '"') { $inString = true; continue; }
                if ($ch === '{') { $depth++; }
                elseif ($ch === '}') {
                    $depth--;
                    if ($depth === 0) {
                        return substr($text, $startPos, $i - $startPos + 1);
                    }
                }
            }
        }
        return null;
    };

    if (preg_match_all('/```(?:\s*json)?\s*(.*?)\s*```/is', $raw, $blocks)) {
        foreach ($blocks[1] as $block) {
            $d = $try_decode($block);
            if ($d !== null) return $d;
            $firstBrace = strpos($block, '{');
            if ($firstBrace !== false) {
                $sub = $extract_balanced_from($block, $firstBrace);
                if ($sub !== null) {
                    $d = $try_decode($sub);
                    if ($d !== null) return $d;
                }
            }
            $firstArr = strpos($block, '[');
            if ($firstArr !== false) {
                $sub = extract_top_level_array($block, $firstArr);
                if ($sub !== null) {
                    $d = $try_decode($sub);
                    if ($d !== null) return $d;
                }
            }
        }
    }

    $positions = get_all_positions($raw, '{');
    foreach ($positions as $pos) {
        $cand = $extract_balanced_from($raw, $pos);
        if ($cand !== null) {
            $d = $try_decode($cand);
            if ($d !== null) return $d;
        }
    }
    
    $positionsArr = get_all_positions($raw, '[');
    foreach ($positionsArr as $pos) {
        $cand = extract_top_level_array($raw, $pos);
        if ($cand !== null) {
            $d = $try_decode($cand);
            if ($d !== null) return $d;
        }
    }

    $first = strpos($raw, '{');
    $last = strrpos($raw, '}');
    if ($first !== false && $last !== false && $last > $first) {
        $cand = substr($raw, $first, $last - $first + 1);
        $d = $try_decode($cand);
        if ($d !== null) return $d;
    }

    $d = $try_decode($raw);
    if ($d !== null) return $d;

    return null;
}

function extract_top_level_array($text, $start) {
    $len = strlen($text);
    $inString = false;
    $escaped = false;
    $depth = 0;
    for ($i = $start; $i < $len; $i++) {
        $ch = $text[$i];
        if ($inString) {
            if ($escaped) { $escaped = false; continue; }
            if ($ch === '\\') { $escaped = true; continue; }
            if ($ch === '"') { $inString = false; continue; }
        } else {
            if ($ch === '"') { $inString = true; continue; }
            if ($ch === '[') { $depth++; }
            elseif ($ch === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $start, $i - $start + 1);
                }
            }
        }
    }
    return null;
}

function unwrap_wrappers($data) {
    if (!is_array($data)) return $data;
    $out = $data;
    
    $wrappers =[
        'analysis', 
        'showrunner_analysis', 
        'analysis_showrunner', 
        'analysis.showrunner', 
        'chunk_analysis',
        'extraction',
        'extraction_metadata'
    ];
    
    foreach ($wrappers as $w) {
        if (isset($data[$w]) && is_array($data[$w])) {
            $out = array_merge($out, $data[$w]);
        }
    }
    
    if (isset($data['analysis']['showrunner_analysis']) && is_array($data['analysis']['showrunner_analysis'])) {
        $out = array_merge($out, $data['analysis']['showrunner_analysis']);
    }
    
    if (isset($data['chunk_analysis']['showrunner_analysis']) && is_array($data['chunk_analysis']['showrunner_analysis'])) {
        $out = array_merge($out, $data['chunk_analysis']['showrunner_analysis']);
    }
    
    return $out;
}

function extract_readable_bible($bestNarr, $raw_by_chunk, $aggLists, $aggEntities) {
    $parts =[];
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
    $normalizedKeys =[];
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

// ----------------- MAIN LOGIC -----------------

if ($targetCategoryId === null) {
    echo "\n" . C_CYAN . "🏗️ MD CURATOR: ASSEMBLE (DB -> VIEW)" . C_RESET . "\n";
    $catStmt = $pdo->query("SELECT id, name FROM documentation_categories ORDER BY name ASC");
    $cats = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    $map = []; $i = 1;
    echo "  [0] " . C_YELLOW . "Process EVERYTHING" . C_RESET . "\n";
    foreach ($cats as $c) { echo "  [$i] {$c['name']} (ID: {$c['id']})\n"; $map[$i] = $c['id']; $i++; }
    while ($targetCategoryId === null) {
        $input = readline("Select Category [0-$i]: ");
        $val = (int)$input;
        if ($val === 0) { $targetCategoryId = 0; }
        elseif (isset($map[$val])) { $targetCategoryId = $map[$val]; }
    }
}

// EXCLUDE LOCKED DOCUMENTS
$whereSql = "WHERE d.is_active = 1 AND (mda.is_locked = 0 OR mda.is_locked IS NULL)";
$params =[];
if ($targetCategoryId > 0) { $whereSql .= " AND d.category_id = :cat"; $params['cat'] = $targetCategoryId; }


/*
// Use LEFT JOIN to access mda.is_locked
$sql = "SELECT d.id, d.name, d.target_collection 
        FROM documentations d 
        LEFT JOIN md_doc_analysis mda ON d.id = mda.doc_id 
        $whereSql 
        ORDER BY d.updated_at DESC, d.id DESC LIMIT $limit";
        */
        
        
// Use LEFT JOIN to access mda.is_locked
// FIX: Order by analyzed_at ASC so it naturally cycles through un-analyzed docs first
$sql = "SELECT d.id, d.name, d.target_collection 
        FROM documentations d 
        LEFT JOIN md_doc_analysis mda ON d.id = mda.doc_id 
        $whereSql 
        ORDER BY mda.analyzed_at ASC, d.id DESC LIMIT $limit";
        
        
        
        

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($docs)) die(C_GREEN . "No docs found or all docs are locked.\n" . C_RESET);

foreach ($docs as $doc) {
    $id = $doc['id'];
    echo "\nAssembling Doc #$id: " . C_CYAN . substr($doc['name'], 0, 30) . C_RESET . "... ";

    $cStmt = $pdo->prepare("SELECT * FROM md_doc_chunks WHERE doc_id = ? ORDER BY chunk_index ASC");
    $cStmt->execute([$id]);
    $dbChunks = $cStmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($dbChunks)) {
        echo C_RED . "No chunks found in DB (Run extract script first)\n" . C_RESET;
        continue;
    }

    $aggEntities = [
        'characters' => [], 
        'locations' => [], 
        'factions' => [], 
        'artifacts' =>[],
        'objects' => [],
        'roles' => []
    ];
    
    $aggLists =[
        'summary' => [], 
        'timeline' => [], 
        'tech_magic' =>[], 
        'themes' => [], 
        'moods' => [], 
        'scores' =>[],
        'lore_rules' => [],
        'visual_details' =>[],
        'anima_types' => [],
        'trade_goods' => [],
        'population_data' =>[]
    ];
    
    $aggShow = ['visual_keywords' => [], 'scene_hooks' =>[], 'notes' => [], 'scores' => [], 'episodes' => [], 'narrative' =>[]];
    $raw_by_chunk = [
        'lore_raw' => [], 'show_raw' =>[],
        'narrative_engines_all' => [], 'episode_concepts_all' =>[],
        'scene_hooks_all' => [], 'production_notes_by_chunk' =>[]
    ];

    foreach ($dbChunks as $row) {
        $idx = (int)$row['chunk_index'];
        $rawLore = $row['lore_raw'];
        $rawShow = $row['show_raw'];

        $raw_by_chunk['lore_raw'][$idx] = $rawLore;
        $raw_by_chunk['show_raw'][$idx] = $rawShow;

        // --- PARSE LORE ---
        if ($rawLore) {
            $dataLore = extract_json_safe($rawLore);
            if ($dataLore) {
                $dataLore = unwrap_wrappers($dataLore);

                if (!empty($dataLore['summary'])) $aggLists['summary'][] = force_string_block($dataLore['summary']);

                if (!empty($dataLore['entities'])) {
                    $ent = $dataLore['entities'];
                    if (isset($ent['characters'])) {
                        $aggEntities['characters'] = safe_merge($aggEntities['characters'], $ent['characters']);
                    }
                    if (isset($ent['locations'])) {
                        $aggEntities['locations'] = safe_merge($aggEntities['locations'], $ent['locations']);
                    }
                    if (isset($ent['factions'])) {
                        $aggEntities['factions'] = safe_merge($aggEntities['factions'], $ent['factions']);
                    }
                    if (isset($ent['artifacts'])) {
                        $aggEntities['artifacts'] = safe_merge($aggEntities['artifacts'], $ent['artifacts']);
                    }
                    if (isset($ent['objects'])) {
                        $aggEntities['objects'] = safe_merge($aggEntities['objects'], $ent['objects']);
                    }
                    if (isset($ent['roles'])) {
                        $aggEntities['roles'] = safe_merge($aggEntities['roles'], $ent['roles']);
                    }
                    
                    if (is_array($ent) && isset($ent[0]['type'])) {
                        foreach ($ent as $e) {
                            $type = strtolower($e['type'] ?? '');
                            if (strpos($type, 'char') !== false) $aggEntities['characters'][] = $e;
                            elseif (strpos($type, 'loc') !== false) $aggEntities['locations'][] = $e;
                            elseif (strpos($type, 'fac') !== false || strpos($type, 'group') !== false) $aggEntities['factions'][] = $e;
                            elseif (strpos($type, 'role') !== false) $aggEntities['roles'][] = $e;
                            elseif (strpos($type, 'object') !== false) $aggEntities['objects'][] = $e;
                            else $aggEntities['artifacts'][] = $e;
                        }
                    }
                }

                $loreSrc = $dataLore['lore_points'] ?? ($dataLore['production_assessment'] ??[]);
                if (empty($loreSrc)) $loreSrc = $dataLore;

                $timeline = get_key_insensitive($loreSrc,[
                    'timeline_events', 'historical_events', 'Timeline Events', 'HISTORICAL_EVENTS', 'history'
                ]);
                if ($timeline) $aggLists['timeline'] = array_merge($aggLists['timeline'], flatten_to_strings($timeline));

                $tech = get_key_insensitive($loreSrc,[
                    'technology_magic', 'Technology', 'magic_system', 'anima_system', 'power_system'
                ]);
                if ($tech) $aggLists['tech_magic'] = array_merge($aggLists['tech_magic'], flatten_to_strings($tech));

                $animaTypes = get_key_insensitive($loreSrc,[
                    'anima_types_catalog', 'anima_types', 'documented_anima_types', 'power_types', 'magic_types'
                ]);
                if ($animaTypes) {
                    $aggLists['anima_types'] = array_merge($aggLists['anima_types'], flatten_to_strings($animaTypes));
                }

                $loreRules = get_key_insensitive($loreSrc,[
                    'lore_rules', 'rules', 'mechanics', 'constraints', 'laws', 'systems'
                ]);
                if ($loreRules) {
                    $aggLists['lore_rules'] = array_merge($aggLists['lore_rules'], flatten_to_strings($loreRules));
                }

                $visualDetails = get_key_insensitive($loreSrc,[
                    'visual_sensory_details', 'visual_details', 'sensory_details', 'aesthetics'
                ]);
                if ($visualDetails) {
                    $aggLists['visual_details'] = array_merge($aggLists['visual_details'], flatten_to_strings($visualDetails));
                }

                $tradeGoods = get_key_insensitive($loreSrc,[
                    'trade_goods_and_exports', 'trade_goods', 'exports', 'economy', 'economic_systems'
                ]);
                if ($tradeGoods) {
                    $aggLists['trade_goods'] = array_merge($aggLists['trade_goods'], flatten_to_strings($tradeGoods));
                }

                $popMetrics = get_key_insensitive($loreSrc,[
                    'population_metrics', 'population_summary', 'demographics', 'statistics'
                ]);
                if ($popMetrics) {
                    $aggLists['population_data'] = array_merge($aggLists['population_data'], flatten_to_strings($popMetrics));
                }

                $themesObj = get_key_insensitive($dataLore, ['thematics']);
                if ($themesObj) {
                    $themes = get_key_insensitive($themesObj,['themes', 'Themes']);
                    if ($themes) $aggLists['themes'] = array_merge($aggLists['themes'], flatten_to_strings($themes));
                    $mood = get_key_insensitive($themesObj,['mood', 'Mood']);
                    if ($mood) $aggLists['moods'][] = force_string_block($mood);
                }

                $uScore = $dataLore['narrative_utility'] ?? ($dataLore['production_assessment']['readiness_score'] ?? null);
                if ($uScore !== null) $aggLists['scores'][] = (float)$uScore;
            } else {
                error_log("[md_curator_aggregate] Failed to parse lore_raw for doc {$id} chunk {$idx}");
            }
        }

        // --- PARSE SHOWRUNNER ---
        if ($rawShow) {
            $dataShow = extract_json_safe($rawShow);
            if ($dataShow) {
                $dataShow = unwrap_wrappers($dataShow);

                $vis = get_key_insensitive($dataShow, ['visual_keywords', 'VISUAL_KEYWORDS', 'visuals', 'Visual Keywords']);
                if ($vis) $aggShow['visual_keywords'] = array_merge($aggShow['visual_keywords'], flatten_to_strings($vis));

                $hooks = get_key_insensitive($dataShow,['scene_hooks', 'SCENE_HOOKS', 'Scene Hooks']);
                if ($hooks) {
                    foreach (safe_array($hooks) as $h) {
                        if (is_string($h)) $entry =['title' => $h]; else $entry = (array)$h;
                        if (empty($entry['title']) && !empty($entry['scene'])) $entry['title'] = $entry['scene'];
                        $entry['__src_chunk'] = $idx;
                        $aggShow['scene_hooks'][] = $entry;
                        $raw_by_chunk['scene_hooks_all'][] = $entry;
                    }
                }

                $notes = get_key_insensitive($dataShow,['production_notes', 'PRODUCTION_NOTES', 'Production Notes']);
                if ($notes) {
                    $noteBlock =['note' => force_string_block($notes), '__src_chunk' => $idx];
                    $aggShow['notes'][] = $noteBlock;
                    $raw_by_chunk['production_notes_by_chunk'][] = $noteBlock;
                }

                $narr = get_key_insensitive($dataShow,['narrative_engine', 'NARRATIVE_ENGINE', 'Narrative Engine']);
                if ($narr) {
                    $aggShow['narrative'] = safe_merge_with_source($aggShow['narrative'], $narr, $idx);
                    foreach (ensure_list($narr) as $nItem) $raw_by_chunk['narrative_engines_all'][] = $nItem;
                } else {
                    $narrAlt = get_key_insensitive($dataShow, ['analysis', 'showrunner_analysis']);
                    if ($narrAlt && is_array($narrAlt) && isset($narrAlt['narrative_engine'])) {
                        $aggShow['narrative'] = safe_merge_with_source($aggShow['narrative'], $narrAlt['narrative_engine'], $idx);
                        $raw_by_chunk['narrative_engines_all'][] = $narrAlt['narrative_engine'];
                    }
                }

                $ep = get_key_insensitive($dataShow,['episode_concepts', 'EPISODE_CONCEPTS', 'Episode Concepts']);
                if ($ep) {
                    $aggShow['episodes'] = safe_merge_with_source($aggShow['episodes'], $ep, $idx);
                    foreach (ensure_list($ep) as $eItem) $raw_by_chunk['episode_concepts_all'][] = $eItem;
                }

                if (isset($dataShow['readiness_score'])) $aggShow['scores'][] = (float)$dataShow['readiness_score'];
            } else {
                error_log("[md_curator_aggregate] Failed to parse show_raw for doc {$id} chunk {$idx}");
            }
        }
    }

    // --- CONSTRUCT FINAL JSON ---
    $finalEntities_obj = [
        'characters' => merge_characters_canonical($aggEntities['characters'] ??[]),
        'locations' => array_values(unique_objects_by_key($aggEntities['locations'] ??[])),
        'factions' => array_values(unique_objects_by_key($aggEntities['factions'] ??[])),
        'artifacts' => array_values(unique_objects_by_key($aggEntities['artifacts'] ??[])),
        'objects' => array_values(unique_objects_by_key($aggEntities['objects'] ??[])),
        'roles' => array_values(unique_objects_by_key($aggEntities['roles'] ??[]))
    ];
    $finalEntities = safe_json_encode($finalEntities_obj, JSON_UNESCAPED_UNICODE);

    $finalLore_arr = [
        'timeline_events' => array_slice(array_values(array_unique($aggLists['timeline'])), 0, 100),
        'technology_magic' => array_slice(array_values(array_unique($aggLists['tech_magic'])), 0, 100),
        'lore_rules' => array_slice(array_values(array_unique($aggLists['lore_rules'])), 0, 100),
        'anima_types' => array_slice(array_values(array_unique($aggLists['anima_types'])), 0, 50),
        'visual_details' => array_slice(array_values(array_unique($aggLists['visual_details'])), 0, 50),
        'trade_goods' => array_slice(array_values(array_unique($aggLists['trade_goods'])), 0, 50),
        'population_data' => array_slice(array_values(array_unique($aggLists['population_data'])), 0, 20)
    ];
    $finalLore = safe_json_encode($finalLore_arr, JSON_UNESCAPED_UNICODE);

    $finalThemes_arr =[
        'themes' => array_values(array_unique($aggLists['themes'])),
        'mood' => implode(', ', array_unique($aggLists['moods']))
    ];
    $finalThemes = safe_json_encode($finalThemes_arr, JSON_UNESCAPED_UNICODE);

    $finalShowrunner_arr = [];
    $finalShowrunner_arr['visual_keywords'] = array_slice(array_values(array_unique($aggShow['visual_keywords'])), 0, 50);
    $finalShowrunner_arr['scene_hooks'] = finalize_scene_hooks($aggShow['scene_hooks'] ?? []);
    $finalShowrunner_arr['production_notes'] = implode("\n\n---\n\n", array_map(function($n){ return safe_str($n['note'] ?? ''); }, $aggShow['notes']));
    $finalShowrunner_arr['readiness_score'] = !empty($aggShow['scores']) ? array_sum($aggShow['scores']) / count($aggShow['scores']) : 0;

    $bestNarr = select_best_narrative($aggShow['narrative'] ??[]);
    $finalShowrunner_arr['narrative_engine'] = $bestNarr;

    $finalShowrunner_arr['episode_concepts'] = finalize_episode_concepts($aggShow['episodes'] ??[]);

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
        $cast_brief[] = trim((($c['name'] ?? '') . ($c['roles'] ? ' — ' . implode(', ', $c['roles']) : '')));
    }
    $finalShowrunner_arr['cast_brief'] = $cast_brief;

    $finalShowrunner_arr['extracted_entities'] = $finalEntities_obj;

    $finalShowrunner = safe_json_encode($finalShowrunner_arr, JSON_UNESCAPED_UNICODE);
    $finalSummary = implode("\n\n", $aggLists['summary']);
    $finalScore = !empty($aggLists['scores']) ? array_sum($aggLists['scores']) / count($aggLists['scores']) : 0;

    $ins = $pdo->prepare("
        INSERT INTO md_doc_analysis 
        (doc_id, summary, entities, lore_points, thematics, narrative_utility, showrunner_analysis, series_bible, generator_config_id, target_collection)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            summary=VALUES(summary), 
            entities=VALUES(entities), 
            lore_points=VALUES(lore_points), 
            thematics=VALUES(thematics), 
            narrative_utility=VALUES(narrative_utility), 
            showrunner_analysis=VALUES(showrunner_analysis), 
            series_bible=VALUES(series_bible), 
            analyzed_at=NOW()
    ");

    $repo = $em->getRepository(GeneratorConfig::class);
    $configLore = $repo->findOneBy(['configId' => $curatorConfigId]);
    $cfgIdForSave = (method_exists($configLore,'getId')) ? $configLore->getId() : null;

    try {
        $ins->execute([
            $id,
            $finalSummary,
            $finalEntities,
            $finalLore,
            $finalThemes,
            $finalScore,
            $finalShowrunner,
            $readable_bible,
            $cfgIdForSave,
            $doc['target_collection'] ?? null
        ]);
    } catch (Exception $e) {
        $err = $e->getMessage();
        error_log("[md_curator_aggregate] DB INSERT FAILED for doc_id={$id}: {$err}");
        throw $e;
    }
    
    
    // =========================================================================
    // AUTOMATIC GRAPH (AG) POPULATION - RECURSIVE MARKDOWN & IMPLICIT EDGES
    // =========================================================================
    try {
        // Wipe isolated graph to ensure perfect idempotency on re-runs
        $pdo->prepare("DELETE FROM ag_node_items WHERE doc_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM ag_nodes WHERE doc_id = ?")->execute([$id]);

        $agEntitiesMap =[
            'character'  => $finalEntities_obj['characters'] ??[],
            'location'   => $finalEntities_obj['locations'] ??[],
            'faction'    => $finalEntities_obj['factions'] ??[],
            'artifact'   => $finalEntities_obj['artifacts'] ??[],
            'object'     => $finalEntities_obj['objects'] ??[],
            'role'       => $finalEntities_obj['roles'] ??[],
            'episode'    => $finalShowrunner_arr['episode_concepts'] ??[],
            'scene_hook' => $finalShowrunner_arr['scene_hooks'] ??[],
            'narrative'  => isset($finalShowrunner_arr['narrative_engine']) && is_array($finalShowrunner_arr['narrative_engine']) ?[$finalShowrunner_arr['narrative_engine']] :[]
        ];

        // HELPER: Deep Recursive Markdown Renderer (mirrors UI logic)
        $convert_entity_to_markdown = function($ent) use (&$convert_entity_to_markdown) {
            $render_recursive = function($item, $depth) use (&$render_recursive) {
                $indent = str_repeat('  ', $depth);
                if ($item === null) return "";
                if (is_scalar($item)) return "{$indent}" . trim((string)$item) . "\n";

                if (is_array($item) || is_object($item)) {
                    $itemArr = (array)$item;
                    if (empty($itemArr)) return "";
                    
                    $is_assoc = array_keys($itemArr) !== range(0, count($itemArr) - 1);

                    if (!$is_assoc) {
                        // Sequential array
                        $all_scalar = true;
                        foreach ($itemArr as $v) if (!is_scalar($v)) $all_scalar = false;

                        if ($all_scalar) {
                            $out = "";
                            foreach ($itemArr as $v) $out .= "{$indent}- " . trim((string)$v) . "\n";
                            return $out;
                        }

                        // Array of complex objects
                        $out = "";
                        foreach ($itemArr as $i => $sub) {
                            $out .= "{$indent}*Item " . ($i + 1) . "*\n";
                            $out .= $render_recursive($sub, $depth + 1);
                        }
                        return $out;
                    } else {
                        // Associative object
                        $out = "";
                        foreach ($itemArr as $k => $v) {
                            if ($v === null || $v === '') continue;

                            if (is_scalar($v)) {
                                $valStr = trim((string)$v);
                                if (strpos($valStr, "\n") === false && strlen($valStr) < 200) {
                                    $out .= "{$indent}- **" . ucfirst(str_replace('_', ' ', $k)) . ":** {$valStr}\n";
                                } else {
                                    $out .= "\n{$indent}**" . ucfirst(str_replace('_', ' ', $k)) . "**\n";
                                    $lines = explode("\n", $valStr);
                                    foreach ($lines as $line) $out .= "{$indent}> " . trim($line) . "\n";
                                    $out .= "\n";
                                }
                            } else {
                                $vArr = (array)$v;
                                if (empty($vArr)) continue;

                                $is_v_assoc = array_keys($vArr) !== range(0, count($vArr) - 1);
                                $all_scalar = true;
                                foreach ($vArr as $x) if (!is_scalar($x)) $all_scalar = false;

                                if (!$is_v_assoc && $all_scalar) {
                                    $out .= "{$indent}- **" . ucfirst(str_replace('_', ' ', $k)) . ":**\n";
                                    foreach ($vArr as $x) $out .= "{$indent}  - " . trim((string)$x) . "\n";
                                } else {
                                    $out .= "\n{$indent}**" . ucfirst(str_replace('_', ' ', $k)) . "**\n";
                                    $out .= $render_recursive($vArr, $depth + 1);
                                }
                            }
                        }
                        return $out;
                    }
                }
                return "{$indent}- " . json_encode($item) . "\n";
            };

            // Top level parsing
            $md = "";
            
            // 1. Core Descriptions (Hoist to top)
            $coreKeys =['description', 'logline', 'core_conflict', 'summary'];
            foreach ($coreKeys as $ck) {
                if (!empty($ent[$ck])) {
                    if (is_scalar($ent[$ck])) {
                        if ($ck === 'description') $md .= trim((string)$ent[$ck]) . "\n\n";
                        else $md .= "**" . ucfirst(str_replace('_', ' ', $ck)) . ":** " . trim((string)$ent[$ck]) . "\n\n";
                    } else {
                        $md .= "**" . ucfirst(str_replace('_', ' ', $ck)) . "**\n" . $render_recursive($ent[$ck], 0) . "\n";
                    }
                }
            }
            
            
            
            
            
           // 2. Identity / Aliases / Roles
            if (!empty($ent['aliases']) && is_array($ent['aliases'])) {
                $md .= "**Aliases:** " . implode(", ", array_filter($ent['aliases'], 'is_scalar')) . "\n\n";
            }
            if (!empty($ent['roles']) && is_array($ent['roles'])) {
                $md .= "**Roles:** " . implode(", ", array_filter($ent['roles'], 'is_scalar')) . "\n\n";
            }
            
            // 2b. For entities that went through merge_characters_canonical,
            //     attributes/timeline/relationships live in raw[] not at top level.
            //     Harvest them now so they survive into ag_nodes.content.
            if (empty($ent['attributes']) && empty($ent['timeline']) && empty($ent['relationships'])
                && !empty($ent['raw']) && is_array($ent['raw'])) {
                $mergedAttrs = [];
                $mergedTimeline = [];
                $mergedRels = [];
                foreach ($ent['raw'] as $chunk) {
                    if (!is_array($chunk)) continue;
                    if (!empty($chunk['attributes']) && is_array($chunk['attributes'])) {
                        $mergedAttrs = array_merge($mergedAttrs, $chunk['attributes']);
                    }
                    foreach (['timeline', 'events', 'history', 'actions'] as $tk) {
                        if (!empty($chunk[$tk]) && is_array($chunk[$tk])) {
                            $mergedTimeline = array_merge($mergedTimeline, $chunk[$tk]);
                        }
                    }
                    if (!empty($chunk['relationships']) && is_array($chunk['relationships'])) {
                        $mergedRels = array_merge($mergedRels, $chunk['relationships']);
                    }
                }
                if (!empty($mergedAttrs))    $ent['attributes']    = $mergedAttrs;
                if (!empty($mergedTimeline)) $ent['timeline']       = $mergedTimeline;
                if (!empty($mergedRels))     $ent['relationships']  = $mergedRels;
            }
            
            // 3. Attributes (Recursive)
            if (!empty($ent['attributes']) && is_array($ent['attributes'])) {
            
            
            
            /*
            
            // 2. Identity / Aliases / Roles
            if (!empty($ent['aliases']) && is_array($ent['aliases'])) {
                $md .= "**Aliases:** " . implode(", ", array_filter($ent['aliases'], 'is_scalar')) . "\n\n";
            }
            if (!empty($ent['roles']) && is_array($ent['roles'])) {
                $md .= "**Roles:** " . implode(", ", array_filter($ent['roles'], 'is_scalar')) . "\n\n";
            }
            
            // 3. Attributes (Recursive)
            if (!empty($ent['attributes']) && is_array($ent['attributes'])) {
                */
                
                
                
                
                $md .= "### Attributes\n";
                $md .= $render_recursive($ent['attributes'], 0) . "\n";
            }
            
            // 4. Timeline / Events / History
            $timelineItems = [];
            foreach (['timeline', 'events', 'history', 'actions'] as $tk) {
                if (!empty($ent[$tk]) && is_array($ent[$tk])) {
                    $timelineItems = array_merge($timelineItems, $ent[$tk]);
                }
            }
            
            if (!empty($timelineItems)) {
                $md .= "### Timeline & Events\n";
                $seenEvents =[]; 
                foreach ($timelineItems as $t) {
                    if (is_scalar($t)) {
                        if (!isset($seenEvents[(string)$t])) { 
                            $md .= "- " . trim((string)$t) . "\n"; 
                            $seenEvents[(string)$t] = true; 
                        }
                        continue;
                    }
                    if (is_array($t) || is_object($t)) {
                        $tArr = (array)$t;
                        $date = $tArr['date'] ?? $tArr['time'] ?? $tArr['year'] ?? $tArr['chapter'] ?? '';
                        $text = $tArr['text'] ?? $tArr['event'] ?? $tArr['description'] ?? $tArr['action'] ?? '';
                        
                        if (is_array($text) || is_object($text)) {
                            $text = trim($render_recursive($text, 0));
                        }
                        
                        $prefix = $date ? "**[{$date}]** " : "";
                        $hash = md5($prefix . (is_scalar($text) ? $text : json_encode($text)));
                        if ($text && !isset($seenEvents[$hash])) {
                            if (strpos($text, "\n") !== false) {
                                $md .= "- {$prefix}\n" . $render_recursive($tArr, 1);
                            } else {
                                $md .= "- {$prefix}{$text}\n";
                            }
                            $seenEvents[$hash] = true;
                        }
                    }
                }
                $md .= "\n";
            }
            
            // 5. Catch-all for Custom Keys (Deep Recursive)
            $exclude =['name', 'title', 'hook', 'scene', 'description', 'logline', 'core_conflict', 'summary', 'aliases', 'roles', 'attributes', 'timeline', 'events', 'history', 'relationships', 'raw', 'type', '__src_chunk', 'actions'];
            foreach ($ent as $k => $v) {
                if (in_array($k, $exclude)) continue;
                if ($v === null || $v === '') continue;
                
                $md .= "### " . ucfirst(str_replace('_', ' ', $k)) . "\n";
                $md .= $render_recursive($v, 0) . "\n";
            }
            
            return trim($md);
        };

        // PASS 1: Build Document-Scoped Alias Resolution Map
        $aliasMap =[];
        foreach ($agEntitiesMap as $agNodeType => $entitiesList) {
            foreach ($entitiesList as $ent) {
                if (!is_array($ent)) continue;
                $entName = trim($ent['name'] ?? $ent['title'] ?? $ent['hook'] ?? $ent['scene'] ?? '');
                if ($entName === '') continue;
                
                $aliasMap[strtolower($entName)] = $entName;
                
                if (!empty($ent['aliases']) && is_array($ent['aliases'])) {
                    foreach ($ent['aliases'] as $alias) {
                        $a = strtolower(trim($alias));
                        if (strlen($a) > 3) $aliasMap[$a] = $entName;
                    }
                }
            }
        }

        // PASS 2: Build Nodes and Edges
        foreach ($agEntitiesMap as $agNodeType => $entitiesList) {
            foreach ($entitiesList as $ent) {
                if (!is_array($ent)) continue;

                
                /*
                $entName = trim($ent['name'] ?? $ent['title'] ?? $ent['hook'] ?? $ent['scene'] ?? '');
                */
                
                
                
                
                //$entName = mb_substr(trim($ent['name'] ?? $ent['title'] ?? $ent['hook'] ?? $ent['scene'] ?? ''), 0, 1024, 'UTF-8');
                $entName = mb_substr(trim(is_array($tmp = ($ent['name'] ?? $ent['title'] ?? $ent['hook'] ?? $ent['scene'] ?? '')) ? json_encode($tmp, JSON_UNESCAPED_UNICODE) : (is_string($tmp) && ($j = json_decode($tmp, true)) !== null ? json_encode($j, JSON_UNESCAPED_UNICODE) : $tmp)), 0, 1024, 'UTF-8');
                
                
                
                
                if ($entName === '') continue;

                // Build robust, deeply nested Markdown text
                $entDesc = $convert_entity_to_markdown($ent);

                // 1. SMART CONCATENATION FOR NODE
                $stmtAg = $pdo->prepare("SELECT id, content FROM ag_nodes WHERE name = ? AND node_type = ? AND doc_id = ? LIMIT 1");
                $stmtAg->execute([$entName, $agNodeType, $id]);
                $existingAgNode = $stmtAg->fetch(PDO::FETCH_ASSOC);

                if ($existingAgNode) {
                    $agNodeId = $existingAgNode['id'];
                    $oldContent = trim($existingAgNode['content'] ?? '');

                    if ($entDesc !== '' && stripos($oldContent, substr($entDesc, 0, 50)) === false) {
                        $mergedContent = $oldContent;
                        if ($mergedContent !== '') $mergedContent .= "\n\n---\n\n";
                        $mergedContent .= $entDesc;

                        $pdo->prepare("UPDATE ag_nodes SET content = ?, updated_at = NOW() WHERE id = ?")
                            ->execute([$mergedContent, $agNodeId]);
                    }
                } else {
                    $pdo->prepare("INSERT INTO ag_nodes (doc_id, name, node_type, content) VALUES (?, ?, ?, ?)")
                        ->execute([$id, $entName, $agNodeType, $entDesc]);
                    $agNodeId = $pdo->lastInsertId();
                }

                $rels =[];

                // 2. EXPLICIT EDGE EXTRACTION
                $extractRels = function($src) use (&$rels, $aliasMap) {
                    if (!is_array($src) || empty($src['relationships'])) return;
                    
                    $rData = $src['relationships'];
                    if (is_array($rData)) {
                        if (array_keys($rData) !== range(0, count($rData) - 1)) {
                            foreach ($rData as $k => $v) {
                                if (is_string($k) && is_string($v)) {
                                    $rawTgt = str_replace('_', ' ', $k);
                                    $resolvedTgt = $aliasMap[strtolower(trim($rawTgt))] ?? $rawTgt;
                                    $rels[] =['target' => $resolvedTgt, 'type' => $v, 'note' => ''];
                                }
                            }
                        } else {
                            foreach ($rData as $rObj) {
                                if (is_array($rObj)) {
                                    $rawTgt = $rObj['target'] ?? $rObj['entity_2'] ?? $rObj['object'] ?? null;
                                    if ($rawTgt) {
                                        $resolvedTgt = $aliasMap[strtolower(trim($rawTgt))] ?? $rawTgt;
                                        $type = $rObj['type'] ?? $rObj['role'] ?? $rObj['relationship_type'] ?? '';
                                        $nature = $rObj['nature'] ?? $rObj['context'] ?? $rObj['relationship'] ?? '';
                                        $desc = $rObj['description'] ?? $rObj['action'] ?? $rObj['details'] ?? '';
                                        $note = trim("$nature: $desc", " : \t\n\r\0\x0B");
                                        $rels[] =['target' => $resolvedTgt, 'type' => $type, 'note' => $note];
                                    }
                                }
                            }
                        }
                    }
                };

                $extractRels($ent);
                if (!empty($ent['raw']) && is_array($ent['raw'])) {
                    foreach ($ent['raw'] as $chunk) $extractRels($chunk);
                }

                // 3. IMPLICIT MENTIONS SCANNING
                if ($entDesc !== '') {
                    $normalizedText = " " . preg_replace('/[^\p{L}\p{N}]/u', ' ', strtolower($entDesc)) . " ";
                    foreach ($aliasMap as $alias => $resolvedTarget) {
                        if ($resolvedTarget === $entName) continue;
                        if (strlen($alias) > 4) {
                            if (strpos($normalizedText, ' ' . $alias . ' ') !== false) {
                                $rels[] =['target' => $resolvedTarget, 'type' => 'mentions', 'note' => ''];
                            }
                        }
                    }
                }

                // 4. INSERT EDGES INTO ag_node_items
                $seenEdges =[]; 
                foreach ($rels as $rel) {
                    
                    /*
                    $tgtName = trim($rel['target']);
                    */
                    
                    //$tgtName = mb_substr(trim(safe_str($rel['target'])), 0, 1024, 'UTF-8');
                    
                    $tgtName = mb_substr(trim(is_array($tmp = $rel['target']) ? json_encode($tmp, JSON_UNESCAPED_UNICODE) : (is_string($tmp) && ($j = json_decode($tmp, true)) !== null ? json_encode($j, JSON_UNESCAPED_UNICODE) : $tmp)), 0, 1024, 'UTF-8');
                    
                    
                    if ($tgtName === '' || $tgtName === $entName) continue;

                    $edgeHash = md5($tgtName . '|' . $rel['type']);
                    if (isset($seenEdges[$edgeHash])) continue;
                    $seenEdges[$edgeHash] = true;

                    $stmtTgt = $pdo->prepare("SELECT id, node_type FROM ag_nodes WHERE name = ? AND doc_id = ? LIMIT 1");
                    $stmtTgt->execute([$tgtName, $id]);
                    $tgtRow = $stmtTgt->fetch(PDO::FETCH_ASSOC);

                    $itemId = $tgtRow ? $tgtRow['id'] : null;
                    $itemType = $tgtRow ? $tgtRow['node_type'] : 'unknown';

                    $stmtChk = $pdo->prepare("SELECT id FROM ag_node_items WHERE node_id = ? AND item_label = ? AND relationship = ? AND doc_id = ? LIMIT 1");
                    $stmtChk->execute([$agNodeId, $tgtName, $rel['type'], $id]);
                    
                    if (!$stmtChk->fetch()) {
                        $pdo->prepare("INSERT INTO ag_node_items (doc_id, node_id, item_type, item_id, item_label, relationship, note) VALUES (?, ?, ?, ?, ?, ?, ?)")
                            ->execute([$id, $agNodeId, $itemType, $itemId, $tgtName, $rel['type'], $rel['note']]);
                    }
                }
            }
        }
    } catch (Exception $e) {
        $err = $e->getMessage();
        error_log("[md_curator_aggregate] AG GRAPH POPULATION FAILED for doc_id={$id}: {$err}");
    }
    // =========================================================================
    
    
    
    
    
    
    
    
    /***
    
    
    // =========================================================================
    // AUTOMATIC GRAPH (AG) POPULATION - ISOLATED PER DOCUMENT
    // =========================================================================
    try {
        // Because this document is unlocked, we completely rebuild its isolated graph.
        // This ensures perfect idempotency (no duplicate edges/nodes on re-runs).
        $pdo->prepare("DELETE FROM ag_node_items WHERE doc_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM ag_nodes WHERE doc_id = ?")->execute([$id]);

        $agEntitiesMap =[
            // World Elements
            'character'  => $finalEntities_obj['characters'] ??[],
            'location'   => $finalEntities_obj['locations'] ??[],
            'faction'    => $finalEntities_obj['factions'] ??[],
            'artifact'   => $finalEntities_obj['artifacts'] ??[],
            'object'     => $finalEntities_obj['objects'] ??[],
            'role'       => $finalEntities_obj['roles'] ??[],
            // Story Elements
            'episode'    => $finalShowrunner_arr['episode_concepts'] ??[],
            'scene_hook' => $finalShowrunner_arr['scene_hooks'] ??[],
            'narrative'  => isset($finalShowrunner_arr['narrative_engine']) && is_array($finalShowrunner_arr['narrative_engine']) ?[$finalShowrunner_arr['narrative_engine']] :[]
        ];

        foreach ($agEntitiesMap as $agNodeType => $entitiesList) {
            foreach ($entitiesList as $ent) {
                if (!is_array($ent)) continue;

                $entName = trim($ent['name'] ?? $ent['title'] ?? $ent['hook'] ?? $ent['scene'] ?? '');
                $entDesc = trim($ent['description'] ?? $ent['logline'] ?? $ent['core_conflict'] ?? '');

                if ($entName === '') continue;

                // 1. SMART CONCATENATION FOR NODE (Scoped to this doc_id)
                $stmtAg = $pdo->prepare("SELECT id, content FROM ag_nodes WHERE name = ? AND node_type = ? AND doc_id = ? LIMIT 1");
                $stmtAg->execute([$entName, $agNodeType, $id]);
                $existingAgNode = $stmtAg->fetch(PDO::FETCH_ASSOC);

                if ($existingAgNode) {
                    $agNodeId = $existingAgNode['id'];
                    $oldContent = trim($existingAgNode['content'] ?? '');

                    if ($entDesc !== '' && stripos($oldContent, $entDesc) === false) {
                        $mergedContent = $oldContent;
                        if ($mergedContent !== '') $mergedContent .= "\n\n---\n\n";
                        $mergedContent .= $entDesc;

                        $pdo->prepare("UPDATE ag_nodes SET content = ?, updated_at = NOW() WHERE id = ?")
                            ->execute([$mergedContent, $agNodeId]);
                    }
                } else {
                    // Insert brand new AG Node assigned to this doc_id
                    $pdo->prepare("INSERT INTO ag_nodes (doc_id, name, node_type, content) VALUES (?, ?, ?, ?)")
                        ->execute([$id, $entName, $agNodeType, $entDesc]);
                    $agNodeId = $pdo->lastInsertId();
                }

                // 2. EDGE EXTRACTION (Relationships)
                $rels =[];
                $extractRels = function($src) use (&$rels) {
                    if (!is_array($src) || empty($src['relationships'])) return;
                    
                    $rData = $src['relationships'];
                    if (is_array($rData)) {
                        if (array_keys($rData) !== range(0, count($rData) - 1)) {
                            foreach ($rData as $k => $v) {
                                if (is_string($k) && is_string($v)) {
                                    $rels[] =['target' => str_replace('_', ' ', $k), 'type' => $v, 'note' => ''];
                                }
                            }
                        } else {
                            foreach ($rData as $rObj) {
                                if (is_array($rObj)) {
                                    $tgt = $rObj['target'] ?? $rObj['entity_2'] ?? $rObj['object'] ?? null;
                                    if ($tgt) {
                                        $type = $rObj['type'] ?? $rObj['role'] ?? $rObj['relationship_type'] ?? '';
                                        $nature = $rObj['nature'] ?? $rObj['context'] ?? $rObj['relationship'] ?? '';
                                        $desc = $rObj['description'] ?? $rObj['action'] ?? $rObj['details'] ?? '';
                                        $note = trim("$nature: $desc", " : \t\n\r\0\x0B");
                                        $rels[] =['target' => $tgt, 'type' => $type, 'note' => $note];
                                    }
                                }
                            }
                        }
                    }
                };

                $extractRels($ent);
                if (!empty($ent['raw']) && is_array($ent['raw'])) {
                    foreach ($ent['raw'] as $chunk) $extractRels($chunk);
                }

                // 3. INSERT EDGES INTO ag_node_items
                foreach ($rels as $rel) {
                    $tgtName = trim($rel['target']);
                    if ($tgtName === '') continue;

                    // Find target node scoped strictly to this doc_id
                    $stmtTgt = $pdo->prepare("SELECT id, node_type FROM ag_nodes WHERE name = ? AND doc_id = ? LIMIT 1");
                    $stmtTgt->execute([$tgtName, $id]);
                    $tgtRow = $stmtTgt->fetch(PDO::FETCH_ASSOC);

                    $itemId = $tgtRow ? $tgtRow['id'] : null;
                    $itemType = $tgtRow ? $tgtRow['node_type'] : 'unknown';

                    $stmtChk = $pdo->prepare("SELECT id FROM ag_node_items WHERE node_id = ? AND item_label = ? AND relationship = ? AND doc_id = ? LIMIT 1");
                    $stmtChk->execute([$agNodeId, $tgtName, $rel['type'], $id]);
                    
                    if (!$stmtChk->fetch()) {
                        $pdo->prepare("INSERT INTO ag_node_items (doc_id, node_id, item_type, item_id, item_label, relationship, note) VALUES (?, ?, ?, ?, ?, ?, ?)")
                            ->execute([$id, $agNodeId, $itemType, $itemId, $tgtName, $rel['type'], $rel['note']]);
                    }
                }
            }
        }
    } catch (Exception $e) {
        $err = $e->getMessage();
        error_log("[md_curator_aggregate] AG GRAPH POPULATION FAILED for doc_id={$id}: {$err}");
    }
    // =========================================================================
    
    
    ***/
    
    
    
    
    
    /*


    // =========================================================================
    // AUTOMATIC GRAPH (AG) POPULATION - SMART CONCATENATION
    // =========================================================================
    $agEntitiesMap = [
        'character' => $finalEntities_obj['characters'] ??[],
        'location'  => $finalEntities_obj['locations'] ?? [],
        'faction'   => $finalEntities_obj['factions'] ?? [],
        'artifact'  => $finalEntities_obj['artifacts'] ??[],
        'object'    => $finalEntities_obj['objects'] ??[],
        'role'      => $finalEntities_obj['roles'] ??[]
    ];

    foreach ($agEntitiesMap as $agNodeType => $entitiesList) {
        foreach ($entitiesList as $ent) {
            $entName = trim($ent['name'] ?? '');
            $entDesc = trim($ent['description'] ?? '');

            if ($entName === '' || $entDesc === '') {
                continue;
            }

            $stmtAg = $pdo->prepare("SELECT id, content FROM ag_nodes WHERE name = ? AND node_type = ? LIMIT 1");
            $stmtAg->execute([$entName, $agNodeType]);
            $existingAgNode = $stmtAg->fetch(PDO::FETCH_ASSOC);

            if ($existingAgNode) {
                $agNodeId = $existingAgNode['id'];
                $oldContent = trim($existingAgNode['content'] ?? '');

                // Smart Concatenation: Only append if the exact lore block isn't already in the content
                if (stripos($oldContent, $entDesc) === false) {
                    $mergedContent = $oldContent;
                    if ($mergedContent !== '') {
                        $mergedContent .= "\n\n---\n\n";
                    }
                    $mergedContent .= $entDesc;

                    $pdo->prepare("UPDATE ag_nodes SET content = ?, updated_at = NOW() WHERE id = ?")
                        ->execute([$mergedContent, $agNodeId]);
                }
            } else {
                // Insert brand new AG Node
                $pdo->prepare("INSERT INTO ag_nodes (name, node_type, content) VALUES (?, ?, ?)")
                    ->execute([$entName, $agNodeType, $entDesc]);
            }
        }
    }
    // =========================================================================

    
    */
    
    
    

    echo C_BLUE . "[ASSEMBLED & GRAPHED]" . C_RESET;
}
echo "\n--- Assembly Complete ---\n";