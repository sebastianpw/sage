<?php
// public/sketch_match_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\PyApiVectorService;

// Prevent HTML warnings from breaking JSON
error_reporting(E_ALL & ~E_WARNING & ~E_NOTICE & ~E_DEPRECATED);
ini_set('display_errors', 0);

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    // -------------------------------------------------------------------------
    // 1. FETCH SKETCHES (MariaDB SQL Search)
    // -------------------------------------------------------------------------
    if ($action === 'fetch_sketches') {
        $page = max(1, (int)($_POST['page'] ?? 1));
        $limit = 10; // Items per page in the horizontal header
        $offset = ($page - 1) * $limit;
        $query = trim($_POST['query'] ?? '');

        // 1. Build Query Conditions
        $where = "WHERE 1=1";
        $params = [];

        if (!empty($query)) {
            $where .= " AND (s.name LIKE :q OR s.description LIKE :q OR s.id = :id)";
            $params['q'] = "%$query%";
            $params['id'] = $query;
        }

        // 2. Count Total Items (For Pagination)
        $countSql = "SELECT COUNT(*) FROM sketches s $where";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalItems = $countStmt->fetchColumn();
        $totalPages = ceil($totalItems / $limit);

        // 3. Fetch Data
        $sql = "
            SELECT s.id, s.name, s.description, s.created_at,
                   (SELECT filename FROM frames WHERE entity_type='sketches' AND entity_id=s.id ORDER BY id DESC LIMIT 1) as thumb
            FROM sketches s
            $where
            ORDER BY s.created_at DESC 
            LIMIT $limit OFFSET $offset
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = array_map(function($r) {
            $vectorPayload = $r['name'];
            if (!empty($r['description'])) {
                $vectorPayload .= ". " . $r['description'];
            }
            return [
                'id' => $r['id'],
                'name' => $r['name'],
                'thumb' => $r['thumb'] ?? '/placeholder.png', 
                'desc' => $r['description'],
                'vector_text' => $vectorPayload
            ];
        }, $rows);

        echo json_encode([
            'ok' => true, 
            'items' => $results,
            'meta' => [
                'current_page' => $page,
                'total_pages' => $totalPages,
                'total_items' => $totalItems
            ]
        ]);
        exit;
    }

    // -------------------------------------------------------------------------
    // 2. SEARCH LORE (ChromaDB Vector Search)
    // -------------------------------------------------------------------------
    if ($action === 'search_lore') {
        
        $vectorService = new PyApiVectorService();
        $query = $_POST['query'] ?? ''; 
        $targetCollection = $_POST['collection'] ?? '';
        $page = max(1, (int)($_POST['page'] ?? 1));
        $limit = 20; 
        $offset = ($page - 1) * $limit;

        if (empty($query)) {
            echo json_encode(['ok' => true, 'items' => []]);
            exit;
        }

        $collectionsToSearch = [];
        if (!empty($targetCollection)) {
            $collectionsToSearch[] = $targetCollection;
        } else {
            $collStmt = $pdo->query("SELECT name FROM chroma_collections WHERE type = 'text' ORDER BY name ASC");
            $collectionsToSearch = $collStmt->fetchAll(PDO::FETCH_COLUMN);
        }

        $hits = [];
        
        foreach ($collectionsToSearch as $collName) {
            try {
                $res = $vectorService->query($query, null, $collName, 'text', 20);
                $hits = array_merge($hits, processChromaHits($res));
            } catch (Exception $e) { }
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
                $metaSql = "
                    SELECT d.id, d.name, c.name as category, da.narrative_utility
                    FROM documentations d
                    LEFT JOIN documentation_categories c ON d.category_id = c.id
                    JOIN md_doc_analysis da ON d.id = da.doc_id
                    WHERE d.id IN ($inList)
                ";
                $metaRows = $pdo->query($metaSql)->fetchAll(PDO::FETCH_ASSOC);
                
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
    
    // -------------------------------------------------------------------------
    // 3. FETCH FULL DOC (Modal Content)
    // -------------------------------------------------------------------------
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
        if (empty($bibleText) || strlen($bibleText) < 10) $bibleText = $row['raw_content'] ?? "";

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
            'meta' => [ 'doc_id' => $docId, 'name' => $row['doc_name'], 'cat' => $row['category_name'] ]
        ];
        
        echo json_encode(['ok' => true, 'data' => $masterJson]);
        exit;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

// --- Helpers ---

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
