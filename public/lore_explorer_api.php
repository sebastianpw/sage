<?php
// public/lore_explorer_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\PyApiVectorService;

// Prevent HTML warnings from breaking JSON
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);

header('Content-Type: application/json');

// Support both form POST and JSON body (lore_focused_export sends JSON)
$jsonInput = [];
$rawInput  = file_get_contents('php://input');
if (!empty($rawInput) && str_starts_with(trim($rawInput), '{')) {
    $jsonInput = json_decode($rawInput, true) ?? [];
}

$action = $_POST['action'] ?? ($jsonInput['action'] ?? '');
$page   = max(1, (int)($_POST['page'] ?? ($jsonInput['page'] ?? 1)));
$limit  = 20;
$offset = ($page - 1) * $limit;

try {
    // --- ACTION: SEARCH ---
    if ($action === 'search') {
        
        $vectorService = new PyApiVectorService();
        $query = $_POST['query'] ?? '';
        $targetCollection = $_POST['collection'] ?? '';
        
        // If empty query, show recent docs (Overview mode)
        if (empty($query)) {
            // FIX: JOIN enforces existence of analysis
            $sql = "
                SELECT d.id, d.name, d.updated_at, c.name as category, 
                       da.narrative_utility, da.summary
                FROM documentations d
                LEFT JOIN documentation_categories c ON d.category_id = c.id
                JOIN md_doc_analysis da ON d.id = da.doc_id 
                WHERE d.is_active = 1
            ";
            
            // Filter by collection if specified
            if (!empty($targetCollection)) {
                $sql .= " AND da.target_collection = :collection";
            }
            
            $sql .= " ORDER BY d.updated_at DESC LIMIT $limit OFFSET $offset";
            
            $stmt = $pdo->prepare($sql);
            if (!empty($targetCollection)) {
                $stmt->execute(['collection' => $targetCollection]);
            } else {
                $stmt->execute();
            }
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = array_map(function($r) {
                return [
                    'type' => 'overview',
                    'doc_id' => $r['id'],
                    'title' => $r['name'],
                    'category' => $r['category'],
                    'score' => (float)($r['narrative_utility'] ?? 0),
                    'match_type' => 'document',
                    'match_entity' => 'Recent Update',
                    'snippet' => mb_substr($r['summary'] ?? '', 0, 200) . '...',
                    'relevance' => 0
                ];
            }, $rows);
            
            echo json_encode(['ok' => true, 'items' => $results]);
            exit;
        }

        // --- VECTOR SEARCH ---
        $collectionsToSearch = [];
        
        if (!empty($targetCollection)) {
            $collectionsToSearch[] = $targetCollection;
        } else {
            $collStmt = $pdo->query("SELECT name FROM chroma_collections WHERE type = 'text' ORDER BY name ASC");
            $allCollections = $collStmt->fetchAll(PDO::FETCH_COLUMN);
            $collectionsToSearch = $allCollections;
        }

        $hits = [];
        
        foreach ($collectionsToSearch as $collName) {
            try {
                $res = $vectorService->query($query, null, $collName, 'text', 20);
                $hits = array_merge($hits, processChromaHits($res));
            } catch (Exception $e) {
                error_log("Vector search failed for collection '{$collName}': " . $e->getMessage());
            }
        }

        $results = [];
        if (!empty($hits)) {
            $uniqueHits = [];
            foreach ($hits as $h) {
                $k = $h['doc_id'] . '_' . $h['entity_name'];
                if (!isset($uniqueHits[$k])) $uniqueHits[$k] = $h;
                else if ($h['distance'] < $uniqueHits[$k]['distance']) $uniqueHits[$k] = $h;
            }
            $hits = array_values($uniqueHits);

            usort($hits, function($a, $b) { return $a['distance'] <=> $b['distance']; });
            $hits = array_slice($hits, $offset, $limit);

            $docIds = array_unique(array_column($hits, 'doc_id'));
            
            if (!empty($docIds)) {
                $inList = implode(',', $docIds);
                // FIX: JOIN enforces existence of analysis
                $metaSql = "
                    SELECT d.id, d.name, c.name as category, da.narrative_utility, da.target_collection
                    FROM documentations d
                    LEFT JOIN documentation_categories c ON d.category_id = c.id
                    JOIN md_doc_analysis da ON d.id = da.doc_id
                    WHERE d.id IN ($inList)
                ";
                
                if (!empty($targetCollection)) {
                    $metaSql .= " AND da.target_collection = :collection";
                }
                
                $metaStmt = $pdo->prepare($metaSql);
                if (!empty($targetCollection)) {
                    $metaStmt->execute(['collection' => $targetCollection]);
                } else {
                    $metaStmt->execute();
                }
                $metaRows = $metaStmt->fetchAll(PDO::FETCH_ASSOC);
                
                $metaMap = [];
                foreach ($metaRows as $r) $metaMap[$r['id']] = $r;

                foreach ($hits as $hit) {
                    $did = $hit['doc_id'];
                    if (isset($metaMap[$did])) {
                        $m = $metaMap[$did];
                        $results[] = [
                            'doc_id' => $did,
                            'title' => $m['name'],
                            'category' => $m['category'],
                            'score' => (float)($m['narrative_utility'] ?? 0),
                            'match_type' => $hit['subtype'],
                            'match_entity' => $hit['entity_name'],
                            'snippet' => $hit['snippet'],
                            'relevance' => (1 - $hit['distance'])
                        ];
                    }
                }
            }
        }

        echo json_encode(['ok' => true, 'items' => $results]);
        exit;
    }
    
    // --- ACTION: FETCH FULL DOC ---
    if ($action === 'fetch_doc_json') {
        $docId = (int)$_POST['doc_id'];
        
        $stmt = $pdo->prepare("
            SELECT d.id, d.name as doc_name, d.content as raw_content, 
                   c.name as category_name,
                   da.entities, da.showrunner_analysis, da.lore_points, da.thematics, 
                   da.series_bible, da.summary
            FROM documentations d
            LEFT JOIN documentation_categories c ON d.category_id = c.id
            LEFT JOIN md_doc_analysis da ON d.id = da.doc_id
            WHERE d.id = ?
        ");
        $stmt->execute([$docId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) { 
            echo json_encode(['ok' => false, 'error' => "Document ID $docId not found."]); 
            exit; 
        }
        
        $entities = json_decode($row['entities'] ?? '{}', true) ?? [];
        $showrunner = json_decode($row['showrunner_analysis'] ?? '{}', true) ?? [];
        $lorePoints = json_decode($row['lore_points'] ?? '{}', true) ?? [];
        $thematics = json_decode($row['thematics'] ?? '{}', true) ?? [];
        
        if (isset($entities['entities'])) $entities = $entities['entities'];

        $bibleText = $row['series_bible'];
        if (empty($bibleText) || strlen($bibleText) < 10) {
            $bibleText = $row['raw_content'] ?? "";
        }

        $curatorData = [
            'bible' => $bibleText,
            'production_notes' => $showrunner['production_notes'] ?? [],
            'themes' => $thematics['themes'] ?? [],
            'mood' => $thematics['mood'] ?? '',
            'summary' => $row['summary'] ?? ''
        ];

        foreach ($lorePoints as $key => $val) {
            if (!empty($val)) {
                $catName = ($key === 'timeline_events') ? 'timeline' : (($key === 'technology_magic') ? 'technology' : $key);
                $list = [];
                if (is_array($val)) foreach ($val as $v) $list[] = is_string($v) ? ['description'=>$v, 'name'=>'Entry'] : $v;
                if (!empty($list)) {
                    if (isset($entities[$catName])) $entities[$catName] = array_merge($entities[$catName], $list);
                    else $entities[$catName] = $list;
                }
            }
        }

        $storyData = [];
        if (!empty($showrunner['episode_concepts'])) $storyData['episodes'] = $showrunner['episode_concepts'];
        if (!empty($showrunner['narrative_engine'])) $storyData['narrative_engine'] = [$showrunner['narrative_engine']];
        if (!empty($showrunner['visual_keywords'])) $storyData['visual_keywords'] = $showrunner['visual_keywords'];
        if (!empty($showrunner['scene_hooks'])) $storyData['scene_hooks'] = $showrunner['scene_hooks'];

        $masterJson = [
            'curator' => $curatorData,
            'world' => $entities,
            'story' => $storyData,
            'meta' => [ 
                'doc_id' => $docId, 
                'name' => $row['doc_name'], 
                'cat' => $row['category_name'] 
            ]
        ];
        
        echo json_encode(['ok' => true, 'data' => $masterJson]);
        exit;
    }

    // -------------------------------------------------------
    // ACTION: LORE_SEMANTIC_QUERY
    // Phase 1 of the Lore Explorer context assembler.
    // Uses PyApiVectorService exactly like the working 'search'
    // action — same service, same processChromaHits(), same
    // metadata key assumptions. Deduplicates by doc_id keeping
    // best distance, then enriches from DB.
    // -------------------------------------------------------
    if ($action === 'lore_semantic_query') {
        $query     = trim($_POST['query'] ?? '');
        $nResults  = min((int)($_POST['n_results'] ?? 20), 60);
        $targetCol = trim($_POST['collection'] ?? '');

        if (!$query) {
            echo json_encode(['ok' => false, 'error' => 'Query required']);
            exit;
        }

        $vectorService = new PyApiVectorService();

        // Determine which collections to search — same logic as 'search' action
        if (!empty($targetCol)) {
            $collectionsToSearch = [$targetCol];
        } else {
            $collStmt = $pdo->query("SELECT name FROM chroma_collections WHERE type = 'text' ORDER BY name ASC");
            $collectionsToSearch = $collStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        // Gather raw hits via the same path the working search uses
        $rawHits = [];
        foreach ($collectionsToSearch as $collName) {
            try {
                $res      = $vectorService->query($query, null, $collName, 'text', $nResults);
                $newHits  = processChromaHits($res);
                $rawHits  = array_merge($rawHits, $newHits);
            } catch (Exception $e) {
                error_log("lore_semantic_query: vector search failed for '{$collName}': " . $e->getMessage());
            }
        }

        if (empty($rawHits)) {
            echo json_encode(['ok' => true, 'hits' => [], 'query' => $query, 'total' => 0]);
            exit;
        }

        // Deduplicate by doc_id + entity_name — same logic as the working 'search' action.
        // Collections are chunked per entity so many chunks share the same doc_id.
        // Collapsing by doc_id alone loses all but one entity per document.
        $uniqueHits = [];
        foreach ($rawHits as $h) {
            $k = $h['doc_id'] . '_' . $h['entity_name'];
            if (!isset($uniqueHits[$k]) || $h['distance'] < $uniqueHits[$k]['distance']) {
                $uniqueHits[$k] = $h;
            }
        }

        // Sort by distance ascending (closest = most relevant), then slice
        usort($uniqueHits, fn($a, $b) => $a['distance'] <=> $b['distance']);
        $uniqueHits = array_slice($uniqueHits, 0, $nResults);

        // Enrich with live DB metadata — one query for all referenced doc IDs
        $docIds       = array_unique(array_column($uniqueHits, 'doc_id'));
        $placeholders = implode(',', array_fill(0, count($docIds), '?'));

        $stmt = $pdo->prepare("
            SELECT d.id, d.name, d.updated_at,
                   c.id   AS category_id,
                   c.name AS category_name,
                   da.narrative_utility, da.target_collection, da.summary,
                   CHAR_LENGTH(COALESCE(d.content,'')) AS content_chars
            FROM documentations d
            LEFT JOIN documentation_categories c ON c.id = d.category_id
            LEFT JOIN md_doc_analysis da ON da.doc_id = d.id
            WHERE d.id IN ($placeholders) AND d.is_active = 1
        ");
        $stmt->execute($docIds);

        $dbRows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $dbRows[(int)$r['id']] = $r;
        }

        $hits = [];
        foreach ($uniqueHits as $hit) {
            $docId = $hit['doc_id'];
            $db    = $dbRows[$docId] ?? null;
            if (!$db) continue;

            $chars         = (int)($db['content_chars'] ?? 0);
            $contentStatus = match(true) {
                $chars === 0  => 'empty',
                $chars < 300  => 'stub',
                $chars < 800  => 'partial',
                default       => 'filled',
            };

            $score = max(0.0, 1.0 - ($hit['distance'] / 2.0));

            $hits[] = [
                'doc_id'            => $docId,
                'score'             => round($score, 4),
                'name'              => $db['name'],
                'category_id'       => (int)($db['category_id'] ?? 0),
                'category_name'     => $db['category_name'] ?? '',
                'match_type'        => $hit['subtype'],
                'match_entity'      => $hit['entity_name'],
                'summary'           => mb_substr($db['summary'] ?? '', 0, 200),
                'content_status'    => $contentStatus,
                'content_chars'     => $chars,
                'excerpt'           => $hit['snippet'],
                'narrative_utility' => (float)($db['narrative_utility'] ?? 0),
                'updated_at'        => $db['updated_at'],
            ];
        }

        echo json_encode(['ok' => true, 'hits' => $hits, 'query' => $query, 'total' => count($hits)]);
        exit;
    }

    // -------------------------------------------------------
    // ACTION: LORE_FOCUSED_EXPORT
    // Phase 2 of the Lore Explorer context assembler.
    // Receives matched_hits: [{doc_id, match_type, match_entity}]
    // Uses LoreAccessService to load each doc, then extracts ONLY
    // the matched entities by name+category — not the whole document.
    // Documents are grouped; each carries only its relevant nodes.
    // -------------------------------------------------------
    if ($action === 'lore_focused_export') {
        require_once dirname(__DIR__) . '/src/Service/LoreAccessService.php';

        // JSON body already parsed at top of file into $jsonInput
        $matchedHits = $jsonInput['matched_hits'] ?? [];
        $withContent = !empty($jsonInput['with_content']) && $jsonInput['with_content'] !== '0';

        if (empty($matchedHits) || !is_array($matchedHits)) {
            echo json_encode(['ok' => false, 'error' => 'matched_hits array required']);
            exit;
        }

        // Group hits by doc_id — each doc may have multiple matched entities
        $hitsByDoc = [];
        foreach ($matchedHits as $hit) {
            $docId = (int)($hit['doc_id'] ?? 0);
            if (!$docId) continue;
            $hitsByDoc[$docId][] = [
                'match_type'   => $hit['match_type']   ?? '',
                'match_entity' => $hit['match_entity'] ?? '',
            ];
        }

        if (empty($hitsByDoc)) {
            echo json_encode(['ok' => false, 'error' => 'No valid hits']);
            exit;
        }

        $docIds       = array_keys($hitsByDoc);
        $placeholders = implode(',', array_fill(0, count($docIds), '?'));

        // Fetch doc + category meta
        $metaStmt = $pdo->prepare("
            SELECT d.id, d.name, d.updated_at, d.keywords, d.desc_short,
                   c.id AS category_id, c.name AS category_name
            FROM documentations d
            LEFT JOIN documentation_categories c ON c.id = d.category_id
            WHERE d.id IN ($placeholders) AND d.is_active = 1
        ");
        $metaStmt->execute($docIds);
        $metaMap = [];
        foreach ($metaStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $metaMap[(int)$r['id']] = $r;
        }

        $service   = new \App\Service\LoreAccessService($pdo);
        $documents = [];
        $totalNodes = 0;

        foreach ($hitsByDoc as $docId => $hits) {
            $meta = $metaMap[$docId] ?? null;
            if (!$meta) continue;

            try {
                $service->loadDoc($docId);
            } catch (Exception $e) {
                $documents[] = [
                    'id'            => $docId,
                    'name'          => $meta['name'],
                    'category_name' => $meta['category_name'] ?? '',
                    'error'         => 'no_analysis',
                    'matched_nodes' => [],
                ];
                continue;
            }

            $curator = $service->getCuratorData();

            // Extract only the matched entities from this document.
            // match_type maps to the entity category key in the aggregated world
            // (e.g. "character" → "characters", or exact key as returned by chroma subtype).
            // match_entity is the entity name — use getEntity() for alias-aware lookup.
            $matchedNodes = [];
            $seen = [];

            foreach ($hits as $hit) {
                $entityName = $hit['match_entity'];
                $matchType  = $hit['match_type'];

                // Try direct name lookup first (alias-aware)
                $entity = $service->getEntity($entityName);

                if (!$entity) {
                    // Fallback: scan the category matching match_type for a name match
                    $categoryKeys = array_unique([
                        $matchType,
                        $matchType . 's',
                        rtrim($matchType, 's'),
                        str_replace('_', ' ', $matchType),
                    ]);
                    foreach ($categoryKeys as $catKey) {
                        $catEntities = $service->queryEntities($catKey);
                        foreach ($catEntities as $ent) {
                            $entName = strtolower(trim($ent['name'] ?? ''));
                            $hitName = strtolower(trim($entityName));
                            if ($entName === $hitName || str_contains($entName, $hitName) || str_contains($hitName, $entName)) {
                                $entity = $ent;
                                break 2;
                            }
                        }
                    }
                }

                if (!$entity) continue;

                $key = strtolower(trim($entity['name'] ?? $entityName));
                if (isset($seen[$key])) continue;
                $seen[$key] = true;

                // Always strip raw_source — it's pre-aggregation chunks, redundant in export
                unset($entity['raw_source']);

                // Structural mode (with_content=false): keep keys but strip verbose values
                // Full mode (with_content=true): include everything
                if (!$withContent) {
                    $node = [
                        'name'      => $entity['name'],
                        'category'  => $entity['category'] ?? $matchType,
                        '_matched_as' => $matchType,
                        'aliases'   => $entity['aliases'] ?? [],
                        'roles'     => $entity['roles']   ?? [],
                        // Attribute keys only — strip empty placeholder keys added by aggregateSingleEntity
                        'attribute_keys' => !empty($entity['attributes']) && is_array($entity['attributes'])
                            ? array_values(array_diff(
                                array_keys($entity['attributes']),
                                ['description', 'sources', 'attributes']
                              ))
                            : [],
                        'relationship_count' => count($entity['relationships'] ?? []),
                        'timeline_count'     => count($entity['timeline']      ?? []),
                    ];
                } else {
                    $node = array_merge($entity, ['_matched_as' => $matchType]);
                }

                $matchedNodes[] = $node;
            }

            $totalNodes += count($matchedNodes);

            $doc = [
                'id'                => $docId,
                'name'              => $meta['name'],
                'category_name'     => $meta['category_name'] ?? '',
                'updated_at'        => $meta['updated_at'],
                'summary'           => $curator['summary'] ?? '',
                'narrative_utility' => $curator['narrative_utility'] ?? 0,
                'themes'            => $curator['themes'] ?? [],
                'matched_nodes'     => $matchedNodes,
            ];

            // with_content detail lives at the entity level (attributes/relationships/timeline in matched_nodes)
            // series_bible and production_notes are document-level blobs — excluded to keep export focused

            $documents[] = $doc;
        }

        $snapshot = [
            'export_meta' => [
                'generated_at'    => date('c'),
                'export_type'     => 'lore_semantic_focused',
                'total_documents' => count($documents),
                'total_nodes'     => $totalNodes,
                'with_content'    => $withContent,
                'note'            => $withContent
                    ? 'Matched entities only. Full entity detail (attributes, relationships, timeline) included.'
                    : 'Matched entities only. Structural export — attribute keys only, no values.',
            ],
            'documents' => $documents,
        ];

        echo json_encode(['ok' => true, 'snapshot' => $snapshot]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

function processChromaHits($res) {
    $hits = [];
    if (empty($res['result']['ids'][0])) return [];
    
    $ids = $res['result']['ids'][0];
    $metas = $res['result']['metadatas'][0];
    $docs = $res['result']['documents'][0];
    $dists = $res['result']['distances'][0];
    
    foreach ($ids as $i => $id) {
        $meta = $metas[$i];
        $hits[] = [
            'doc_id' => $meta['db_id'],
            'subtype' => $meta['subtype'] ?? 'general',
            'entity_name' => extractEntityNameFromText($docs[$i], $meta['subtype']),
            'snippet' => extractSnippet($docs[$i]),
            'distance' => $dists[$i]
        ];
    }
    return $hits;
}

function extractSnippet($text) {
    $lines = explode("\n", $text);
    $clean = [];
    $capture = false;
    foreach ($lines as $line) {
        if (strpos($line, 'Description:') === 0 || strpos($line, 'Logline:') === 0 || strpos($line, 'SUMMARY:') === 0 || strpos($line, '---') !== false) $capture = true;
        if ($capture && trim($line) && strpos($line, '---') === false && strpos($line, 'Title:') === false) $clean[] = $line;
    }
    if (empty($clean)) return mb_substr($text, 0, 200) . '...';
    return mb_substr(implode(" ", $clean), 0, 300) . '...';
}

function extractEntityNameFromText($text, $subtype) {
    if ($subtype === 'overview') return 'Series Bible';
    if (preg_match('/(Entity|Episode|Location|Artifact):\s*(.*)$/m', $text, $m)) {
        return preg_replace('/^[\s:]+/', '', trim($m[2]));
    }
    return ucfirst($subtype);
}