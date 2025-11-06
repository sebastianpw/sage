<?php
/**
 * AI-Powered Search Endpoint
 * 
 * Receives search queries, uses AI to determine which database tables to query,
 * executes the queries, and returns formatted results.
 */

require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get and validate input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['query']) || empty(trim($data['query']))) {
    echo json_encode(['error' => 'Query parameter is required']);
    exit;
}

$userQuery = trim($data['query']);
$category = isset($data['category']) ? trim($data['category']) : 'general';

try {
    // Get lightweight table list (just names, not full schema)
    $tableList = getTableList($pdo, $pdoSys, $dbname, $sysDbName);
    
    // Use AI to determine search strategy (with category hint)
    $searchStrategy = getAISearchStrategy($userQuery, $tableList, $category, $fileLogger);
    
    // Get detailed column info for the chosen table only
    $columnInfo = getTableColumns(
        $searchStrategy['database'] === 'sys_db' ? $pdoSys : $pdo,
        $searchStrategy['database'] === 'sys_db' ? $sysDbName : $dbname,
        $searchStrategy['table']
    );
    
    // Execute queries based on AI strategy
    $results = executeSearchQueries($searchStrategy, $columnInfo, $pdo, $pdoSys);
    
    // Format and return results
    echo json_encode([
        'success' => true,
        'results' => $results,
        'query' => $userQuery,
        'strategy' => $searchStrategy['explanation'] ?? null
    ]);
    
} catch (Exception $e) {
    $fileLogger->error(['AI Search Error' => $e->getMessage()]);
    echo json_encode([
        'error' => 'Search failed: ' . $e->getMessage()
    ]);
}

/**
 * Get just table names from both databases (lightweight)
 */
function getTableList($pdo, $pdoSys, $dbname, $sysDbName): array
{
    $tables = [
        'main_db' => [
            'name' => $dbname,
            'tables' => []
        ],
        'sys_db' => [
            'name' => $sysDbName,
            'tables' => []
        ]
    ];
    
    // Get main database tables
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables['main_db']['tables'][] = $row[0];
    }
    
    // Get sys database tables
    $stmt = $pdoSys->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables['sys_db']['tables'][] = $row[0];
    }
    
    return $tables;
}

/**
 * Get detailed column information for a specific table using INFORMATION_SCHEMA
 */
function getTableColumns($pdo, $dbname, $tableName): array
{
    $sql = "
        SELECT 
            COLUMN_NAME,
            DATA_TYPE,
            COLUMN_KEY,
            COLUMN_TYPE
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = :dbname 
          AND TABLE_NAME = :tablename
        ORDER BY ORDINAL_POSITION
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'dbname' => $dbname,
        'tablename' => $tableName
    ]);
    
    $columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = [
            'name' => $row['COLUMN_NAME'],
            'type' => $row['DATA_TYPE'],
            'key' => $row['COLUMN_KEY'],
            'full_type' => $row['COLUMN_TYPE']
        ];
    }
    
    return $columns;
}

/**
 * Use AI to determine which tables to search and how
 */
function getAISearchStrategy(string $userQuery, array $tableList, string $category, $fileLogger): array
{
    // If category is specific (not "general"), use it directly
    if ($category !== 'general') {
        $tableMap = [
            'frames' => ['main_db', 'frames'],
            'characters' => ['main_db', 'characters'],
            'locations' => ['main_db', 'locations'],
            'backgrounds' => ['main_db', 'backgrounds'],
            'sketches' => ['main_db', 'sketches'],
            'artifacts' => ['main_db', 'artifacts'],
            'vehicles' => ['main_db', 'vehicles'],
            'storyboards' => ['main_db', 'storyboards'],
            'todos' => ['sys_db', 'sage_todos'],
            'code' => ['sys_db', 'code_classes'],
            'chat' => ['main_db', 'chat_session'],
            'gpt' => ['sys_db', 'gpt_conversations'],
        ];
        
        if (isset($tableMap[$category])) {
            return [
                'database' => $tableMap[$category][0],
                'table' => $tableMap[$category][1],
                'explanation' => "User selected category: {$category}",
                'search_type' => 'text_search'
            ];
        }
    }
    
    $aiProvider = new \App\Core\AIProvider($fileLogger);
    
    // Build a concise table list for the AI
    $tableDescription = buildTableListDescription($tableList);
    
    $systemPrompt = <<<PROMPT
You are a database query assistant for a movie storyboard generation application. Given a user's search query and available database tables, determine the optimal search strategy.

DATABASES:
- main_db: Contains movie/storyboard entities (characters, locations, frames, scenes, etc.)
- sys_db: Contains system data (todos, code analysis, GPT conversations, etc.)

AVAILABLE TABLES:
{$tableDescription}

COMMON PATTERNS:
- Search for frames/images: use "frames" table
- Search for characters: use "characters" table
- Search for locations/backgrounds: use "locations" or "backgrounds" table
- Search for todos/tasks: use "sage_todos" table (sys_db)
- Search for code/files/classes: use "code_classes" table (sys_db)
- Search for internal chat sessions: use "chat_session" table (main_db)
- Search for GPT imported conversations: use "gpt_conversations" table (sys_db)
- Search for sketches/concepts: use "sketches" table
- Search for storyboards: use "storyboards" table
- Search for vehicles: use "vehicles" table
- Search for artifacts/items: use "artifacts" table

Respond ONLY with valid JSON in this exact format:
{
    "database": "main_db or sys_db",
    "table": "table_name",
    "explanation": "brief explanation of why this table was chosen",
    "search_type": "text_search or id_lookup"
}

Rules:
- Pick the MOST relevant single table
- Use "text_search" for keyword/description searches
- Use "id_lookup" for specific ID searches (e.g., "show frame 123")
- Keep explanation brief and clear
PROMPT;

    $userPrompt = "User search query: \"{$userQuery}\"\n\nProvide the optimal table selection in JSON format.";
    
    try {
        $aiResponse = $aiProvider->sendPrompt(
            \App\Core\AIProvider::getDefaultModel(),
            $userPrompt,
            $systemPrompt,
            ['temperature' => 0.2, 'max_tokens' => 300]
        );
        
        // Extract JSON from response
        $jsonMatch = [];
        if (preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $aiResponse, $jsonMatch)) {
            $strategy = json_decode($jsonMatch[0], true);
            
            if (json_last_error() === JSON_ERROR_NONE && isset($strategy['table'])) {
                // Validate table exists
                $db = $strategy['database'] ?? 'main_db';
                if (in_array($strategy['table'], $tableList[$db]['tables'])) {
                    return $strategy;
                }
            }
        }
        
        // Fallback if AI response isn't valid
        return getFallbackStrategy($userQuery, $tableList);
        
    } catch (Exception $e) {
        $fileLogger->error(['AI Strategy Error' => $e->getMessage()]);
        return getFallbackStrategy($userQuery, $tableList);
    }
}

/**
 * Build a concise description of available tables
 */
function buildTableListDescription(array $tableList): string
{
    $description = "\nMAIN DATABASE:\n";
    foreach ($tableList['main_db']['tables'] as $table) {
        if (strpos($table, 'frames_2_') === false) { // Skip junction tables
            $description .= "  - {$table}\n";
        }
    }
    
    $description .= "\nSYS DATABASE:\n";
    foreach ($tableList['sys_db']['tables'] as $table) {
        $description .= "  - {$table}\n";
    }
    
    return $description;
}

/**
 * Fallback strategy when AI fails
 */
function getFallbackStrategy(string $userQuery, array $tableList): array
{
    $queryLower = strtolower($userQuery);
    
    // Keyword-based fallback matching
    $patterns = [
        'todo|task' => ['sys_db', 'sage_todos'],
        'code|file|class|function|method' => ['sys_db', 'code_classes'],
        'chat|conversation(?!.*gpt)' => ['main_db', 'chat_session'],
        'gpt|import|exported' => ['sys_db', 'gpt_conversations'],
        'character|person|hero' => ['main_db', 'characters'],
        'location|place|setting' => ['main_db', 'locations'],
        'frame|image|picture' => ['main_db', 'frames'],
        'sketch|concept' => ['main_db', 'sketches'],
        'storyboard|sequence' => ['main_db', 'storyboards'],
        'vehicle|ship|transport' => ['main_db', 'vehicles'],
        'artifact|item|object' => ['main_db', 'artifacts'],
        'background|scene' => ['main_db', 'backgrounds'],
    ];
    
    foreach ($patterns as $pattern => $target) {
        if (preg_match('/\b(' . $pattern . ')\b/i', $queryLower)) {
            return [
                'database' => $target[0],
                'table' => $target[1],
                'explanation' => 'Fallback: keyword-based match',
                'search_type' => 'text_search'
            ];
        }
    }
    
    // Default to frames
    return [
        'database' => 'main_db',
        'table' => 'frames',
        'explanation' => 'Fallback: default to frames search',
        'search_type' => 'text_search'
    ];
}

/**
 * Execute the search queries based on AI strategy
 */
function executeSearchQueries(array $strategy, array $columnInfo, $pdo, $pdoSys): array
{
    $db = ($strategy['database'] === 'sys_db') ? $pdoSys : $pdo;
    $table = $strategy['table'];
    
    // Special handling for code search - search in code_classes which has class_name
    if ($table === 'code_classes') {
        return searchCodeClasses($db, $pdo, $pdoSys);
    }
    
    // Determine searchable text columns
    $searchableColumns = [];
    foreach ($columnInfo as $col) {
        $type = strtolower($col['type']);
        $name = $col['name'];
        
        // Include text columns that are likely to contain searchable content
        if (in_array($type, ['varchar', 'text', 'mediumtext', 'longtext', 'char'])) {
            // Prioritize common name/description columns
            if (in_array($name, ['name', 'description', 'content', 'text', 'prompt', 
                                  'summary', 'title', 'content_text', 'class_name'])) {
                $searchableColumns[] = $name;
            } elseif (!in_array($name, ['filename', 'path', 'hash', 'img2img_frame_filename', 
                                         'cnmap_frame_filename', 'external_id', 'session_id',
                                         'config_id', 'file_hash'])) {
                // Include other text columns unless they're clearly not for searching
                $searchableColumns[] = $name;
            }
        }
    }
    
    if (empty($searchableColumns)) {
        // No text columns found, just return recent records
        $sql = "SELECT * FROM `{$table}` ORDER BY id DESC LIMIT 10";
        $stmt = $db->query($sql);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return formatResults($rows, $table, $strategy['database']);
    }
    
    // Build WHERE clause for text search
    global $data;
    $searchTerm = $data['query'];
    $whereParts = [];
    
    foreach ($searchableColumns as $col) {
        $whereParts[] = "`{$col}` LIKE :search";
    }
    
    $whereClause = implode(' OR ', $whereParts);
    $sql = "SELECT * FROM `{$table}` WHERE {$whereClause} ORDER BY id DESC LIMIT 500";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute(['search' => "%{$searchTerm}%"]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format results for display
        return formatResults($rows, $table, $strategy['database']);
        
    } catch (Exception $e) {
        throw new Exception("Query execution failed: " . $e->getMessage());
    }
}

/**
 * Special search handler for code classes
 */
function searchCodeClasses($db, $pdo, $pdoSys): array
{
    global $data;
    $searchTerm = $data['query'];
    
    // Search in code_classes table for class names and summaries
    $sql = "
        SELECT 
            cc.id,
            cc.file_id,
            cc.class_name,
            cc.methods,
            cc.extends_class,
            cc.summary,
            cf.path,
            cf.file_hash
        FROM code_classes cc
        INNER JOIN code_files cf ON cc.file_id = cf.id
        WHERE cc.class_name LIKE :search
           OR cc.summary LIKE :search
           OR cc.methods LIKE :search
           OR cf.path LIKE :search
        ORDER BY cc.id DESC
        LIMIT 500
    ";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute(['search' => "%{$searchTerm}%"]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format for display
        $results = [];
        foreach ($rows as $row) {
            $results[] = [
                'id' => $row['id'],
                'table' => 'code_classes',
                'database' => 'sys_db',
                'title' => $row['class_name'] ?? basename($row['path'] ?? 'Unknown'),
                'meta' => isset($row['path']) ? basename($row['path']) : '',
                'url' => "codeboard.php?file_id=" . $row['file_id'],
                'raw_data' => $row
            ];
        }
        
        return $results;
        
    } catch (Exception $e) {
        throw new Exception("Code search failed: " . $e->getMessage());
    }
}

/**
 * Format raw database results for display
 */
function formatResults(array $rows, string $table, string $database): array
{
    $results = [];
    $framesDir = \App\Core\SpwBase::getInstance()->getFramesDirRel();
    
    foreach ($rows as $row) {
        $result = [
            'id' => $row['id'] ?? null,
            'table' => $table,
            'database' => $database,
            'title' => '',
            'meta' => '',
            'url' => null,
            'thumbnail' => null,
            'raw_data' => $row
        ];
        
        // Add thumbnail for frames
        if ($table === 'frames' && isset($row['filename'])) {
            $result['thumbnail'] = $row['filename'];
        }
        
        // Determine title based on available columns
        if (isset($row['name']) && !empty($row['name'])) {
            $result['title'] = $row['name'];
        } elseif (isset($row['title']) && !empty($row['title'])) {
            $result['title'] = $row['title'];
        } elseif (isset($row['class_name']) && !empty($row['class_name'])) {
            $result['title'] = $row['class_name'];
        } elseif (isset($row['description']) && !empty($row['description'])) {
            $result['title'] = substr($row['description'], 0, 60);
            if (strlen($row['description']) > 60) {
                $result['title'] .= '...';
            }
        } elseif (isset($row['prompt']) && !empty($row['prompt'])) {
            $result['title'] = substr($row['prompt'], 0, 60) . '...';
        } elseif (isset($row['content_text']) && !empty($row['content_text'])) {
            $result['title'] = substr($row['content_text'], 0, 60) . '...';
        } elseif (isset($row['filename'])) {
            $result['title'] = basename($row['filename']);
        } elseif (isset($row['path'])) {
            $result['title'] = basename($row['path']);
        } else {
            $result['title'] = ucfirst($table) . " #" . ($row['id'] ?? 'unknown');
        }
        
        // Add metadata
        if (isset($row['created_at'])) {
            $result['meta'] = date('M j, Y', strtotime($row['created_at']));
        } elseif (isset($row['updated_at'])) {
            $result['meta'] = date('M j, Y', strtotime($row['updated_at']));
        }
        
        // Add type/role info if available
        if (isset($row['type']) && !empty($row['type'])) {
            $result['meta'] = ($result['meta'] ? $result['meta'] . ' • ' : '') . $row['type'];
        } elseif (isset($row['role']) && !empty($row['role'])) {
            $result['meta'] = ($result['meta'] ? $result['meta'] . ' • ' : '') . $row['role'];
        } elseif (isset($row['status']) && !empty($row['status'])) {
            $result['meta'] = ($result['meta'] ? $result['meta'] . ' • ' : '') . $row['status'];
        } elseif (isset($row['model']) && !empty($row['model'])) {
            $result['meta'] = ($result['meta'] ? $result['meta'] . ' • ' : '') . $row['model'];
        }
        
        // Generate URL based on table type
        $result['url'] = generateResultUrl($table, $row);
        
        $results[] = $result;
    }
    
    return $results;
}

/**
 * Generate appropriate URL for result based on table and data
 */
function generateResultUrl(string $table, array $row): ?string
{
    $id = $row['id'] ?? null;
    if (!$id) return null;
    
    // Map tables to their view pages
    $urlMap = [
        'frames' => "view_frame.php?frame_id={$id}",
        'characters' => "view_character.php?character_id={$id}",
        'locations' => "view_entity.php?entity=locations&id={$id}",
        'backgrounds' => "view_entity.php?entity=backgrounds&id={$id}",
        'sketches' => "view_entity.php?entity=sketches&id={$id}",
        'artifacts' => "view_entity.php?entity=artifacts&id={$id}",
        'vehicles' => "view_entity.php?entity=vehicles&id={$id}",
        'spawns' => "view_entity.php?entity=spawns&id={$id}",
        'generatives' => "view_entity.php?entity=generatives&id={$id}",
        'storyboards' => "view_storyboard.php?id={$id}",
        'sage_todos' => "todo.php?id={$id}",
        'code_classes' => "codeboard.php?file_id=".urlencode($row['id'] ?? ''),
        'chat_session' => "chat.php?session_id=" . urlencode($row['session_id'] ?? '')
    ];
    
    // Special handling for gpt_conversations which uses external_id
    if ($table === 'gpt_conversations') {
        $externalId = $row['external_id'] ?? '';
        return "view_gpt.php?id=" . urlencode($externalId);
    }
    
    return $urlMap[$table] ?? null;
}
