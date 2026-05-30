<?php
// public/narratives_query_helpers_v11.php
// Shared query building helpers — used by narratives_api.php AND taggeranger_api.php
// DO NOT add headers, JSON output, routing, or side effects here.
// ──────────────────────────────────────────────────────────────────────────────
// V11: Adds optional KG graph-walker support.
// When $filterData['enable_kg_graph'] is true, each entity item gets a
// 1-degree KG edge lookup (outgoing + incoming) appended to its lore desc
// before enrichment. All existing behavior is 100% preserved when the flag
// is absent or false.
// ──────────────────────────────────────────────────────────────────────────────


use App\Entity\GeneratorConfig;
use App\Service\GeneratorService;
use App\Service\Schema\SchemaValidator;
use App\Service\Schema\ResponseNormalizer;
use App\Core\VectorContextEngine;
use App\Service\LoreAccessService;



// These globals are set by the caller before invoking enrichFilterQuery()
global $queryEnricherService, $queryEnricherConfig, $enricherInitError;
global $queryEnricherSeriesBible, $queryEnricherKeywords;


// ============================================================
// HELPER: Flatten nested arrays to a string (recursive)
// ============================================================
function flatten_array_values($array) {
    $flat = [];
    array_walk_recursive($array, function($a) use (&$flat) {
        if (is_scalar($a)) {
            $flat[] = trim((string)$a);
        }
    });
    return implode(", ", array_filter($flat));
}


// ============================================================
// HELPER: Universal field serializer
// ============================================================
function serializeField(string $key, $value): string {
    if (is_string($value)) {
        $value = trim($value);
        if (strlen($value) <= 3 || strlen($value) > 800) return '';
        return ucfirst($key) . ": " . $value . ". ";
    }

    if (is_numeric($value)) {
        return ucfirst($key) . ": " . $value . ". ";
    }

    if (is_array($value) && !empty($value)) {
        $stringItems = array_values(array_filter($value, 'is_string'));

        if (count($stringItems) === count($value)) {
            if (count($stringItems) >= 2) {
                $avgLen = array_sum(array_map('strlen', $stringItems)) / count($stringItems);
                if ($avgLen > 40) {
                    return "Beats (" . $key . "): " . implode(" | ", $stringItems) . ". ";
                } else {
                    return ucfirst($key) . ": " . implode(", ", $stringItems) . ". ";
                }
            }
            return ucfirst($key) . ": " . implode(", ", $stringItems) . ". ";
        }

        $flat = flatten_array_values($value);
        if ($flat) return ucfirst($key) . ": " . $flat . ". ";
    }

    return '';
}


// ==============================================================================
// HELPER: Enrich a single raw lore query string via AI
// ==============================================================================
function enrichFilterQuery(string $rawQuery, string $seriesBible, string $keywords, array &$debugMeta = []): string {
    global $queryEnricherService, $queryEnricherConfig, $enricherInitError;

    $debugMeta['start_time'] = microtime(true);
    $debugMeta['status'] = 'pending';

    if ($enricherInitError) {
        $debugMeta['status'] = 'skipped';
        $debugMeta['error']  = "Init Error: " . $enricherInitError;
        return $rawQuery;
    }
    if (!$queryEnricherService || !$queryEnricherConfig) {
        $debugMeta['status'] = 'skipped';
        $debugMeta['error']  = "Service or Config is null";
        return $rawQuery;
    }

    // Prepare Input with explicit sections
    $inputText = "=== FILTER ITEM ===\n" . $rawQuery;

    // Pass the Keywords specifically as vocabulary to use
    if (!empty($keywords)) {
        $inputText .= "\n\n=== MANDATORY WORLD VOCABULARY ===\n" . $keywords;
    }

    if (!empty($seriesBible)) {
        $inputText .= "\n\n=== WORLD CONTEXT (Flavor/Tone Only) ===\n" . $seriesBible;
    }

    // Generate
    try {
        $res = $queryEnricherService->generate(
            $queryEnricherConfig,
            ['entity_name' => $inputText]
        );

        $rawOut = is_object($res) && method_exists($res, 'getRawResponse')
            ? $res->getRawResponse()
            : (string)$res;

        $debugMeta['raw_response'] = substr($rawOut, 0, 200) . '...';

        $stripped = trim(str_replace(['```json', '```'], '', $rawOut));
        $decoded  = json_decode($stripped, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }

        if (!empty($decoded['enriched_query']) && is_string($decoded['enriched_query'])) {
            $debugMeta['status'] = 'success';
            $debugMeta['duration'] = round((microtime(true) - $debugMeta['start_time']) * 1000) . 'ms';
            return trim($decoded['enriched_query']);
        } else {
            throw new Exception("JSON missing 'enriched_query' key");
        }

    } catch (Exception $e) {
        $debugMeta['status'] = 'failed';
        $debugMeta['error']  = $e->getMessage();
        return $rawQuery;
    }
}


// ============================================================
// BUILD RICH QUERY — Universal catch-all lore builder
// ============================================================
// $debugLog is passed by reference. When DEBUG_MODE is true,
// each filter item appends an entry to it.
//
// V11: Accepts optional $filterData['enable_kg_graph'] (bool).
// When true, each entity item gets 1-degree KG edges appended
// to its lore description before enrichment. Completely
// backward-compatible — omitting the flag or setting it false
// produces identical output to the V10 version.
// ============================================================
function buildRichQuery($pdo, $docId, $filterData, array &$debugLog = []) {

    // 1. Global Search Mode (Text Only, No Doc Context)
    if (!$docId) {
        return $filterData['text'] ?? '';
    }

    // 2. V11: Parse feature flag
    $enableKgGraph = isset($filterData['enable_kg_graph']) ? (bool)$filterData['enable_kg_graph'] : false;

    // 3. Context-Aware Search
    $lore = new LoreAccessService($pdo);
    $lore->loadDoc($docId);

    $freeText    = trim($filterData['text'] ?? '');
    $items       = $filterData['items'] ?? [];
    $queryParts  = [];
    $perItem     = [];

    if (!empty($freeText)) {
        $queryParts[] = $freeText;
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $debugLog[] = [
                'label' => '(free text)',
                'cat'   => 'text',
                'query' => $freeText,
            ];
        }
    }

    if (!empty($items)) {
        $storyEngine = $lore->getStoryEngine();

        foreach ($items as $item) {
            $cat      = $item['cat']  ?? '';
            $name     = $item['name'] ?? '';
            $found    = false;
            $desc     = '';
            $edgeText = ''; // V11: KG edge text for this entity

            // --------------------------------------------------------
            // V11: GRAPH-WALKER — fetch 1-degree KG edges for entity
            // --------------------------------------------------------
            if ($enableKgGraph) {
                $kgStmt = $pdo->prepare(
                    "SELECT id FROM kg_nodes WHERE name = ? AND status = 'active' LIMIT 1"
                );
                $kgStmt->execute([$name]);
                $kgNodeId = $kgStmt->fetchColumn();

                if ($kgNodeId) {
                    $edgeLines = [];

                    // Outgoing edges
                    $outStmt = $pdo->prepare(
                        "SELECT item_type, item_label, relationship
                         FROM kg_node_items
                         WHERE node_id = ?
                         ORDER BY sort_order ASC"
                    );
                    $outStmt->execute([$kgNodeId]);
                    foreach ($outStmt->fetchAll(PDO::FETCH_ASSOC) as $edge) {
                        $rel = $edge['relationship'] ?: 'links to';
                        $edgeLines[] = "- [{$edge['item_type']}] {$edge['item_label']} ({$rel})";
                    }

                    // Incoming edges
                    $inStmt = $pdo->prepare("
                        SELECT n.node_type, n.name, i.relationship
                        FROM kg_node_items i
                        JOIN kg_nodes n ON n.id = i.node_id
                        WHERE i.item_type = 'kg_node' AND i.item_id = ? AND n.status = 'active'
                    ");
                    $inStmt->execute([$kgNodeId]);
                    foreach ($inStmt->fetchAll(PDO::FETCH_ASSOC) as $edge) {
                        $rel = $edge['relationship'] ?: 'is linked by';
                        $edgeLines[] = "- [Incoming {$edge['node_type']}] {$edge['name']} ({$rel})";
                    }

                    if (!empty($edgeLines)) {
                        $edgeText = "\nKnowledge Graph Connections:\n" . implode("\n", $edgeLines) . ". ";
                    }
                }
            }

            // --------------------------------------------------------
            // EPISODES
            // --------------------------------------------------------
            if ($cat === 'episodes') {
                $eps = $storyEngine['episodes'] ?? [];
                foreach ($eps as $ep) {
                    if (!is_array($ep)) continue;

                    $titleKeys = ['title', 'name', 'episode_title', 'ep_title', 'label', 'heading'];
                    $title = '';
                    foreach ($titleKeys as $tk) {
                        if (!empty($ep[$tk]) && is_string($ep[$tk])) {
                            $title = $ep[$tk];
                            break;
                        }
                    }
                    if (!$title) continue;

                    $match = stripos($title, $name) !== false
                          || stripos($name, $title) !== false;
                    if (!$match) continue;

                    $desc = "Episode: " . $title . ". ";

                    $explicit = [
                        'episode_number'     => 'Episode Number',
                        'logline'            => 'Logline',
                        'high_concept'       => 'Concept',
                        'narrative_function' => 'Function',
                        'emotional_arc'      => 'Emotional Arc',
                        'production_notes'   => 'Visual/Production',
                        'production_focus'   => 'Production Focus',
                        'description'        => null,
                        'act_structure'      => 'Structure',
                        'story_beats'        => 'Story Beats',
                        'themes'             => 'Themes',
                    ];
                    $handled = array_merge(array_keys($explicit), $titleKeys);

                    foreach ($explicit as $k => $label) {
                        if (empty($ep[$k])) continue;
                        $v = $ep[$k];
                        if ($label === null) {
                            $desc .= (is_string($v) ? trim($v) : flatten_array_values($v)) . ". ";
                        } elseif ($k === 'story_beats' && is_array($v)) {
                            $desc .= "Story Beats: " . implode(" | ", $v) . ". ";
                        } elseif ($k === 'themes' && is_array($v)) {
                            $desc .= "Themes: " . implode(", ", $v) . ". ";
                        } elseif ($k === 'act_structure') {
                            $desc .= "Structure: " . (is_array($v) ? implode(", ", $v) : $v) . ". ";
                        } elseif (is_string($v)) {
                            $desc .= $label . ": " . $v . ". ";
                        } elseif (is_numeric($v)) {
                            $desc .= $label . ": " . $v . ". ";
                        }
                    }

                    foreach ($ep as $k => $v) {
                        if (in_array($k, $handled)) continue;
                        $serialized = serializeField($k, $v);
                        if ($serialized) $desc .= $serialized;
                    }

                    $found = true;
                    break;
                }
            }

            // --------------------------------------------------------
            // SCENE HOOKS
            // --------------------------------------------------------
            elseif ($cat === 'scene_hooks') {
                $hooks = $storyEngine['scene_hooks'] ?? [];
                foreach ($hooks as $h) {
                    $title = $h['title'] ?? ($h['name'] ?? '');
                    if (!is_array($h) || !$title || stripos($name, $title) === false) continue;

                    $desc = "Scene Hook: " . $title . ". ";

                    $explicit = [
                        'description'        => null,
                        'visual_beat'        => 'Visual Beat',
                        'visual_signature'   => 'Visual Signature',
                        'narrative_function' => 'Function',
                        'emotional_tone'     => 'Tone',
                    ];
                    $handled = array_merge(array_keys($explicit), ['title', 'name']);

                    foreach ($explicit as $k => $label) {
                        if (empty($h[$k])) continue;
                        $v = $h[$k];
                        if ($label === null) {
                            $desc .= (is_string($v) ? trim($v) : flatten_array_values($v)) . ". ";
                        } elseif (is_string($v)) {
                            $desc .= $label . ": " . $v . ". ";
                        }
                    }

                    foreach ($h as $k => $v) {
                        if (in_array($k, $handled)) continue;
                        $serialized = serializeField($k, $v);
                        if ($serialized) $desc .= $serialized;
                    }

                    $found = true;
                    break;
                }
            }

            // --------------------------------------------------------
            // ALL OTHER ENTITIES (characters, factions, locations, artifacts, etc.)
            // --------------------------------------------------------
            else {
                $entity = $lore->getEntity($name);
                if ($entity) {
                    $desc = ucfirst($cat) . ": " . $entity['name'] . ". ";

                    if (!empty($entity['roles'])) {
                        $desc .= "Roles: " . implode(", ", (array)$entity['roles']) . ". ";
                    }

                    if (!empty($entity['attributes']) && is_array($entity['attributes'])) {
                        $attrExplicit = [
                            'description'      => null,
                            'summary'          => 'Summary',
                            'function'         => 'Function',
                            'purpose'          => 'Purpose',
                            'visual'           => 'Visual',
                            'appearance'       => 'Appearance',
                            'personality'      => 'Personality',
                            'motivation'       => 'Motivation',
                            'backstory'        => 'Backstory',
                            'abilities'        => 'Abilities',
                            'power'            => 'Power',
                            'weakness'         => 'Weakness',
                            'allegiance'       => 'Allegiance',
                            'location'         => 'Location',
                            'status'           => 'Status',
                            'significance'     => 'Significance',
                            'themes'           => 'Themes',
                            'production_notes' => 'Production Notes',
                        ];
                        $attrHandled = array_merge(array_keys($attrExplicit), ['id', 'type']);

                        foreach ($attrExplicit as $k => $label) {
                            if (empty($entity['attributes'][$k])) continue;
                            $v = $entity['attributes'][$k];
                            if ($label === null) {
                                $desc .= (is_string($v) ? trim($v) : flatten_array_values($v)) . ". ";
                            } else {
                                $serialized = serializeField($label, $v);
                                if ($serialized) $desc .= $serialized;
                            }
                        }

                        foreach ($entity['attributes'] as $k => $v) {
                            if (in_array($k, $attrHandled)) continue;
                            $serialized = serializeField($k, $v);
                            if ($serialized) $desc .= $serialized;
                        }
                    }

                    if (!empty($entity['relationships'])) {
                        $relParts = [];
                        foreach (array_slice($entity['relationships'], 0, 4) as $r) {
                            $rel = $r['target'] ?? '';
                            $typ = $r['type']   ?? '';
                            if ($rel) $relParts[] = $rel . ($typ ? " ($typ)" : "");
                        }
                        if ($relParts) $desc .= "Relations: " . implode(", ", $relParts) . ". ";
                    }

                    if (!empty($entity['timeline'])) {
                        $tlParts = [];
                        foreach (array_slice($entity['timeline'], 0, 3) as $t) {
                            if (!empty($t['text'])) $tlParts[] = $t['text'];
                        }
                        if ($tlParts) $desc .= "History: " . implode(". ", $tlParts) . ". ";
                    }

                    $entityHandled = ['name', 'roles', 'attributes', 'relationships', 'timeline', 'id', 'type', 'cat'];
                    foreach ($entity as $k => $v) {
                        if (in_array($k, $entityHandled)) continue;
                        $serialized = serializeField($k, $v);
                        if ($serialized) $desc .= $serialized;
                    }

                    $found = true;
                }
            }

            if ($found && $desc) {
                // V11: Append KG edge text if present
                if ($edgeText) $desc .= $edgeText;

                $queryParts[] = $desc;

                // Build the per-item query (free text prefix + lore block)
                $itemQuery = '';
                if ($freeText) $itemQuery .= $freeText . "\n\n";
                $itemQuery .= $desc;

                // Capture Debug Info for this specific item
                $enrichDebug = [];

                // Enrich via AI using Global Keyword List
                global $queryEnricherSeriesBible, $queryEnricherKeywords;
                $enrichedQuery = enrichFilterQuery(
                    $itemQuery,
                    $queryEnricherSeriesBible ?? '',
                    $queryEnricherKeywords ?? '',
                    $enrichDebug
                );

                $perItem[] = $enrichedQuery;

                // Debug entry
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    $debugEntry = [
                        'label'         => $name,
                        'cat'           => $cat,
                        'query'         => $enrichedQuery,
                        'raw_query'     => $itemQuery,
                        'was_enriched'  => ($enrichedQuery !== $itemQuery),
                    ];

                    if (isset($enrichDebug['status']) && $enrichDebug['status'] === 'failed') {
                        $debugEntry['warning'] = "AI Enrichment Failed: " . ($enrichDebug['error'] ?? 'Unknown Error');
                    } elseif (isset($enrichDebug['status']) && $enrichDebug['status'] === 'skipped') {
                        $debugEntry['warning'] = "AI Skipped: " . ($enrichDebug['error'] ?? 'Unknown reason');
                    }

                    $debugLog[] = $debugEntry;
                }

            } elseif (!$found) {
                $queryParts[] = $name;
                $fallbackQuery = ($freeText ? $freeText . "\n\n" : '') . $name;
                if ($edgeText) $fallbackQuery .= "\n" . $edgeText;
                $perItem[]    = $fallbackQuery;

                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    $debugLog[] = [
                        'label'         => $name,
                        'cat'           => $cat,
                        'query'         => $fallbackQuery,
                        'raw_query'     => $fallbackQuery,
                        'was_enriched'  => false,
                        'warning'       => 'Entity not found in lore index — used raw name as query',
                    ];
                }
            }
        }
    }

    // Always return the enriched item array if available,
    // even for a single item, to ensure the AI result is used.
    if (!empty($perItem)) {
        if (count($perItem) > 1) {
            return $perItem;
        } else {
            return $perItem[0];
        }
    }

    return implode("\n\n", array_filter($queryParts));
}
