<?php
/**
 * AI-Powered Search Endpoint
 *
 * New categories: docs, fuzz, kg, sequences, vector
 * New utility actions: chroma_ping, chroma_collections
 */

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

header('Content-Type: application/json');

// ── Utility GET actions (called directly by the UI) ──────────────────────────

$getAction = $_GET['action'] ?? null;

if ($getAction === 'chroma_ping') {
    // Lightweight ping to check if the ChromaDB PyAPI is reachable
    try {
        $vectorService = new \App\Core\PyApiVectorService();
        $result = $vectorService->ping(); // Using newly added PyApiVectorService ping()
        $online = is_array($result) ? !empty($result['ok']) : (bool)$result;
        echo json_encode(['online' => $online]);
    } catch (\Exception $e) {
        echo json_encode(['online' => false, 'message' => 'Vector database unreachable: ' . $e->getMessage()]);
    }
    exit;
}

if ($getAction === 'chroma_collections') {
    // Return all collections from DB so the UI can build the picker
    try {
        $stmt = $pdo->query("SELECT id, name, type, description, dimension FROM chroma_collections ORDER BY type ASC, name ASC");
        $collections = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['collections' => $collections]);
    } catch (\Exception $e) {
        echo json_encode(['collections' => [], 'error' => $e->getMessage()]);
    }
    exit;
}

// ── Main POST search handler ──────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = file_get_contents('php://input');
$data  = json_decode($input, true);
$GLOBALS['data'] = $data;

if (!isset($data['query']) || empty(trim($data['query']))) {
    echo json_encode(['error' => 'Query parameter is required']);
    exit;
}

$userQuery = trim($data['query']);
$category  = isset($data['category']) ? trim($data['category']) : 'general';

try {

    // ── Route by category ─────────────────────────────────────────────────

    // DOCS — plain LIKE search on documentations.content + name
    if ($category === 'docs') {
        $results = searchDocs($pdo, $userQuery);
        echo json_encode(['success' => true, 'results' => $results, 'query' => $userQuery]);
        exit;
    }

    // FUZZ — search fuzz_candidates
    if ($category === 'fuzz') {
        $results = searchFuzz($pdo, $userQuery);
        echo json_encode(['success' => true, 'results' => $results, 'query' => $userQuery]);
        exit;
    }

    // KG — search kg_nodes
    if ($category === 'kg') {
        $results = searchKgNodes($pdo, $userQuery);
        echo json_encode(['success' => true, 'results' => $results, 'query' => $userQuery]);
        exit;
    }

    // SEQUENCES — search narrative_sequences
    if ($category === 'sequences') {
        $results = searchSequences($pdo, $userQuery);
        echo json_encode(['success' => true, 'results' => $results, 'query' => $userQuery]);
        exit;
    }

    // VECTOR — ChromaDB semantic search
    if ($category === 'vector') {
        $collection = trim($data['vector_collection'] ?? '');
        if (!$collection) {
            echo json_encode(['error' => 'No collection specified for vector search.']);
            exit;
        }
        $results = searchVector($pdo, $userQuery, $collection);
        echo json_encode(['success' => true, 'results' => $results, 'query' => $userQuery]);
        exit;
    }

    // ── All existing categories (general + specific table searches) ───────

    $tableList = getTableList($pdo, $pdoSys, $dbname, $sysDbName);
    $searchStrategy = getAISearchStrategy($userQuery, $tableList, $category, $fileLogger ?? null);

    if (isset($searchStrategy['table']) && $searchStrategy['table'] === 'multi' && !empty($searchStrategy['tables'])) {
        $results = executeMultiTableSearch($searchStrategy, $pdo, $pdoSys);
    } else {
        $columnInfo = getTableColumns(
            $searchStrategy['database'] === 'sys_db' ? $pdoSys : $pdo,
            $searchStrategy['database'] === 'sys_db' ? $sysDbName : $dbname,
            $searchStrategy['table']
        );
        $results = executeSearchQueries($searchStrategy, $columnInfo, $pdo, $pdoSys);
    }

    echo json_encode([
        'success'  => true,
        'results'  => $results,
        'query'    => $userQuery,
        'strategy' => $searchStrategy['explanation'] ?? null
    ]);

} catch (Exception $e) {
    if (isset($fileLogger) && method_exists($fileLogger, 'error')) {
        $fileLogger->error(['AI Search Error' => $e->getMessage()]);
    }
    echo json_encode(['error' => 'Search failed: ' . $e->getMessage()]);
}

// ════════════════════════════════════════════════════════════════════════════
// NEW SEARCH HANDLERS
// ════════════════════════════════════════════════════════════════════════════

/**
 * DOCS — plain LIKE search across documentations.name + documentations.content
 */
function searchDocs($pdo, string $query): array
{
    $stmt = $pdo->prepare("
        SELECT d.id, d.name, d.created_at, d.updated_at,
               dc.name AS category_name,
               LEFT(d.content, 200) AS content_snippet
        FROM documentations d
        LEFT JOIN documentation_categories dc ON dc.id = d.category_id
        WHERE d.is_active = 1
          AND (d.name LIKE :q OR d.content LIKE :q)
        ORDER BY d.updated_at DESC
        LIMIT 100
    ");
    $stmt->execute(['q' => "%{$query}%"]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($rows as $row) {
        $snippet = trim(preg_replace('/\s+/', ' ', strip_tags($row['content_snippet'] ?? '')));
        if (mb_strlen($snippet) > 120) $snippet = mb_substr($snippet, 0, 117) . '…';

        $results[] = [
            'id'    => $row['id'],
            'table' => 'documentations',
            'title' => $row['name'],
            'meta'  => ($row['category_name'] ? $row['category_name'] . ' · ' : '')
                       . date('M j, Y', strtotime($row['updated_at']))
                       . ($snippet ? ' · ' . $snippet : ''),
            'url'   => 'view_md.php?id=' . $row['id'],
            'thumbnail' => null,
            'raw_data'  => $row
        ];
    }
    return $results;
}

/**
 * FUZZ — search fuzz_candidates
 */
function searchFuzz($pdo, string $query): array
{
    $stmt = $pdo->prepare("
        SELECT id, label, concept_type, status, created_at, updated_at,
               kg_node_id
        FROM fuzz_candidates
        WHERE label LIKE :q OR concept_type LIKE :q OR status LIKE :q
        ORDER BY updated_at DESC
        LIMIT 100
    ");
    $stmt->execute(['q' => "%{$query}%"]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($rows as $row) {
        $results[] = [
            'id'        => $row['id'],
            'table'     => 'fuzz_candidates',
            'title'     => $row['label'],
            'meta'      => 'Type: ' . $row['concept_type'] . ' · Status: ' . $row['status']
                           . ' · ' . date('M j, Y', strtotime($row['updated_at'])),
            'url'       => 'fuzz_forge_landing.php?candidate_id=' . $row['id'],
            'thumbnail' => null,
            'raw_data'  => $row
        ];
    }
    return $results;
}

/**
 * KG — search kg_nodes (name, node_type, keywords, summary)
 */
function searchKgNodes($pdo, string $query): array
{
    $stmt = $pdo->prepare("
        SELECT id, name, node_type, status, keywords, created_at, updated_at
        FROM kg_nodes
        WHERE status = 'active'
          AND (name LIKE :q OR node_type LIKE :q OR keywords LIKE :q)
        ORDER BY name ASC
        LIMIT 100
    ");
    $stmt->execute(['q' => "%{$query}%"]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($rows as $row) {
        $results[] = [
            'id'        => $row['id'],
            'table'     => 'kg_nodes',
            'title'     => $row['name'],
            'meta'      => 'Type: ' . $row['node_type']
                           . ($row['keywords'] ? ' · ' . mb_substr($row['keywords'], 0, 80) : ''),
            'url'       => 'kg_view.php?node_id=' . $row['id'],
            'thumbnail' => null,
            'raw_data'  => $row
        ];
    }
    return $results;
}

/**
 * SEQUENCES — search narrative_sequences
 */
function searchSequences($pdo, string $query): array
{
    $stmt = $pdo->prepare("
        SELECT id, name, description, created_at, updated_at
        FROM narrative_sequences
        WHERE name LIKE :q OR description LIKE :q
        ORDER BY id DESC
        LIMIT 100
    ");
    $stmt->execute(['q' => "%{$query}%"]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    foreach ($rows as $row) {
        $desc = mb_substr(strip_tags($row['description'] ?? ''), 0, 100);
        $results[] = [
            'id'        => $row['id'],
            'table'     => 'narrative_sequences',
            'title'     => $row['name'],
            'meta'      => ($desc ? $desc . ' · ' : '') . date('M j, Y', strtotime($row['updated_at'])),
            'url'       => 'narratives.php?sequence_id=' . $row['id'],
            'thumbnail' => null,
            'raw_data'  => $row
        ];
    }
    return $results;
}

/**
 * VECTOR — ChromaDB semantic search
 *
 * Resolves chunked text entries by consolidating unique underlying DB entities,
 * ensuring high-quality aggregated results identically ranked to sketches_viewer.php.
 */
function searchVector($pdo, string $query, string $collection): array
{
    $stmt = $pdo->prepare("SELECT type FROM chroma_collections WHERE name = ? LIMIT 1");
    $stmt->execute([$collection]);
    $collectionType = $stmt->fetchColumn() ?: 'text';

    // ──────────────────────────────────────────────────────────────────────────
    // MAGIC FIX: Replicate sketches_viewer.php exact behavior!
    // If the user queries 'sage_nu_images' with TEXT, CLIP text-to-image is used, 
    // which is imprecise for narrative semantics. sketches_viewer explicitly maps 
    // text queries to 'sage_sketches_nu' (MiniLM text embeddings). We do the same.
    // ──────────────────────────────────────────────────────────────────────────
    $queryCollection = $collection;
    $queryModality   = $collectionType;
    
    if ($collection === 'sage_nu_images') {
        $queryCollection = 'sage_sketches_nu';
        $queryModality   = 'text';
    }

    try {
        $vectorService = new \App\Core\PyApiVectorService();
        // Request 100 to ensure we have enough hits left over after collapsing chunks
        $chromaRes = $vectorService->query($query, null, $queryCollection, $queryModality, 100);
    } catch (\Exception $e) {
        throw new \Exception('Vector search failed: ' . $e->getMessage());
    }

    if (empty($chromaRes['result']['ids'][0])) {
        return [];
    }

    $rawIds    = $chromaRes['result']['ids'][0];
    $distances = $chromaRes['result']['distances'][0] ?? [];
    $metadatas = $chromaRes['result']['metadatas'][0] ?? [];

    $isSketch    = str_contains($collection, 'sketch');
    $isKgMeta    = str_contains($collection, 'kg_nodes');
    $isImage     = $collectionType === 'image';
    $isLore      = str_contains($collection, 'lore');

    $targetEntities = [];

    // 1. Group by entity ID and find MIN distance (exact method from sketches_viewer)
    foreach ($rawIds as $idx => $rid) {
        $dist = $distances[$idx] ?? 1.0;
        $meta = $metadatas[$idx] ?? [];
        
        $dbId = null;
        if (preg_match('/(?:sketch|frame|node|entity)_(\d+)/', $rid, $m)) {
            $dbId = (int)$m[1];
        } elseif (isset($meta['doc_id'])) {
            $dbId = (int)$meta['doc_id'];
        } elseif (isset($meta['sketch_id'])) {
            $dbId = (int)$meta['sketch_id'];
        } elseif (isset($meta['db_id'])) {
            $dbId = (int)$meta['db_id'];
        } elseif (preg_match('/_(\d+)$/', $rid, $m)) {
            $dbId = (int)$m[1];
        }

        $uniqKey = $dbId ? 'db_' . $dbId : 'raw_' . $rid;

        if (!isset($targetEntities[$uniqKey])) {
            $targetEntities[$uniqKey] = [
                'dbId' => $dbId,
                'rid'  => $rid,
                'dist' => $dist,
                'meta' => $meta
            ];
        } else {
            // Keep the one with the lowest distance (best match)
            if ($dist < $targetEntities[$uniqKey]['dist']) {
                $targetEntities[$uniqKey]['dist'] = $dist;
                $targetEntities[$uniqKey]['meta'] = $meta;
            }
        }
    }

    // 2. Sort ascending by distance (lowest distance = best match)
    uasort($targetEntities, function($a, $b) {
        return $a['dist'] <=> $b['dist'];
    });

    // 3. Slice to top 30 unique hits
    $targetEntities = array_slice($targetEntities, 0, 30);

    $results = [];

    // 4. Resolve database UI information for the winning entities
    foreach ($targetEntities as $hit) {
        $dbId = $hit['dbId'];
        $rid  = $hit['rid'];
        $dist = $hit['dist'];
        $meta = $hit['meta'];

        $result = [
            'id'        => $dbId,
            'table'     => $collection,
            'title'     => $rid,
            'meta'      => '', 
            'url'       => null,
            'thumbnail' => null,
            'score'     => null, // Omitted to avoid fake % conversions; relies purely on strict sorting
            'raw_data'  => $meta
        ];

        if ($isSketch && $dbId) {
            // Fetch Sketch Description AND its primary Frame thumbnail
            $row = $pdo->prepare("
                SELECT s.id, s.name, s.description,
                       (SELECT f.filename FROM frames_2_sketches f2s JOIN frames f ON f.id = f2s.from_id WHERE f2s.to_id = s.id ORDER BY f.id DESC LIMIT 1) as filename
                FROM sketches s WHERE s.id = ? LIMIT 1
            ");
            $row->execute([$dbId]);
            $r = $row->fetch(PDO::FETCH_ASSOC);
            
            $result['title']     = $r && $r['name'] ? $r['name'] : 'Sketch #' . $dbId;
            $descSnippet         = $r ? mb_substr($r['description'] ?? '', 0, 100) : '';
            $result['meta']      = $descSnippet . ' [Dist: ' . number_format($dist, 3) . ']';
            $result['thumbnail'] = $r ? $r['filename'] : null;
            $result['url']       = 'entity_form.php?entity_type=sketches&entity_id=' . $dbId . '&view=modal';
            $result['table']     = 'sketches';
        } elseif ($isKgMeta && $dbId) {
            $row = $pdo->prepare("SELECT id, name, node_type FROM kg_nodes WHERE id = ? LIMIT 1");
            $row->execute([$dbId]);
            $r = $row->fetch(PDO::FETCH_ASSOC);
            
            $result['title'] = $r ? $r['name'] : 'Node #' . $dbId;
            $result['meta']  = 'KG · ' . ($r['node_type'] ?? 'Unknown');
            $result['url']   = 'kg_view.php?node_id=' . $dbId;
            $result['table'] = 'kg_nodes';
        } elseif ($isImage && $dbId) {
            // If we hijacked sage_nu_images to sage_sketches_nu, $dbId is actually a SKETCH ID!
            // We must map it back to the best Frame ID.
            if ($queryCollection === 'sage_sketches_nu') {
                $row = $pdo->prepare("
                    SELECT f.id, f.filename, f.name 
                    FROM frames_2_sketches f2s 
                    JOIN frames f ON f.id = f2s.from_id 
                    WHERE f2s.to_id = ? 
                    ORDER BY f.id DESC LIMIT 1
                ");
                $row->execute([$dbId]);
                $r = $row->fetch(PDO::FETCH_ASSOC);
                
                if ($r) {
                    $result['id']        = $r['id'];
                    $result['title']     = $r['name'] ?: 'Frame from Sketch #' . $dbId;
                    $result['thumbnail'] = $r['filename'];
                    $result['url']       = 'view_frame.php?frame_id=' . $r['id'] . '&view=modal';
                } else {
                    $result['title'] = 'Frame for Sketch #' . $dbId;
                }
            } else {
                // Normal frame mapping
                $row = $pdo->prepare("SELECT id, filename, name FROM frames WHERE id = ? LIMIT 1");
                $row->execute([$dbId]);
                $r = $row->fetch(PDO::FETCH_ASSOC);
                
                $result['title']     = $r && $r['name'] ? $r['name'] : 'Frame #' . $dbId;
                $result['thumbnail'] = $r ? $r['filename'] : null;
                $result['url']       = 'view_frame.php?frame_id=' . $dbId . '&view=modal';
            }
            $result['meta']  = '[Dist: ' . number_format($dist, 3) . ']';
            $result['table'] = 'frames';
        } elseif ($isLore) {
            $entityName = $meta['name'] ?? $meta['entity_name'] ?? $rid;
            $result['title'] = $entityName;
            $result['meta']  = ($meta['type'] ?? '') . ' ' . ($meta['civilization'] ?? $meta['source'] ?? '');
            $result['table'] = 'lore_entity';
            if ($dbId) {
                $result['url'] = 'view_curated_docs.php?doc_id=' . $dbId . '&embed=1&focus_type=' . urlencode($meta['type'] ?? '') . '&focus_entity=' . urlencode($entityName);
            }
        }

        $results[] = $result;
    }

    return $results;
}

// ════════════════════════════════════════════════════════════════════════════
// EXISTING FUNCTIONS (preserved intact)
// ════════════════════════════════════════════════════════════════════════════

function getTableList($pdo, $pdoSys, $dbname, $sysDbName): array
{
    $tables = [
        'main_db' => ['name' => $dbname,    'tables' => []],
        'sys_db'  => ['name' => $sysDbName, 'tables' => []]
    ];
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) { $tables['main_db']['tables'][] = $row[0]; }
    $stmt = $pdoSys->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) { $tables['sys_db']['tables'][]  = $row[0]; }
    return $tables;
}

function getTableColumns($pdo, $dbname, $tableName): array
{
    $stmt = $pdo->prepare("
        SELECT COLUMN_NAME, DATA_TYPE, COLUMN_KEY, COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = :dbname AND TABLE_NAME = :tablename
        ORDER BY ORDINAL_POSITION
    ");
    $stmt->execute(['dbname' => $dbname, 'tablename' => $tableName]);
    $columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = ['name' => $row['COLUMN_NAME'], 'type' => $row['DATA_TYPE'], 'key' => $row['COLUMN_KEY'], 'full_type' => $row['COLUMN_TYPE']];
    }
    return $columns;
}

function getAISearchStrategy(string $userQuery, array $tableList, string $category, $fileLogger): array
{
    $tableMap = [
        'frames'      => ['main_db', 'frames'],
        'characters'  => ['main_db', 'characters'],
        'locations'   => ['main_db', 'locations'],
        'backgrounds' => ['main_db', 'backgrounds'],
        'sketches'    => ['main_db', 'sketches'],
        'artifacts'   => ['main_db', 'artifacts'],
        'vehicles'    => ['main_db', 'vehicles'],
        'storyboards' => ['main_db', 'storyboards'],
        'todos'       => ['sys_db',  'sage_todos'],
        'code'        => ['sys_db',  'code_classes'],
        'chat'        => ['main_db', 'chat_session']
    ];

    if ($category !== 'general' && isset($tableMap[$category])) {
        return [
            'database'    => $tableMap[$category][0],
            'table'       => $tableMap[$category][1],
            'explanation' => "User selected category: {$category}",
            'search_type' => 'text_search'
        ];
    }

    if ($category === 'general') {
        $tables = [];
        foreach ($tableMap as $key => $pair) {
            $dbKey = $pair[0];
            if (in_array($pair[1], $tableList[$dbKey]['tables'])) {
                $tables[] = ['database' => $pair[0], 'table' => $pair[1]];
            }
        }
        return [
            'table'       => 'multi',
            'tables'      => $tables,
            'explanation' => 'General: multi-table search across all categories',
            'search_type' => 'text_search'
        ];
    }

    // AI-assisted fallback
    $aiProvider = new \App\Core\AIProvider($fileLogger);
    $tableDescription = buildTableListDescription($tableList);
    $systemPrompt = <<<PROMPT
You are a database query assistant for a movie storyboard generation application. Given a user's search query and available database tables, determine the optimal search strategy.

DATABASES:
- main_db: Contains movie/storyboard entities (characters, locations, frames, scenes, etc.)
- sys_db: Contains system data (todos, code analysis, GPT conversations, etc.)

AVAILABLE TABLES:
{$tableDescription}

Respond ONLY with valid JSON:
{"database":"main_db or sys_db","table":"table_name","explanation":"brief reason","search_type":"text_search or id_lookup"}
PROMPT;
    $userPrompt = "User search query: \"{$userQuery}\"\n\nProvide the optimal table selection in JSON format.";
    try {
        $aiResponse = $aiProvider->sendPrompt(\App\Core\AIProvider::getDefaultModel(), $userPrompt, $systemPrompt, ['temperature' => 0.2, 'max_tokens' => 300]);
        $jsonMatch = [];
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $aiResponse, $jsonMatch)) {
            $strategy = json_decode($jsonMatch[0], true);
            if (json_last_error() === JSON_ERROR_NONE && isset($strategy['table'])) {
                $db = $strategy['database'] ?? 'main_db';
                if (in_array($strategy['table'], $tableList[$db]['tables'])) return $strategy;
            }
        }
        return getFallbackStrategy($userQuery, $tableList);
    } catch (Exception $e) {
        if (isset($fileLogger) && method_exists($fileLogger, 'error')) $fileLogger->error(['AI Strategy Error' => $e->getMessage()]);
        return getFallbackStrategy($userQuery, $tableList);
    }
}

function buildTableListDescription(array $tableList): string
{
    $description = "\nMAIN DATABASE:\n";
    foreach ($tableList['main_db']['tables'] as $table) {
        if (strpos($table, 'frames_2_') === false) $description .= "  - {$table}\n";
    }
    $description .= "\nSYS DATABASE:\n";
    foreach ($tableList['sys_db']['tables'] as $table) { $description .= "  - {$table}\n"; }
    return $description;
}

function getFallbackStrategy(string $userQuery, array $tableList): array
{
    $queryLower = strtolower($userQuery);
    $patterns = [
        'todo|task'                         => ['sys_db',  'sage_todos'],
        'code|file|class|function|method'   => ['sys_db',  'code_classes'],
        'chat|conversation'                 => ['main_db', 'chat_session'],
        'character|person|hero'             => ['main_db', 'characters'],
        'location|place|setting'            => ['main_db', 'locations'],
        'frame|image|picture'               => ['main_db', 'frames'],
        'sketch|concept'                    => ['main_db', 'sketches'],
        'storyboard|sequence'               => ['main_db', 'storyboards'],
        'vehicle|ship|transport'            => ['main_db', 'vehicles'],
        'artifact|item|object'              => ['main_db', 'artifacts'],
        'background|scene'                  => ['main_db', 'backgrounds'],
    ];
    foreach ($patterns as $pattern => $target) {
        if (preg_match('/\b(' . $pattern . ')\b/i', $queryLower)) {
            return ['database' => $target[0], 'table' => $target[1], 'explanation' => 'Fallback: keyword match', 'search_type' => 'text_search'];
        }
    }
    return ['database' => 'main_db', 'table' => 'frames', 'explanation' => 'Fallback: default to frames', 'search_type' => 'text_search'];
}

function executeSearchQueries(array $strategy, array $columnInfo, $pdo, $pdoSys): array
{
    $db = ($strategy['database'] === 'sys_db') ? $pdoSys : $pdo;
    $table = $strategy['table'];
    $data = $GLOBALS['data'] ?? [];
    $searchTerm = trim($data['query'] ?? '');
    $isNum = is_numeric($searchTerm);

    if ($table === 'chat_session') {
        $sql = "SELECT DISTINCT cs.*, cm.id AS message_id, cm.content AS match_content 
                FROM `chat_session` cs LEFT JOIN `chat_message` cm ON cm.session_id = cs.id 
                WHERE cs.title LIKE :search OR cm.content LIKE :search " . ($isNum ? " OR cs.id = :exactId" : "") . " 
                ORDER BY " . ($isNum ? "(cs.id = :exactId) DESC, " : "") . "cs.id DESC LIMIT 500";
        $stmt = $db->prepare($sql);
        $params = ['search' => "%{$searchTerm}%"];
        if ($isNum) $params['exactId'] = (int)$searchTerm;
        $stmt->execute($params);
        return formatResults($stmt->fetchAll(PDO::FETCH_ASSOC), 'chat_session', $strategy['database']);
    }

    if ($table === 'code_classes') return searchCodeClasses($db, $pdo, $pdoSys);

    $searchableColumns = [];
    $hasId = false;
    foreach ($columnInfo as $col) {
        $type = strtolower($col['type']); $name = $col['name'];
        if ($name === 'id') $hasId = true;
        if (in_array($type, ['varchar', 'text', 'mediumtext', 'longtext', 'char'])) {
            if (in_array($name, ['name', 'description', 'content', 'text', 'prompt', 'summary', 'title', 'content_text', 'class_name'])) {
                $searchableColumns[] = $name;
            } elseif (!in_array($name, ['filename', 'path', 'hash', 'external_id', 'session_id', 'config_id', 'file_hash'])) {
                $searchableColumns[] = $name;
            }
        }
    }

    if (empty($searchableColumns)) {
        $rows = $db->query("SELECT * FROM `{$table}` ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        return formatResults($rows, $table, $strategy['database']);
    }

    $whereParts = array_map(fn($c) => "`{$c}` LIKE :search", $searchableColumns);
    $params = ['search' => "%{$searchTerm}%"];
    $orderBy = "id DESC";

    if ($hasId && $isNum) {
        $exactId = (int)$searchTerm;
        array_unshift($whereParts, "`id` = :exactId");
        $params['exactId'] = $exactId;
        // Boost exactly matching ID to the absolute top
        $orderBy = "(`id` = {$exactId}) DESC, id DESC";
    }

    $sql = "SELECT * FROM `{$table}` WHERE " . implode(' OR ', $whereParts) . " ORDER BY {$orderBy} LIMIT 500";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return formatResults($stmt->fetchAll(PDO::FETCH_ASSOC), $table, $strategy['database']);
}

function executeMultiTableSearch(array $strategy, $pdo, $pdoSys): array
{
    $data = $GLOBALS['data'] ?? [];
    $searchTerm = trim($data['query'] ?? '');
    $tables = $strategy['tables'] ?? [];
    $perTableLimit = 50;
    $aggregateResults = [];

    foreach ($tables as $t) {
        $dbRef = ($t['database'] === 'sys_db') ? $pdoSys : $pdo;
        $tableName = $t['table'];
        $miniStrategy = ['database' => $t['database'], 'table' => $tableName, 'explanation' => 'Multi-search', 'search_type' => 'text_search'];

        if ($tableName === 'chat_session') {
            try {
                $isNum = is_numeric($searchTerm);
                $sql = "SELECT DISTINCT cs.*, cm.id AS message_id, cm.content AS match_content 
                        FROM `chat_session` cs LEFT JOIN `chat_message` cm ON cm.session_id = cs.id 
                        WHERE cs.title LIKE :search OR cm.content LIKE :search " . ($isNum ? " OR cs.id = :exactId" : "") . " 
                        ORDER BY " . ($isNum ? "(cs.id = :exactId) DESC, " : "") . "cs.id DESC LIMIT :lim";
                $stmt = $dbRef->prepare($sql);
                $stmt->bindValue(':search', "%{$searchTerm}%");
                if ($isNum) $stmt->bindValue(':exactId', (int)$searchTerm, PDO::PARAM_INT);
                $stmt->bindValue(':lim', (int)$perTableLimit, PDO::PARAM_INT);
                $stmt->execute();
                $aggregateResults = array_merge($aggregateResults, formatResults($stmt->fetchAll(PDO::FETCH_ASSOC), 'chat_session', $t['database']));
            } catch (Exception $e) {}
            continue;
        }
        if ($tableName === 'code_classes') {
            try { $aggregateResults = array_merge($aggregateResults, searchCodeClasses($dbRef, $pdo, $pdoSys)); } catch (Exception $e) {}
            continue;
        }
        try {
            $colInfo = getTableColumns($dbRef, ($t['database'] === 'sys_db' ? $GLOBALS['sysDbName'] : $GLOBALS['dbname']), $tableName);
            $sub = executeSearchQueries($miniStrategy, $colInfo, $pdo, $pdoSys);
            if (is_array($sub)) $aggregateResults = array_merge($aggregateResults, array_slice($sub, 0, $perTableLimit));
        } catch (Exception $e) {}
    }

    $seen = []; $unique = [];
    foreach ($aggregateResults as $r) {
        $key = ($r['table'] ?? 'unknown') . ':' . ($r['id'] ?? uniqid('noid_', true));
        if (!isset($seen[$key])) { $seen[$key] = true; $unique[] = $r; }
    }
    
    usort($unique, function($a, $b) use ($searchTerm) {
        // Force exact ID matches to float entirely to the top across all tables
        $isAExact = (is_numeric($searchTerm) && isset($a['id']) && $a['id'] == $searchTerm) ? 1 : 0;
        $isBExact = (is_numeric($searchTerm) && isset($b['id']) && $b['id'] == $searchTerm) ? 1 : 0;
        
        if ($isAExact !== $isBExact) {
            return $isBExact <=> $isAExact; 
        }

        $ad = $a['raw_data']['created_at'] ?? $a['raw_data']['updated_at'] ?? null;
        $bd = $b['raw_data']['created_at'] ?? $b['raw_data']['updated_at'] ?? null;
        $at = $ad ? strtotime($ad) : null; $bt = $bd ? strtotime($bd) : null;
        if ($at === $bt) return ($b['id'] ?? 0) <=> ($a['id'] ?? 0);
        if ($at === null) return 1; if ($bt === null) return -1;
        return $bt <=> $at;
    });
    return array_slice($unique, 0, 200);
}

function searchCodeClasses($db, $pdo, $pdoSys): array
{
    $data = $GLOBALS['data'] ?? [];
    $searchTerm = trim($data['query'] ?? '');
    $isNum = is_numeric($searchTerm);
    
    $sql = "SELECT cc.id, cc.file_id, cc.class_name, cc.methods, cc.extends_class, cc.summary, cf.path, cf.file_hash 
            FROM code_classes cc INNER JOIN code_files cf ON cc.file_id = cf.id 
            WHERE cc.class_name LIKE :search OR cc.summary LIKE :search OR cc.methods LIKE :search OR cf.path LIKE :search " .
            ($isNum ? " OR cc.id = :exactId" : "") . " 
            ORDER BY " . ($isNum ? "(cc.id = :exactId) DESC, " : "") . "cc.id DESC LIMIT 500";
    
    $stmt = $db->prepare($sql);
    $params = ['search' => "%{$searchTerm}%"];
    if ($isNum) $params['exactId'] = (int)$searchTerm;
    $stmt->execute($params);
    
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $results = [];
    foreach ($rows as $row) {
        $results[] = ['id' => $row['id'], 'table' => 'code_classes', 'database' => 'sys_db', 'title' => $row['class_name'] ?? basename($row['path'] ?? 'Unknown'), 'meta' => isset($row['path']) ? basename($row['path']) : '', 'url' => "codeboard.php?file_id=" . $row['file_id'], 'raw_data' => $row];
    }
    return $results;
}

function formatResults(array $rows, string $table, string $database): array
{
    $results = [];
    foreach ($rows as $row) {
        $result = ['id' => $row['id'] ?? null, 'table' => $table, 'database' => $database, 'title' => '', 'meta' => '', 'url' => null, 'thumbnail' => null, 'raw_data' => $row];
        if ($table === 'frames' && isset($row['filename'])) $result['thumbnail'] = $row['filename'];
        if (isset($row['name']) && !empty($row['name'])) $result['title'] = $row['name'];
        elseif (isset($row['title']) && !empty($row['title'])) $result['title'] = $row['title'];
        elseif (isset($row['class_name']) && !empty($row['class_name'])) $result['title'] = $row['class_name'];
        elseif (isset($row['description']) && !empty($row['description'])) $result['title'] = mb_substr($row['description'], 0, 60) . (strlen($row['description']) > 60 ? '…' : '');
        elseif (isset($row['prompt']) && !empty($row['prompt'])) $result['title'] = mb_substr($row['prompt'], 0, 60) . '…';
        elseif (isset($row['content_text']) && !empty($row['content_text'])) $result['title'] = mb_substr($row['content_text'], 0, 60) . '…';
        elseif (isset($row['filename'])) $result['title'] = basename($row['filename']);
        elseif (isset($row['path'])) $result['title'] = basename($row['path']);
        else $result['title'] = ucfirst($table) . " #" . ($row['id'] ?? 'unknown');
        if (isset($row['created_at'])) $result['meta'] = date('M j, Y', strtotime($row['created_at']));
        elseif (isset($row['updated_at'])) $result['meta'] = date('M j, Y', strtotime($row['updated_at']));
        if (isset($row['type']) && !empty($row['type'])) $result['meta'] .= ($result['meta'] ? ' · ' : '') . $row['type'];
        elseif (isset($row['role']) && !empty($row['role'])) $result['meta'] .= ($result['meta'] ? ' · ' : '') . $row['role'];
        elseif (isset($row['status']) && !empty($row['status'])) $result['meta'] .= ($result['meta'] ? ' · ' : '') . $row['status'];
        elseif (isset($row['model']) && !empty($row['model'])) $result['meta'] .= ($result['meta'] ? ' · ' : '') . $row['model'];
        if ($table === 'chat_session' && !empty($row['match_content'])) {
            $snippet = trim(preg_replace('/\s+/', ' ', strip_tags($row['match_content'])));
            if (mb_strlen($snippet) > 160) $snippet = mb_substr($snippet, 0, 157) . '…';
            $result['meta'] .= ($result['meta'] ? ' · ' : '') . $snippet;
        }
        $result['url'] = generateResultUrl($table, $row);
        $results[] = $result;
    }
    return $results;
}

function generateResultUrl(string $table, array $row): ?string
{
    $id = $row['id'] ?? null;
    if (!$id) return null;
    $urlMap = [
        'frames'      => "view_frame.php?frame_id={$id}",
        'characters'  => "entity_form.php?entity_type=characters&entity_id={$id}",
        'locations'   => "entity_form.php?entity_type=locations&entity_id={$id}",
        'backgrounds' => "entity_form.php?entity_type=backgrounds&entity_id={$id}",
        'sketches'    => "entity_form.php?entity_type=sketches&entity_id={$id}",
        'artifacts'   => "entity_form.php?entity_type=artifacts&entity_id={$id}",
        'vehicles'    => "entity_form.php?entity_type=vehicles&entity_id={$id}",
        'storyboards' => "view_storyboard.php?id={$id}",
        'sage_todos'  => "todo.php?id={$id}",
        'code_classes'=> "codeboard.php?file_id=" . urlencode($row['id'] ?? ''),
        'chat_session'=> "chat.php?session_id=" . urlencode($row['session_id'] ?? '')
    ];
    return $urlMap[$table] ?? null;
}