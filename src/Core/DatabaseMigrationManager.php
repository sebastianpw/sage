<?php
namespace App\Core;

use PDO;
use Exception;

/**
 * DatabaseMigrationManager - Automated Schema Migration Service
 * 
 * Handles two primary use cases:
 * 1. Syncing parallel instances (e.g., starlight_guardians_nu → starlight_guardians_test)
 * 2. Version-to-version updates (e.g., v0.3 → v0.4)
 * 
 * Features:
 * - Schema comparison using information_schema
 * - Safe migration script generation
 * - AI-assisted validation
 * - Rollback support
 * - Comprehensive logging
 */
class DatabaseMigrationManager
{
    private PDO $pdo;
    private ?FileLogger $logger;
    private ?AIProvider $aiProvider;
    private array $migrationLog = [];
    
    // Migration types
    public const TYPE_PARALLEL_SYNC = 'parallel_sync';
    public const TYPE_VERSION_UPDATE = 'version_update';
    
    public function __construct(PDO $pdo, ?FileLogger $logger = null, ?AIProvider $aiProvider = null)
    {
        $this->pdo = $pdo;
        $this->logger = $logger;
        $this->aiProvider = $aiProvider;
    }
    
    /**
     * Compare two databases and generate migration plan
     */
    public function compareSchemas(string $sourceDb, string $targetDb): array
    {
        $this->log('info', 'Starting schema comparison', [
            'source' => $sourceDb,
            'target' => $targetDb
        ]);
        
        $differences = [
            'missing_tables' => [],
            'missing_columns' => [],
            'column_changes' => [],
            'missing_indexes' => [],
            'missing_constraints' => [],
            'missing_views' => [],
            'altered_views' => [],
            'extra_tables' => [], // Tables in target but not in source
            'extra_views' => [],  // Views in target but not in source
            'stats' => [
                'source_table_count' => 0,
                'target_table_count' => 0,
                'migration_needed' => false
            ]
        ];
        
        // Get all tables from both databases
        $sourceTables = $this->getTables($sourceDb);
        $targetTables = $this->getTables($targetDb);
        
        $differences['stats']['source_table_count'] = count($sourceTables);
        $differences['stats']['target_table_count'] = count($targetTables);
        
        // Find missing tables
        foreach ($sourceTables as $table) {
            if (!in_array($table, $targetTables)) {
                $differences['missing_tables'][] = $table;
                $differences['stats']['migration_needed'] = true;
            }
        }
        
        // Find extra tables (in target but not source)
        foreach ($targetTables as $table) {
            if (!in_array($table, $sourceTables)) {
                $differences['extra_tables'][] = $table;
            }
        }
        
        // Compare columns for existing tables
        $commonTables = array_intersect($sourceTables, $targetTables);
        foreach ($commonTables as $table) {
            $sourceColumns = $this->getColumns($sourceDb, $table);
            $targetColumns = $this->getColumns($targetDb, $table);
            
            // Find missing columns
            foreach ($sourceColumns as $column) {
                $found = false;
                foreach ($targetColumns as $targetCol) {
                    if ($targetCol['COLUMN_NAME'] === $column['COLUMN_NAME']) {
                        $found = true;
                        // Check for column differences
                        if ($this->columnsAreDifferent($column, $targetCol)) {
                            $differences['column_changes'][] = [
                                'table' => $table,
                                'column' => $column['COLUMN_NAME'],
                                'source' => $column,
                                'target' => $targetCol
                            ];
                            $differences['stats']['migration_needed'] = true;
                        }
                        break;
                    }
                }
                
                if (!$found) {
                    $differences['missing_columns'][] = [
                        'table' => $table,
                        'column' => $column
                    ];
                    $differences['stats']['migration_needed'] = true;
                }
            }
            
            // Compare indexes
            $sourceIndexes = $this->getIndexes($sourceDb, $table);
            $targetIndexes = $this->getIndexes($targetDb, $table);
            
            // Group indexes by name (for composite indexes)
            $sourceIndexGroups = $this->groupIndexesByName($sourceIndexes);
            $targetIndexGroups = $this->groupIndexesByName($targetIndexes);
            
            foreach ($sourceIndexGroups as $indexName => $indexCols) {
                if (!isset($targetIndexGroups[$indexName])) {
                    // Only add the first column entry - we'll reconstruct the full index
                    $differences['missing_indexes'][] = [
                        'table' => $table,
                        'index' => $indexCols[0], // Use first column as reference
                        'all_columns' => $indexCols // Store all columns for composite indexes
                    ];
                    $differences['stats']['migration_needed'] = true;
                }
            }
            
            // Compare foreign keys
            $sourceFKs = $this->getForeignKeys($sourceDb, $table);
            $targetFKs = $this->getForeignKeys($targetDb, $table);
            
            foreach ($sourceFKs as $fk) {
                if (!$this->foreignKeyExists($fk, $targetFKs)) {
                    $differences['missing_constraints'][] = [
                        'table' => $table,
                        'constraint' => $fk
                    ];
                    $differences['stats']['migration_needed'] = true;
                }
            }
        }
        
        // Compare Views
        $sourceViews = $this->getViews($sourceDb);
        $targetViews = $this->getViews($targetDb);

        foreach ($sourceViews as $view) {
            if (!in_array($view, $targetViews)) {
                $differences['missing_views'][] = $view;
                $differences['stats']['migration_needed'] = true;
            }
        }

        foreach ($targetViews as $view) {
            if (!in_array($view, $sourceViews)) {
                $differences['extra_views'][] = $view;
            }
        }

        $commonViews = array_intersect($sourceViews, $targetViews);
        foreach ($commonViews as $view) {
            $sourceDef = $this->getCreateViewStatement($sourceDb, $view);
            $targetDef = $this->getCreateViewStatement($targetDb, $view);

            if ($this->viewDefinitionsAreDifferent($sourceDef, $targetDef, $sourceDb, $targetDb)) {
                $differences['altered_views'][] = [
                    'view' => $view,
                    'source_definition' => $sourceDef,
                    'target_definition' => $targetDef,
                ];
                $differences['stats']['migration_needed'] = true;
            }
        }
        
        $this->log('info', 'Schema comparison complete', [
            'differences_found' => $differences['stats']['migration_needed'],
            'missing_tables' => count($differences['missing_tables']),
            'missing_columns' => count($differences['missing_columns']),
            'column_changes' => count($differences['column_changes']),
            'missing_views' => count($differences['missing_views']),
            'altered_views' => count($differences['altered_views']),
        ]);
        
        return $differences;
    }
    
    /**
     * Generate migration SQL statements from comparison results
     */
    public function generateMigrationSQL(string $sourceDb, string $targetDb, array $differences): array
    {
        $statements = [];
        
        // Create missing tables (split FK constraints out)
        foreach ($differences['missing_tables'] as $table) {
            $createSQL = $this->getCreateTableStatement($sourceDb, $table);
            if ($createSQL) {
                // Extract and separate foreign key constraints
                $parsedSQL = $this->splitForeignKeysFromCreateTable($createSQL, $targetDb, $table);
                
                // Add the CREATE TABLE without FKs
                $statements[] = [
                    'type' => 'CREATE_TABLE',
                    'table' => $table,
                    'sql' => $parsedSQL['create_table'],
                    'safe' => true,
                    'reversible' => true,
                    'rollback' => "DROP TABLE IF EXISTS `{$targetDb}`.`{$table}`;",
                    'priority' => 1 // Tables first
                ];
                
                // Add FK constraints as separate statements
                foreach ($parsedSQL['foreign_keys'] as $fk) {
                    $statements[] = [
                        'type' => 'ADD_FOREIGN_KEY',
                        'table' => $table,
                        'constraint' => $fk['name'],
                        'sql' => "ALTER TABLE `{$targetDb}`.`{$table}` ADD " . $fk['definition'] . ";",
                        'safe' => true,
                        'reversible' => true,
                        'rollback' => "ALTER TABLE `{$targetDb}`.`{$table}` DROP FOREIGN KEY `{$fk['name']}`;",
                        'priority' => 5, // Foreign keys LAST
                        'referenced_table' => $fk['referenced_table']
                    ];
                }
            }
        }
        
        // Add missing columns
        foreach ($differences['missing_columns'] as $missing) {
            $col = $missing['column'];
            $sql = $this->generateAddColumnSQL($targetDb, $missing['table'], $col);
            $statements[] = [
                'type' => 'ADD_COLUMN',
                'table' => $missing['table'],
                'column' => $col['COLUMN_NAME'],
                'sql' => $sql,
                'safe' => true,
                'reversible' => true,
                'rollback' => "ALTER TABLE `{$targetDb}`.`{$missing['table']}` DROP COLUMN `{$col['COLUMN_NAME']}`;",
                'priority' => 2 // Columns second
            ];
        }
        
        // Modify changed columns (requires caution)
        foreach ($differences['column_changes'] as $change) {
            $sql = $this->generateModifyColumnSQL($targetDb, $change['table'], $change['source']);
            $statements[] = [
                'type' => 'MODIFY_COLUMN',
                'table' => $change['table'],
                'column' => $change['column'],
                'sql' => $sql,
                'safe' => false, // Data might be affected
                'reversible' => true,
                'rollback' => $this->generateModifyColumnSQL($targetDb, $change['table'], $change['target']),
                'warning' => 'Column modification may affect existing data',
                'priority' => 3 // Column changes third
            ];
        }
        
        // Add missing indexes
        foreach ($differences['missing_indexes'] as $missing) {
            $allColumns = $missing['all_columns'] ?? [$missing['index']];
            $sql = $this->generateAddIndexSQL($targetDb, $missing['table'], $missing['index'], $allColumns);
            
            $statements[] = [
                'type' => 'ADD_INDEX',
                'table' => $missing['table'],
                'index' => $missing['index']['Key_name'],
                'sql' => $sql,
                'safe' => true,
                'reversible' => true,
                'rollback' => "ALTER TABLE `{$targetDb}`.`{$missing['table']}` DROP INDEX `{$missing['index']['Key_name']}`;",
                'priority' => 4 // Indexes fourth
            ];
        }
        
        // Add missing foreign keys
        foreach ($differences['missing_constraints'] as $missing) {
            $sql = $this->generateAddForeignKeySQL($targetDb, $missing['table'], $missing['constraint']);
            $statements[] = [
                'type' => 'ADD_FOREIGN_KEY',
                'table' => $missing['table'],
                'constraint' => $missing['constraint']['CONSTRAINT_NAME'],
                'sql' => $sql,
                'safe' => true,
                'reversible' => true,
                'rollback' => "ALTER TABLE `{$targetDb}`.`{$missing['table']}` DROP FOREIGN KEY `{$missing['constraint']['CONSTRAINT_NAME']}`;",
                'priority' => 5, // Foreign keys LAST
                'referenced_table' => $missing['constraint']['REFERENCED_TABLE_NAME']
            ];
        }

        // Create missing views
        foreach ($differences['missing_views'] as $view) {
            $createSQL = $this->getCreateViewStatement($sourceDb, $view);
            if ($createSQL) {
                $sql = $this->rebuildCreateViewSQL($createSQL, $targetDb, $view, false);
                $statements[] = [
                    'type' => 'CREATE_VIEW',
                    'table' => $view, // Use 'table' key for UI consistency
                    'sql' => $sql,
                    'safe' => true,
                    'reversible' => true,
                    'rollback' => "DROP VIEW IF EXISTS `{$targetDb}`.`{$view}`;",
                    'priority' => 6 // Views after all table structures
                ];
            }
        }

        // Alter/Replace views
        foreach ($differences['altered_views'] as $change) {
            $view = $change['view'];
            $createSQL = $change['source_definition'];
            if ($createSQL) {
                // Rebuild with CREATE OR REPLACE
                $sql = $this->rebuildCreateViewSQL($createSQL, $targetDb, $view, true); 
                
                // For rollback, we need the old definition
                $oldCreateSQL = $this->rebuildCreateViewSQL($change['target_definition'], $targetDb, $view, true);

                $statements[] = [
                    'type' => 'REPLACE_VIEW',
                    'table' => $view,
                    'sql' => $sql,
                    'safe' => true,
                    'reversible' => true,
                    'rollback' => $oldCreateSQL,
                    'priority' => 6
                ];
            }
        }
        
        // Sort by priority to ensure proper execution order
        usort($statements, function($a, $b) {
            return ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99);
        });
        
        return $statements;
    }
    
    /**
     * Split foreign key constraints from CREATE TABLE statement
     */
    private function splitForeignKeysFromCreateTable(string $createSQL, string $targetDb, string $table): array
    {
        $foreignKeys = [];
        
        // Replace database name in CREATE TABLE
        $createSQL = str_replace("CREATE TABLE `{$table}`", "CREATE TABLE `{$targetDb}`.`{$table}`", $createSQL);
        
        // Extract all CONSTRAINT lines with foreign keys
        $lines = explode("\n", $createSQL);
        $filteredLines = [];
        $inTableDef = false;
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            
            if (stripos($trimmed, 'CREATE TABLE') !== false) {
                $inTableDef = true;
                $filteredLines[] = $line;
                continue;
            }
            
            if (!$inTableDef) {
                $filteredLines[] = $line;
                continue;
            }
            
            // Check if this line contains a CONSTRAINT with FOREIGN KEY
            if (preg_match('/CONSTRAINT\s+`([^`]+)`\s+FOREIGN KEY\s*\(`([^`]+)`\)\s+REFERENCES\s+`([^`]+)`\s*\(`([^`]+)`\)(.*?)(?:,?\s*)$/i', 
                          $trimmed, $matches)) {
                
                $constraintName = $matches[1];
                $column = $matches[2];
                $refTable = $matches[3];
                $refColumn = $matches[4];
                $extra = $matches[5]; // ON DELETE CASCADE, etc.
                
                // Store the FK for later
                $foreignKeys[] = [
                    'name' => $constraintName,
                    'column' => $column,
                    'referenced_table' => $refTable,
                    'referenced_column' => $refColumn,
                    'definition' => "CONSTRAINT `{$constraintName}` FOREIGN KEY (`{$column}`) REFERENCES `{$refTable}` (`{$refColumn}`)" . $extra
                ];
                
                // Skip this line (remove from CREATE TABLE)
                continue;
            }
            
            $filteredLines[] = $line;
        }
        
        // Reconstruct CREATE TABLE without FKs
        $cleanSQL = implode("\n", $filteredLines);
        
        // Clean up any trailing commas before closing parenthesis
        $cleanSQL = preg_replace('/,(\s*)\)(\s*ENGINE)/i', '$1)$2', $cleanSQL);
        
        return [
            'create_table' => $cleanSQL,
            'foreign_keys' => $foreignKeys
        ];
    }
    
    /**
     * Execute migration with optional AI validation
     */
    public function executeMigration(string $targetDb, array $statements, bool $useAI = true, bool $dryRun = false): array
    {
        $results = [
            'success' => true,
            'executed' => [],
            'failed' => [],
            'skipped' => [],
            'ai_feedback' => [],
            'warnings' => []
        ];
        
        // AI validation if enabled
        if ($useAI && $this->aiProvider) {
            $results['ai_feedback'] = $this->validateWithAI($statements);
            
            // Check for AI warnings
            foreach ($results['ai_feedback'] as $feedback) {
                if (isset($feedback['risk']) && $feedback['risk'] === 'high') {
                    $this->log('warning', 'AI flagged high-risk migration', $feedback);
                }
            }
        }
        
        if ($dryRun) {
            $this->log('info', 'Dry run mode - no changes will be made');
            return $results;
        }
        
        // Create backup point
        $backupInfo = $this->createBackupPoint($targetDb);
        $this->log('info', 'Backup created', $backupInfo);
        
        // Validate foreign key constraints before execution
        $fkValidation = $this->validateForeignKeyConstraints($targetDb, $statements);
        if (!empty($fkValidation)) {
            $results['warnings'] = array_merge($results['warnings'], $fkValidation);
        }
        
        // Execute statements
        $transactionActive = false;
        
        try {
            $this->pdo->beginTransaction();
            $transactionActive = true;
            
            foreach ($statements as $idx => $statement) {
                try {
                    $this->log('info', 'Executing migration', [
                        'type' => $statement['type'],
                        'table' => $statement['table'] ?? null
                    ]);
                    
                    $this->pdo->exec($statement['sql']);
                    
                    $results['executed'][] = [
                        'index' => $idx,
                        'statement' => $statement,
                        'status' => 'success'
                    ];
                    
                    $this->migrationLog[] = [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'type' => $statement['type'],
                        'sql' => $statement['sql'],
                        'status' => 'executed'
                    ];
                    
                } catch (Exception $e) {
                    $errorMsg = $e->getMessage();
                    
                    // Enhanced error reporting for foreign key issues
                    if (strpos($errorMsg, 'errno: 150') !== false || strpos($errorMsg, 'foreign key constraint') !== false) {
                        $fkDetails = $this->analyzeForeignKeyError($targetDb, $statement);
                        $errorMsg .= "\n\nDiagnostics:\n" . implode("\n", $fkDetails);
                    }
                    
                    $this->log('error', 'Migration statement failed', [
                        'statement' => $statement,
                        'error' => $errorMsg
                    ]);
                    
                    $results['failed'][] = [
                        'index' => $idx,
                        'statement' => $statement,
                        'error' => $errorMsg
                    ];
                    
                    $this->migrationLog[] = [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'type' => $statement['type'],
                        'sql' => $statement['sql'],
                        'status' => 'failed',
                        'error' => $errorMsg
                    ];
                    
                    // Rollback and exit
                    throw $e;
                }
            }
            
            if ($transactionActive) {
                $this->pdo->commit();
                $transactionActive = false;
            }
            $this->log('info', 'Migration completed successfully');
            
        } catch (Exception $e) {
            if ($transactionActive) {
                try {
                    $this->pdo->rollBack();
                    $transactionActive = false;
                } catch (Exception $rollbackErr) {
                    // Transaction may already be rolled back - this is OK
                    $this->log('debug', 'Transaction already inactive during rollback', [
                        'error' => $rollbackErr->getMessage()
                    ]);
                }
            }
            
            // Only mark as failure if we actually failed to execute statements
            if (!empty($results['failed'])) {
                $results['success'] = false;
            }
            
            $this->log('error', 'Migration encountered an error', [
                'error' => $e->getMessage(),
                'failed_count' => count($results['failed']),
                'executed_count' => count($results['executed'])
            ]);
        }
        
        // Ensure no active transaction before logging
        if ($transactionActive) {
            try {
                $this->pdo->rollBack();
            } catch (Exception $e) {
                // Ignore - transaction may already be rolled back
            }
        }
        
        // If all statements executed successfully, mark as success regardless of transaction warnings
        if (count($results['failed']) === 0 && count($results['executed']) > 0) {
            $results['success'] = true;
        }
        
        // Save migration log (always, even on failure) - uses separate connection
        $this->saveMigrationLog($targetDb);
        
        return $results;
    }
    
    /**
     * Validate foreign key constraints before execution
     */
    private function validateForeignKeyConstraints(string $targetDb, array $statements): array
    {
        $warnings = [];
        
        // Create a fresh PDO connection for validation
        try {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';
            
            $dsn = "mysql:host={$host};dbname={$targetDb};charset=utf8mb4";
            $valPdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Exception $e) {
            return [['type' => 'validation_error', 'message' => 'Could not create validation connection: ' . $e->getMessage()]];
        }
        
        foreach ($statements as $stmt) {
            if ($stmt['type'] === 'ADD_FOREIGN_KEY' || strpos($stmt['sql'], 'FOREIGN KEY') !== false) {
                // Extract referenced table and column
                if (preg_match('/REFERENCES\s+`?(\w+)`?\s*\(`?(\w+)`?\)/i', $stmt['sql'], $matches)) {
                    $refTable = $matches[1];
                    $refColumn = $matches[2];
                    
                    // Check if referenced table exists
                    try {
                        $checkTable = $valPdo->query("
                            SELECT COUNT(*) as cnt 
                            FROM information_schema.TABLES 
                            WHERE TABLE_SCHEMA = " . $valPdo->quote($targetDb) . " 
                            AND TABLE_NAME = " . $valPdo->quote($refTable)
                        )->fetch();
                        
                        if ($checkTable['cnt'] == 0) {
                            $warnings[] = [
                                'type' => 'missing_referenced_table',
                                'table' => $stmt['table'] ?? 'unknown',
                                'referenced_table' => $refTable,
                                'message' => "Referenced table '{$refTable}' does not exist in target database"
                            ];
                        } else {
                            // Check if referenced column exists
                            $checkColumn = $valPdo->query("
                                SELECT COUNT(*) as cnt 
                                FROM information_schema.COLUMNS 
                                WHERE TABLE_SCHEMA = " . $valPdo->quote($targetDb) . " 
                                AND TABLE_NAME = " . $valPdo->quote($refTable) . "
                                AND COLUMN_NAME = " . $valPdo->quote($refColumn)
                            )->fetch();
                            
                            if ($checkColumn['cnt'] == 0) {
                                $warnings[] = [
                                    'type' => 'missing_referenced_column',
                                    'table' => $stmt['table'] ?? 'unknown',
                                    'referenced_table' => $refTable,
                                    'referenced_column' => $refColumn,
                                    'message' => "Referenced column '{$refTable}.{$refColumn}' does not exist"
                                ];
                            }
                        }
                    } catch (Exception $e) {
                        $warnings[] = [
                            'type' => 'validation_error',
                            'message' => 'Could not validate FK: ' . $e->getMessage()
                        ];
                    }
                }
            }
        }
        
        return $warnings;
    }
    
    /**
     * Analyze foreign key error and provide diagnostics
     */
    private function analyzeForeignKeyError(string $targetDb, array $statement): array
    {
        $diagnostics = [];
        
        // Create a fresh PDO connection for diagnostics (don't use transaction PDO)
        try {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';
            
            $dsn = "mysql:host={$host};dbname={$targetDb};charset=utf8mb4";
            $diagPdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (Exception $e) {
            return ['Could not create diagnostic connection: ' . $e->getMessage()];
        }
        
        // Extract table names and columns from the SQL
        if (preg_match('/(?:TABLE|INTO)\s+`?(\w+)`?/i', $statement['sql'], $matches)) {
            $tableName = $matches[1];
            $diagnostics[] = "Table: {$tableName}";
        }
        
        // Extract foreign key details
        if (preg_match('/FOREIGN KEY\s*\(`?(\w+)`?\)\s*REFERENCES\s+`?(\w+)`?\s*\(`?(\w+)`?\)/i', 
                       $statement['sql'], $matches)) {
            $fkColumn = $matches[1];
            $refTable = $matches[2];
            $refColumn = $matches[3];
            
            $diagnostics[] = "FK Column: {$fkColumn} -> {$refTable}.{$refColumn}";
            
            // Check if referenced table exists
            try {
                $result = $diagPdo->query("
                    SELECT COUNT(*) as cnt 
                    FROM information_schema.TABLES 
                    WHERE TABLE_SCHEMA = " . $diagPdo->quote($targetDb) . " 
                    AND TABLE_NAME = " . $diagPdo->quote($refTable)
                )->fetch();
                
                if ($result['cnt'] == 0) {
                    $diagnostics[] = "❌ Referenced table '{$refTable}' does NOT exist";
                } else {
                    $diagnostics[] = "✓ Referenced table '{$refTable}' exists";
                    
                    // Check column
                    $colResult = $diagPdo->query("
                        SELECT COLUMN_TYPE, COLUMN_KEY 
                        FROM information_schema.COLUMNS 
                        WHERE TABLE_SCHEMA = " . $diagPdo->quote($targetDb) . " 
                        AND TABLE_NAME = " . $diagPdo->quote($refTable) . "
                        AND COLUMN_NAME = " . $diagPdo->quote($refColumn)
                    )->fetch();
                    
                    if (!$colResult) {
                        $diagnostics[] = "❌ Referenced column '{$refColumn}' does NOT exist in '{$refTable}'";
                    } else {
                        $diagnostics[] = "✓ Referenced column exists: {$refColumn} ({$colResult['COLUMN_TYPE']})";
                        
                        if (strpos($colResult['COLUMN_KEY'], 'PRI') === false && 
                            strpos($colResult['COLUMN_KEY'], 'UNI') === false) {
                            $diagnostics[] = "❌ Referenced column is NOT indexed (PRIMARY or UNIQUE key required)";
                        } else {
                            $diagnostics[] = "✓ Referenced column has proper index";
                        }
                    }
                }
                
                // Get table engine
                $engineResult = $diagPdo->query("
                    SELECT ENGINE 
                    FROM information_schema.TABLES 
                    WHERE TABLE_SCHEMA = " . $diagPdo->quote($targetDb) . " 
                    AND TABLE_NAME = " . $diagPdo->quote($refTable)
                )->fetch();
                
                if ($engineResult && $engineResult['ENGINE'] !== 'InnoDB') {
                    $diagnostics[] = "❌ Table engine is {$engineResult['ENGINE']} (should be InnoDB for FK support)";
                }
                
            } catch (Exception $e) {
                $diagnostics[] = "Error during diagnostics: " . $e->getMessage();
            }
        }
        
        $diagnostics[] = "\nCommon FK Error Causes:";
        $diagnostics[] = "1. Referenced table doesn't exist (create it first)";
        $diagnostics[] = "2. Referenced column doesn't exist";
        $diagnostics[] = "3. Referenced column is not indexed (needs PRIMARY or UNIQUE key)";
        $diagnostics[] = "4. Data types don't match exactly";
        $diagnostics[] = "5. Table engine is not InnoDB";
        $diagnostics[] = "6. Charset/collation mismatch";
        
        return $diagnostics;
    }
    
    /**
     * Validate migration with AI
     */
    private function validateWithAI(array $statements): array
    {
        $feedback = [];
        
        if (!$this->aiProvider) {
            return $feedback;
        }
        
        // Prepare SQL for AI review
        $sqlBatch = array_map(fn($s) => $s['sql'], $statements);
        $sqlText = implode("\n\n", $sqlBatch);
        
        $prompt = "Review the following database migration SQL for safety and correctness. " .
                 "Identify any potential issues, data loss risks, or performance concerns:\n\n" .
                 $sqlText;
        
        try {
            $response = $this->aiProvider->sendPrompt(
                AIProvider::getDefaultModel(),
                $prompt,
                "You are a database migration expert. Analyze SQL statements for safety and correctness."
            );
            
            $feedback[] = [
                'source' => 'ai_review',
                'response' => $response,
                'risk' => $this->assessRiskFromAI($response)
            ];
            
        } catch (Exception $e) {
            $this->log('warning', 'AI validation failed', ['error' => $e->getMessage()]);
        }
        
        return $feedback;
    }
    
    /**
     * Create a backup point for rollback
     */
    private function createBackupPoint(string $dbName): array
    {
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = PROJECT_ROOT . "/temp/db_backup_{$dbName}_{$timestamp}.sql";
        
        // Note: In production, use proper backup tools
        $command = sprintf(
            'sh mysqldump --single-transaction --routines --triggers %s > %s',
            escapeshellarg($dbName),
            escapeshellarg($backupFile)
        );
        
        exec($command, $output, $returnCode);
        
        return [
            'timestamp' => $timestamp,
            'file' => $backupFile,
            'success' => $returnCode === 0
        ];
    }
    
    /**
     * Save migration log to database
     */
    private function saveMigrationLog(string $targetDb): void
    {
        // Create a fresh PDO connection for logging (outside any transaction)
        try {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $user = $_ENV['DB_USER'] ?? 'root';
            $pass = $_ENV['DB_PASS'] ?? '';
            $port = $_ENV['DB_PORT'] ?? '3306';
            
            // Try to use the existing PDO's connection if available
            // to avoid socket/connection issues
            try {
                $logPdo = $this->pdo;
                // Test if connection is alive
                $logPdo->query('SELECT 1');
            } catch (Exception $e) {
                // Connection not usable, create fresh one
                $dsn = "mysql:host={$host};port={$port};dbname={$targetDb};charset=utf8mb4";
                $logPdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            }
            
            // Create migrations table if it doesn't exist
            $createTable = "
                CREATE TABLE IF NOT EXISTS `migration_history` (
                    `id` INT AUTO_INCREMENT PRIMARY KEY,
                    `executed_at` DATETIME NOT NULL,
                    `migration_type` VARCHAR(50),
                    `statements_count` INT,
                    `success` BOOLEAN,
                    `log_data` TEXT,
                    INDEX `idx_executed` (`executed_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ";
            
            $logPdo->exec($createTable);
            
            // Determine success based on log entries
            $success = true;
            foreach ($this->migrationLog as $entry) {
                if (isset($entry['status']) && $entry['status'] === 'failed') {
                    $success = false;
                    break;
                }
            }
            
            // Insert migration record
            $stmt = $logPdo->prepare("
                INSERT INTO `migration_history` 
                (executed_at, migration_type, statements_count, success, log_data)
                VALUES (NOW(), ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                'schema_sync',
                count($this->migrationLog),
                $success,
                json_encode($this->migrationLog, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            ]);
            
            $this->log('info', 'Migration log saved', ['log_id' => $logPdo->lastInsertId()]);
            
        } catch (Exception $e) {
            // Don't throw - logging failure shouldn't break the migration result
            // Just log it if logger is available
            if ($this->logger) {
                $this->log('debug', 'Could not save migration log', [
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
    
    // ============================================================================
    // SCHEMA INTROSPECTION METHODS
    // ============================================================================
    
    private function getTables(string $dbName): array
    {
        $stmt = $this->pdo->prepare("
            SELECT TABLE_NAME 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME
        ");
        $stmt->execute([$dbName]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    private function getViews(string $dbName): array
    {
        $stmt = $this->pdo->prepare("
            SELECT TABLE_NAME 
            FROM information_schema.TABLES 
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_TYPE = 'VIEW'
            ORDER BY TABLE_NAME
        ");
        $stmt->execute([$dbName]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    private function getColumns(string $dbName, string $tableName): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
            ORDER BY ORDINAL_POSITION
        ");
        $stmt->execute([$dbName, $tableName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getIndexes(string $dbName, string $tableName): array
    {
        $stmt = $this->pdo->prepare("
            SHOW INDEX FROM `{$dbName}`.`{$tableName}`
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getForeignKeys(string $dbName, string $tableName): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? 
            AND TABLE_NAME = ?
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $stmt->execute([$dbName, $tableName]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    private function getCreateTableStatement(string $dbName, string $tableName): ?string
    {
        $stmt = $this->pdo->query("SHOW CREATE TABLE `{$dbName}`.`{$tableName}`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['Create Table'] ?? null;
    }
    
    private function getCreateViewStatement(string $dbName, string $viewName): ?string
    {
        $stmt = $this->pdo->query("SHOW CREATE VIEW `{$dbName}`.`{$viewName}`");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row['Create View'] ?? null;
    }
    
    // ============================================================================
    // SQL GENERATION HELPERS
    // ============================================================================
    
    private function generateAddColumnSQL(string $dbName, string $tableName, array $column): string
    {
        $sql = "ALTER TABLE `{$dbName}`.`{$tableName}` ADD COLUMN `{$column['COLUMN_NAME']}` {$column['COLUMN_TYPE']}";
        
        if ($column['IS_NULLABLE'] === 'NO') {
            $sql .= ' NOT NULL';
        } else {
            $sql .= ' NULL';
        }
        
        if ($column['COLUMN_DEFAULT'] !== null) {
            $sql .= $this->formatDefaultValue($column['COLUMN_DEFAULT'], $column['COLUMN_TYPE']);
        } elseif ($column['IS_NULLABLE'] === 'YES') {
            // Explicitly set NULL as default for nullable columns if no default specified
            $sql .= ' DEFAULT NULL';
        }
        
        if ($column['EXTRA']) {
            $sql .= ' ' . $column['EXTRA'];
        }
        
        return $sql . ';';
    }
    
    private function generateModifyColumnSQL(string $dbName, string $tableName, array $column): string
    {
        $sql = "ALTER TABLE `{$dbName}`.`{$tableName}` MODIFY COLUMN `{$column['COLUMN_NAME']}` {$column['COLUMN_TYPE']}";
        
        if ($column['IS_NULLABLE'] === 'NO') {
            $sql .= ' NOT NULL';
        } else {
            $sql .= ' NULL';
        }
        
        if ($column['COLUMN_DEFAULT'] !== null) {
            $sql .= $this->formatDefaultValue($column['COLUMN_DEFAULT'], $column['COLUMN_TYPE']);
        } elseif ($column['IS_NULLABLE'] === 'YES') {
            // Explicitly set NULL as default for nullable columns if no default specified
            $sql .= ' DEFAULT NULL';
        }
        
        if ($column['EXTRA']) {
            $sql .= ' ' . $column['EXTRA'];
        }
        
        return $sql . ';';
    }
    
    /**
     * Format default value based on column type
     */
    private function formatDefaultValue(string $default, string $columnType): string
    {
        // Handle special SQL values
        if (strtoupper($default) === 'NULL') {
            return ' DEFAULT NULL';
        }
        
        if (stripos($default, 'current_timestamp') !== false || 
            stripos($default, 'now()') !== false) {
            return ' DEFAULT ' . $default;
        }
        
        // For ENUM and SET types, the default is already properly formatted
        // It comes from information_schema already quoted
        $lowerType = strtolower($columnType);
        if (strpos($lowerType, 'enum') === 0 || strpos($lowerType, 'set') === 0) {
            // Remove any extra quotes that might have been added
            $cleaned = trim($default, "'\"");
            return " DEFAULT '{$cleaned}'";
        }
        
        // For numeric types, don't quote
        if (preg_match('/^(int|integer|tinyint|smallint|mediumint|bigint|decimal|numeric|float|double|real)/i', $columnType)) {
            return ' DEFAULT ' . $default;
        }
        
        // For string types and others, quote the value
        return ' DEFAULT ' . $this->pdo->quote($default);
    }
    
    private function generateAddIndexSQL(string $dbName, string $tableName, array $index, ?array $allColumns = null): string
    {
        $indexName = $index['Key_name'];
        
        // Build column list for composite indexes
        if ($allColumns && count($allColumns) > 0) {
            $columnNames = array_map(function($col) {
                return "`{$col['Column_name']}`";
            }, $allColumns);
            $columnList = implode(', ', $columnNames);
        } else {
            $columnList = "`{$index['Column_name']}`";
        }
        
        if ($indexName === 'PRIMARY') {
            return "ALTER TABLE `{$dbName}`.`{$tableName}` ADD PRIMARY KEY ({$columnList});";
        } elseif ($index['Non_unique'] == 0) {
            return "ALTER TABLE `{$dbName}`.`{$tableName}` ADD UNIQUE INDEX `{$indexName}` ({$columnList});";
        } else {
            // Use IF NOT EXISTS to prevent duplicate key errors
            return "ALTER TABLE `{$dbName}`.`{$tableName}` ADD INDEX IF NOT EXISTS `{$indexName}` ({$columnList});";
        }
    }
    
    private function generateAddForeignKeySQL(string $dbName, string $tableName, array $fk): string
    {
        return sprintf(
            "ALTER TABLE `%s`.`%s` ADD CONSTRAINT `%s` FOREIGN KEY (`%s`) REFERENCES `%s` (`%s`);",
            $dbName,
            $tableName,
            $fk['CONSTRAINT_NAME'],
            $fk['COLUMN_NAME'],
            $fk['REFERENCED_TABLE_NAME'],
            $fk['REFERENCED_COLUMN_NAME']
        );
    }
    
    private function rebuildCreateViewSQL(string $createViewStatement, string $targetDb, string $viewName, bool $orReplace = false): string
    {
        // Extract the SELECT part of the view definition, which is the safest way
        if (preg_match('/AS\s+(SELECT.*)/is', $createViewStatement, $matches)) {
            $selectStatement = rtrim(trim($matches[1]), ';');
            
            $replace = $orReplace ? 'OR REPLACE ' : '';
            return "CREATE {$replace}VIEW `{$targetDb}`.`{$viewName}` AS {$selectStatement};";
        }
        
        // Fallback if the regex fails (less safe as it keeps DEFINER clauses)
        $replaceStr = $orReplace ? 'CREATE OR REPLACE' : 'CREATE';
        $statement = preg_replace(
            '/^CREATE(.*?)VIEW `[^`]+`\.`[^`]+`/i', 
            "{$replaceStr} VIEW `{$targetDb}`.`{$viewName}`", 
            $createViewStatement
        );

        return rtrim($statement, ' ;') . ';';
    }

    // ============================================================================
    // COMPARISON HELPERS
    // ============================================================================
    
    private function columnsAreDifferent(array $col1, array $col2): bool
    {
        // Normalize defaults for comparison
        $default1 = $this->normalizeDefault($col1['COLUMN_DEFAULT'] ?? null);
        $default2 = $this->normalizeDefault($col2['COLUMN_DEFAULT'] ?? null);
        
        // Compare type
        if (strcasecmp($col1['COLUMN_TYPE'], $col2['COLUMN_TYPE']) !== 0) {
            return true;
        }
        
        // Compare nullable
        if ($col1['IS_NULLABLE'] !== $col2['IS_NULLABLE']) {
            return true;
        }
        
        // Compare defaults
        if ($default1 !== $default2) {
            return true;
        }
        
        // Compare extra (auto_increment, etc.)
        $extra1 = strtolower(trim($col1['EXTRA'] ?? ''));
        $extra2 = strtolower(trim($col2['EXTRA'] ?? ''));
        if ($extra1 !== $extra2) {
            return true;
        }
        
        return false;
    }
    
    private function viewDefinitionsAreDifferent(?string $def1, ?string $def2, string $db1, string $db2): bool
    {
        if ($def1 === null || $def2 === null) {
            return $def1 !== $def2; // One is null, the other isn't
        }

        // A robust way to compare is to extract just the SELECT part, ignoring DEFINER, ALGORITHM, etc.
        $select1 = '';
        if (preg_match('/AS\s+(SELECT.*)/is', $def1, $matches)) {
            $select1 = $matches[1];
        }
        $select2 = '';
        if (preg_match('/AS\s+(SELECT.*)/is', $def2, $matches)) {
            $select2 = $matches[1];
        }

        // Normalize by removing schema names and standardizing whitespace
        $normalized1 = preg_replace("/`{$db1}`\./i", '', $select1);
        $normalized1 = preg_replace('/\s+/', ' ', trim($normalized1));
        
        $normalized2 = preg_replace("/`{$db2}`\./i", '', $select2);
        $normalized2 = preg_replace('/\s+/', ' ', trim($normalized2));

        return strcasecmp($normalized1, $normalized2) !== 0;
    }

    /**
     * Normalize default values for comparison
     */
    private function normalizeDefault($default): ?string
    {
        if ($default === null) {
            return null;
        }
        
        // Remove all types of quotes from string
        $normalized = trim($default);
        $normalized = trim($normalized, "'\"");
        $normalized = str_replace(["\\'", '\\"'], '', $normalized); // Remove escaped quotes
        
        // Handle NULL
        if (strtoupper($normalized) === 'NULL') {
            return null;
        }
        
        // Handle current_timestamp variations
        if (stripos($normalized, 'current_timestamp') !== false) {
            return 'CURRENT_TIMESTAMP';
        }
        
        return $normalized;
    }
    
    /**
     * Group indexes by name (for composite indexes)
     */
    private function groupIndexesByName(array $indexes): array
    {
        $grouped = [];
        
        foreach ($indexes as $idx) {
            $keyName = $idx['Key_name'];
            if (!isset($grouped[$keyName])) {
                $grouped[$keyName] = [];
            }
            $grouped[$keyName][] = $idx;
        }
        
        return $grouped;
    }
    
    private function indexExists(array $needle, array $haystack): bool
    {
        foreach ($haystack as $idx) {
            // Match by key name first
            if (strcasecmp($idx['Key_name'], $needle['Key_name']) === 0) {
                // For composite indexes, we should check all columns
                // But for simple comparison, matching name is enough
                return true;
            }
        }
        return false;
    }
    
    private function foreignKeyExists(array $needle, array $haystack): bool
    {
        foreach ($haystack as $fk) {
            if ($fk['CONSTRAINT_NAME'] === $needle['CONSTRAINT_NAME']) {
                return true;
            }
        }
        return false;
    }
    
    private function assessRiskFromAI(string $response): string
    {
        $lower = strtolower($response);
        if (strpos($lower, 'data loss') !== false || strpos($lower, 'dangerous') !== false) {
            return 'high';
        } elseif (strpos($lower, 'caution') !== false || strpos($lower, 'warning') !== false) {
            return 'medium';
        }
        return 'low';
    }
    
    private function log(string $level, string $message, array $context = []): void
    {
        if ($this->logger) {
            $method = strtolower($level);
            if (method_exists($this->logger, $method)) {
                // Ensure we always pass an array to the logger
                $this->logger->$method([$message => $context]);
            }
        }
    }
}
