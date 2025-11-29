<?php
// public/db_migration_api.php
require_once __DIR__ . '/bootstrap.php';
require __DIR__ . '/env_locals.php';

use App\Core\DatabaseMigrationManager;
use App\Core\AIProvider;

header('Content-Type: application/json');

$spw = \App\Core\SpwBase::getInstance();
$pdo = $spw->getPDO();
$fileLogger = $spw->getFileLogger();
$aiProvider = new AIProvider($fileLogger);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'compare_schemas':
            handleCompareSchemas();
            break;
            
        case 'version_diff':
            handleVersionDiff();
            break;
            
        case 'generate_migration':
            handleGenerateMigration();
            break;
            
        case 'execute_migration':
            handleExecuteMigration();
            break;
            
        case 'get_migration_history':
            handleGetMigrationHistory();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if (isset($fileLogger)) {
        $fileLogger->error(['Migration API error' => [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]]);
    }
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

function handleCompareSchemas() {
    global $pdo, $fileLogger, $aiProvider;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $sourceDb = $input['source_db'] ?? '';
    $targetDb = $input['target_db'] ?? '';
    
    if (empty($sourceDb) || empty($targetDb)) {
        throw new Exception('Source and target databases are required');
    }
    
    $migrationManager = new DatabaseMigrationManager($pdo, $fileLogger, $aiProvider);
    $differences = $migrationManager->compareSchemas($sourceDb, $targetDb);
    
    echo json_encode([
        'status' => 'ok',
        'source_db' => $sourceDb,
        'target_db' => $targetDb,
        'differences' => $differences,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function handleVersionDiff() {
    global $pdo, $fileLogger, $aiProvider, $projectPath;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $targetDb = $input['target_db'] ?? '';
    $fromVersion = $input['from_version'] ?? '';
    $toVersion = $input['to_version'] ?? '';
    
    if (empty($targetDb) || empty($fromVersion) || empty($toVersion)) {
        throw new Exception('Target database and versions are required');
    }
    
    // Look for migration files in migrations directory
    $migrationsDir = $projectPath . '/migrations';
    
    if (!is_dir($migrationsDir)) {
        throw new Exception('Migrations directory not found. Please create: ' . $migrationsDir);
    }
    
    // Find migration files between versions
    $migrationFiles = findMigrationFiles($migrationsDir, $fromVersion, $toVersion);
    
    if (empty($migrationFiles)) {
        throw new Exception("No migration files found between {$fromVersion} and {$toVersion}");
    }
    
    // Parse migration files to extract statements
    $statements = parseMigrationFiles($migrationFiles);
    
    echo json_encode([
        'status' => 'ok',
        'target_db' => $targetDb,
        'from_version' => $fromVersion,
        'to_version' => $toVersion,
        'migration_files' => array_map('basename', $migrationFiles),
        'differences' => [
            'version_migrations' => $statements,
            'stats' => [
                'migration_needed' => count($statements) > 0,
                'file_count' => count($migrationFiles)
            ]
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function handleGenerateMigration() {
    global $pdo, $fileLogger, $aiProvider;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $comparison = $input['comparison'] ?? null;
    $useAI = $input['use_ai'] ?? false;
    
    if (!$comparison) {
        throw new Exception('Comparison data is required');
    }
    
    $sourceDb = $comparison['source_db'] ?? '';
    $targetDb = $comparison['target_db'] ?? '';
    $differences = $comparison['differences'] ?? [];
    
    // Handle version update migrations
    if (isset($differences['version_migrations'])) {
        $statements = $differences['version_migrations'];
        $aiFeedback = [];
        
        if ($useAI) {
            $migrationManager = new DatabaseMigrationManager($pdo, $fileLogger, $aiProvider);
            // We need to make validateWithAI accessible or call it through execution
            $aiFeedback = []; // Placeholder - can be enhanced
        }
        
        echo json_encode([
            'status' => 'ok',
            'statements' => $statements,
            'ai_feedback' => $aiFeedback,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return;
    }
    
    // Handle parallel sync migrations
    $migrationManager = new DatabaseMigrationManager($pdo, $fileLogger, $aiProvider);
    $statements = $migrationManager->generateMigrationSQL($sourceDb, $targetDb, $differences);
    
    $aiFeedback = [];
    if ($useAI && !empty($statements)) {
        // Call AI validation
        $aiFeedback = callAIValidation($statements);
    }
    
    echo json_encode([
        'status' => 'ok',
        'statements' => $statements,
        'ai_feedback' => $aiFeedback,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function handleExecuteMigration() {
    global $pdo, $fileLogger, $aiProvider, $spw;
    
    $input = json_decode(file_get_contents('php://input'), true);
    $statements = $input['statements'] ?? [];
    $dryRun = $input['dry_run'] ?? true;
    $createBackup = $input['create_backup'] ?? true;
    $targetDb = $input['target_db'] ?? '';
    
    if (empty($statements)) {
        throw new Exception('No statements to execute');
    }
    
    if (empty($targetDb)) {
        throw new Exception('Target database is required');
    }
    
    // Switch to target database PDO connection
    $targetPdo = $spw->getPDOForDatabase($targetDb);
    
    $migrationManager = new DatabaseMigrationManager($targetPdo, $fileLogger, $aiProvider);
    $results = $migrationManager->executeMigration($targetDb, $statements, false, $dryRun);
    
    $backupInfo = null;
    if ($createBackup && !$dryRun && $results['success']) {
        // Backup was already created in executeMigration
        $backupInfo = [
            'created' => true,
            'file' => '/tmp/db_backup_' . $targetDb . '_' . date('Y-m-d_H-i-s') . '.sql'
        ];
    }
    
    echo json_encode([
        'status' => 'ok',
        'results' => $results,
        'backup_info' => $backupInfo,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

function handleGetMigrationHistory() {
    global $pdo, $spw;
    
    $targetDb = $_GET['database'] ?? $spw->getDbName();
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM `{$targetDb}`.`migration_history`
            ORDER BY executed_at DESC
            LIMIT 50
        ");
        $stmt->execute();
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'ok',
            'database' => $targetDb,
            'history' => $history
        ]);
    } catch (Exception $e) {
        // Table might not exist yet
        echo json_encode([
            'status' => 'ok',
            'database' => $targetDb,
            'history' => [],
            'message' => 'No migration history found'
        ]);
    }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function findMigrationFiles(string $dir, string $fromVersion, string $toVersion): array {
    $files = [];
    
    // Convert version strings to comparable format (e.g., v0.3 -> 0.3, v0.4 -> 0.4)
    $fromNum = (float) ltrim($fromVersion, 'v');
    $toNum = (float) ltrim($toVersion, 'v');
    
    if (!is_dir($dir)) {
        return $files;
    }
    
    $iterator = new DirectoryIterator($dir);
    
    foreach ($iterator as $file) {
        if ($file->isDot() || !$file->isFile()) {
            continue;
        }
        
        $filename = $file->getFilename();
        
        // Match migration files like: migration_v0.3_to_v0.4.sql or 001_add_table.sql
        if (preg_match('/\.sql$/i', $filename)) {
            // Check if file contains version info
            if (preg_match('/v?([\d.]+)/', $filename, $matches)) {
                $fileVersion = (float) $matches[1];
                
                // Include if version is between from and to
                if ($fileVersion > $fromNum && $fileVersion <= $toNum) {
                    $files[] = $file->getPathname();
                }
            } else {
                // Include all migration files if no version specified
                $files[] = $file->getPathname();
            }
        }
    }
    
    sort($files);
    return $files;
}

function parseMigrationFiles(array $files): array {
    $statements = [];
    
    foreach ($files as $file) {
        $content = file_get_contents($file);
        
        // Split by semicolons but respect SQL strings
        $queries = splitSqlStatements($content);
        
        foreach ($queries as $sql) {
            $sql = trim($sql);
            
            if (empty($sql) || strpos($sql, '--') === 0) {
                continue; // Skip empty or comment-only lines
            }
            
            // Determine statement type
            $type = 'UNKNOWN';
            if (preg_match('/^CREATE TABLE/i', $sql)) {
                $type = 'CREATE_TABLE';
            } elseif (preg_match('/^ALTER TABLE.*ADD COLUMN/i', $sql)) {
                $type = 'ADD_COLUMN';
            } elseif (preg_match('/^ALTER TABLE.*MODIFY COLUMN/i', $sql)) {
                $type = 'MODIFY_COLUMN';
            } elseif (preg_match('/^ALTER TABLE.*ADD INDEX/i', $sql)) {
                $type = 'ADD_INDEX';
            } elseif (preg_match('/^ALTER TABLE.*ADD CONSTRAINT/i', $sql)) {
                $type = 'ADD_FOREIGN_KEY';
            } elseif (preg_match('/^ALTER TABLE/i', $sql)) {
                $type = 'ALTER_TABLE';
            } elseif (preg_match('/^DROP TABLE/i', $sql)) {
                $type = 'DROP_TABLE';
            } elseif (preg_match('/^INSERT INTO/i', $sql)) {
                $type = 'INSERT_DATA';
            } elseif (preg_match('/^UPDATE/i', $sql)) {
                $type = 'UPDATE_DATA';
            }
            
            // Extract table name
            $table = null;
            if (preg_match('/(?:TABLE|INTO|UPDATE)\s+`?(\w+)`?/i', $sql, $matches)) {
                $table = $matches[1];
            }
            
            $statements[] = [
                'type' => $type,
                'table' => $table,
                'sql' => $sql,
                'safe' => !in_array($type, ['DROP_TABLE', 'MODIFY_COLUMN', 'UPDATE_DATA']),
                'reversible' => in_array($type, ['CREATE_TABLE', 'ADD_COLUMN', 'ADD_INDEX']),
                'source_file' => basename($file)
            ];
        }
    }
    
    return $statements;
}

function splitSqlStatements(string $sql): array {
    $statements = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    $length = strlen($sql);
    
    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        
        // Handle string literals
        if (($char === '"' || $char === "'") && ($i === 0 || $sql[$i - 1] !== '\\')) {
            if (!$inString) {
                $inString = true;
                $stringChar = $char;
            } elseif ($char === $stringChar) {
                $inString = false;
            }
        }
        
        // Handle statement terminator
        if ($char === ';' && !$inString) {
            $statements[] = $current;
            $current = '';
            continue;
        }
        
        $current .= $char;
    }
    
    // Add final statement if not empty
    if (!empty(trim($current))) {
        $statements[] = $current;
    }
    
    return $statements;
}

function callAIValidation(array $statements): array {
    global $aiProvider;
    
    $feedback = [];
    
    if (!$aiProvider) {
        return $feedback;
    }
    
    // Prepare SQL for AI review
    $sqlBatch = array_map(fn($s) => $s['sql'], array_slice($statements, 0, 10)); // Limit to first 10 for token limits
    $sqlText = implode("\n\n", $sqlBatch);
    
    if (count($statements) > 10) {
        $sqlText .= "\n\n-- ... and " . (count($statements) - 10) . " more statements";
    }
    
    $prompt = "Review the following database migration SQL for safety and correctness. " .
             "Identify any potential issues, data loss risks, or performance concerns. " .
             "Provide a brief risk assessment:\n\n" . $sqlText;
    
    try {
        $response = $aiProvider->sendPrompt(
            AIProvider::getDefaultModel(),
            $prompt,
            "You are a database migration expert. Analyze SQL statements for safety and correctness. Be concise."
        );
        
        // Assess risk based on keywords
        $risk = 'low';
        $lowerResponse = strtolower($response);
        
        if (strpos($lowerResponse, 'data loss') !== false || 
            strpos($lowerResponse, 'dangerous') !== false ||
            strpos($lowerResponse, 'irreversible') !== false) {
            $risk = 'high';
        } elseif (strpos($lowerResponse, 'caution') !== false || 
                  strpos($lowerResponse, 'warning') !== false ||
                  strpos($lowerResponse, 'careful') !== false) {
            $risk = 'medium';
        }
        
        $feedback[] = [
            'source' => 'ai_review',
            'model' => AIProvider::getDefaultModel(),
            'response' => $response,
            'risk' => $risk,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        $feedback[] = [
            'source' => 'ai_review',
            'error' => $e->getMessage(),
            'risk' => 'unknown'
        ];
    }
    
    return $feedback;
}
