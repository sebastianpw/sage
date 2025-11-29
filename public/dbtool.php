<?php
/**
 * dbtool.php - Lightweight Database Administration Tool
 * A single-file database management interface for MySQL/MariaDB
 * Part 1/4: Bootstrap, Styles, and Core Structure
 */

// Bootstrap and dependencies
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/env_locals.php';

// Security: Ensure authentication
AccessManager::authenticate();

// Initialize database connections
global $pdo, $pdoSys, $dbname, $sysDbName;

/**
 * Configuration
 */
define('DBTOOL_VERSION', '1.0.0');
define('RECORDS_PER_PAGE', 50);

/**
 * Helper Functions
 */
class DbTool {
    private static $pdo;
    private static $pdoSys;
    private static $currentDb;
    
    public static function init($pdo, $pdoSys, $dbname) {
        self::$pdo = $pdo;
        self::$pdoSys = $pdoSys;
        self::$currentDb = $dbname;
    }
    
    public static function getCurrentConnection() {
        $db = $_GET['db'] ?? 'main';
        
        // Handle "other:" databases
        if (strpos($db, 'other:') === 0) {
            global $pdoRoot;
            $dbName = substr($db, 6); // Remove "other:" prefix
            
            // Switch to the selected database
            if ($pdoRoot) {
                try {
                    $pdoRoot->exec("USE `" . self::escapeIdentifier($dbName) . "`");
                    return $pdoRoot;
                } catch (Exception $e) {
                    // Fall back to main if switching fails
                    return self::$pdo;
                }
            }
            return self::$pdo;
        }
        
        return $db === 'sys' ? self::$pdoSys : self::$pdo;
    }
    
    public static function getCurrentDbName() {
        $db = $_GET['db'] ?? 'main';
        
        // Handle "other:" databases
        if (strpos($db, 'other:') === 0) {
            return substr($db, 6); // Remove "other:" prefix
        }
        
        global $dbname, $sysDbName;
        return $db === 'sys' ? $sysDbName : $dbname;
    }
    
    public static function getDatabases() {
        global $dbname, $sysDbName;
        return [
            'main' => ['name' => $dbname, 'label' => 'Main Database'],
            'sys' => ['name' => $sysDbName, 'label' => 'System Database']
        ];
    }
    
    public static function listAllDatabases($pdo) {
        try {
            $stmt = $pdo->query("SHOW DATABASES");
            $databases = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Filter out system databases that shouldn't be managed
            $filtered = array_filter($databases, function($db) {
                return !in_array($db, ['information_schema', 'performance_schema', 'mysql', 'sys']);
            });
            
            return array_values($filtered);
        } catch (Exception $e) {
            return [];
        }
    }
    
    public static function getDatabaseInfo($pdo, $dbName) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    SCHEMA_NAME as name,
                    DEFAULT_CHARACTER_SET_NAME as charset,
                    DEFAULT_COLLATION_NAME as collation
                FROM information_schema.SCHEMATA 
                WHERE SCHEMA_NAME = ?
            ");
            $stmt->execute([$dbName]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return null;
        }
    }
    
    public static function getDatabaseSize($pdo, $dbName) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    SUM(data_length + index_length) as size
                FROM information_schema.TABLES 
                WHERE table_schema = ?
            ");
            $stmt->execute([$dbName]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['size'] ?? 0;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    public static function formatBytes($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
   


public static function getTables($pdo) {
    $stmt = $pdo->query("SHOW TABLES");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// START: ADD THIS NEW FUNCTION
public static function getTablesWithInfo($pdo, $dbName) {
    try {
        $sql = "
            SELECT 
                TABLE_NAME, 
                TABLE_TYPE, 
                TABLE_ROWS 
            FROM 
                information_schema.TABLES 
            WHERE 
                TABLE_SCHEMA = ? 
            ORDER BY 
                TABLE_NAME ASC
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$dbName]);
        // Use TABLE_NAME as the key for easy lookup
        return $stmt->fetchAll(PDO::FETCH_ASSOC | PDO::FETCH_UNIQUE);
    } catch (Exception $e) {
        return []; // Return empty on error
    }
}

    
    
    
    public static function getTableInfo($pdo, $table) {
        $stmt = $pdo->query("SHOW COLUMNS FROM `" . self::escapeIdentifier($table) . "`");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public static function getTableIndexes($pdo, $table) {
        $stmt = $pdo->query("SHOW INDEXES FROM `" . self::escapeIdentifier($table) . "`");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public static function getTableRowCount($pdo, $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM `" . self::escapeIdentifier($table) . "`");
            return $stmt->fetchColumn();
        } catch (Exception $e) {
            return 'N/A';
        }
    }
    
    public static function escapeIdentifier($identifier) {
        return str_replace('`', '``', $identifier);
    }
    
    public static function executeQuery($pdo, $sql, $params = []) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return ['success' => true, 'statement' => $stmt];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    public static function exportTableAsSQL($pdo, $table) {
        $sql = "-- Export of table: $table\n\n";
        
        // Get CREATE TABLE statement
        $stmt = $pdo->query("SHOW CREATE TABLE `" . self::escapeIdentifier($table) . "`");
        $create = $stmt->fetch(PDO::FETCH_ASSOC);
        $sql .= $create['Create Table'] . ";\n\n";
        
        // Get data
        $stmt = $pdo->query("SELECT * FROM `" . self::escapeIdentifier($table) . "`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($rows) {
            foreach ($rows as $row) {
                $values = array_map(function($v) use ($pdo) {
                    return $v === null ? 'NULL' : $pdo->quote($v);
                }, $row);
                
                $sql .= "INSERT INTO `" . self::escapeIdentifier($table) . "` VALUES (" . implode(', ', $values) . ");\n";
            }
        }
        
        return $sql;
    }
    
    public static function exportTableAsCSV($pdo, $table) {
        $stmt = $pdo->query("SELECT * FROM `" . self::escapeIdentifier($table) . "`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($rows)) return '';
        
        $csv = '';
        // Header
        $csv .= implode(',', array_map(function($col) {
            return '"' . str_replace('"', '""', $col) . '"';
        }, array_keys($rows[0]))) . "\n";
        
        // Data
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(function($val) {
                return '"' . str_replace('"', '""', $val ?? '') . '"';
            }, $row)) . "\n";
        }
        
        return $csv;
    }
}

DbTool::init($pdo, $pdoSys, $dbname);

// Root connection for database management (optional)
$pdoRoot = null;
if (isset($spw) && method_exists($spw, 'getRootPDO')) {
    try {
        $pdoRoot = $spw->getRootPDO();
    } catch (Exception $e) {
        // Root connection not available, that's okay
        $pdoRoot = null;
    }
}

// If no explicit root connection, try to use one of the existing connections
if (!$pdoRoot) {
    // Try sys connection first as it might have more privileges
    $pdoRoot = $pdoSys ?? $pdo;
}

/**
 * Action Handlers
 */
$action = $_GET['action'] ?? 'home';
$table = $_GET['table'] ?? null;
$currentPdo = DbTool::getCurrentConnection();
$currentDbName = DbTool::getCurrentDbName();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? $action;
    
    if ($postAction === 'execute_sql') {
        $sql = $_POST['sql'] ?? '';
        $result = DbTool::executeQuery($currentPdo, $sql);
        
        if ($result['success']) {
            $message = ['type' => 'success', 'text' => 'Query executed successfully.'];
            if ($result['statement']->columnCount() > 0) {
                $queryResults = $result['statement']->fetchAll(PDO::FETCH_ASSOC);
            }
        } else {
            $message = ['type' => 'error', 'text' => 'Error: ' . $result['error']];
        }
    }
    
    if ($postAction === 'create_database') {
        global $pdoRoot;
        $dbName = $_POST['db_name'] ?? '';
        $charset = $_POST['db_charset'] ?? 'utf8mb4';
        $collation = $_POST['db_collation'] ?? 'utf8mb4_general_ci';
        
        // Validate database name
        if (empty($dbName) || !preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
            $message = ['type' => 'error', 'text' => 'Invalid database name. Only letters, numbers, and underscores allowed.'];
        } else {
            try {
                $sql = "CREATE DATABASE `" . DbTool::escapeIdentifier($dbName) . "` CHARACTER SET $charset COLLATE $collation";
                $pdoRoot->exec($sql);
                $message = ['type' => 'success', 'text' => "Database '$dbName' created successfully."];
            } catch (PDOException $e) {
                $message = ['type' => 'error', 'text' => 'Error creating database: ' . $e->getMessage()];
            }
        }
    }
    
    if ($postAction === 'drop_database') {
        global $pdoRoot;
        $dbName = $_POST['db_name'] ?? '';
        
        // Prevent dropping current databases
        global $dbname, $sysDbName;
        if ($dbName === $dbname || $dbName === $sysDbName) {
            $message = ['type' => 'error', 'text' => 'Cannot drop the currently active database.'];
        } elseif (empty($dbName)) {
            $message = ['type' => 'error', 'text' => 'No database specified.'];
        } else {
            try {
                $sql = "DROP DATABASE `" . DbTool::escapeIdentifier($dbName) . "`";
                $pdoRoot->exec($sql);
                $message = ['type' => 'success', 'text' => "Database '$dbName' dropped successfully."];
            } catch (PDOException $e) {
                $message = ['type' => 'error', 'text' => 'Error dropping database: ' . $e->getMessage()];
            }
        }
    }
    
    if ($postAction === 'insert_row' && $table) {
        $columns = $_POST['columns'] ?? [];
        $values = $_POST['values'] ?? [];
        
        $cols = array_map(function($c) { return "`" . DbTool::escapeIdentifier($c) . "`"; }, $columns);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = "INSERT INTO `" . DbTool::escapeIdentifier($table) . "` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $result = DbTool::executeQuery($currentPdo, $sql, $values);
        
        if ($result['success']) {
            $message = ['type' => 'success', 'text' => 'Row inserted successfully.'];
            header("Location: ?db=" . ($_GET['db'] ?? 'main') . "&action=browse&table=" . urlencode($table));
            exit;
        } else {
            $message = ['type' => 'error', 'text' => 'Error: ' . $result['error']];
        }
    }
    
    if ($postAction === 'update_row' && $table) {
        $id = $_POST['id'] ?? null;
        $idColumn = $_POST['id_column'] ?? 'id';
        $columns = $_POST['columns'] ?? [];
        $values = $_POST['values'] ?? [];
        
        $sets = array_map(function($c) { return "`" . DbTool::escapeIdentifier($c) . "` = ?"; }, $columns);
        $values[] = $id;
        
        $sql = "UPDATE `" . DbTool::escapeIdentifier($table) . "` SET " . implode(', ', $sets) . " WHERE `" . DbTool::escapeIdentifier($idColumn) . "` = ?";
        $result = DbTool::executeQuery($currentPdo, $sql, $values);
        
        if ($result['success']) {
            $message = ['type' => 'success', 'text' => 'Row updated successfully.'];
            header("Location: ?db=" . ($_GET['db'] ?? 'main') . "&action=browse&table=" . urlencode($table));
            exit;
        } else {
            $message = ['type' => 'error', 'text' => 'Error: ' . $result['error']];
        }
    }
    
    if ($postAction === 'delete_row' && $table) {
        $id = $_POST['id'] ?? null;
        $idColumn = $_POST['id_column'] ?? 'id';
        
        $sql = "DELETE FROM `" . DbTool::escapeIdentifier($table) . "` WHERE `" . DbTool::escapeIdentifier($idColumn) . "` = ?";
        $result = DbTool::executeQuery($currentPdo, $sql, [$id]);
        
        if ($result['success']) {
            $message = ['type' => 'success', 'text' => 'Row deleted successfully.'];
        } else {
            $message = ['type' => 'error', 'text' => 'Error: ' . $result['error']];
        }
    }
    
    if ($postAction === 'delete_rows' && $table) {
        $ids = json_decode($_POST['ids'] ?? '[]', true);
        $idColumn = $_POST['id_column'] ?? 'id';
        
        if (empty($ids)) {
            $message = ['type' => 'error', 'text' => 'No rows selected for deletion.'];
        } else {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "DELETE FROM `" . DbTool::escapeIdentifier($table) . "` WHERE `" . DbTool::escapeIdentifier($idColumn) . "` IN ($placeholders)";
            $result = DbTool::executeQuery($currentPdo, $sql, $ids);
            
            if ($result['success']) {
                $count = count($ids);
                $message = ['type' => 'success', 'text' => "Successfully deleted $count row" . ($count > 1 ? 's' : '') . "."];
            } else {
                $message = ['type' => 'error', 'text' => 'Error: ' . $result['error']];
            }
        }
    }
    
    
   if ($postAction === 'execute_alter' && $table) {
        $alterSql = $_POST['alter_sql'] ?? '';
        
        if (empty($alterSql) || strpos($alterSql, '-- No changes') !== false) {
            $message = ['type' => 'error', 'text' => 'No SQL to execute.'];
        } else {
            // Split by semicolon and execute each statement
            $statements = array_filter(array_map('trim', explode(';', $alterSql)));
            $errors = [];
            $success = 0;
            
            foreach ($statements as $stmt) {
                if (empty($stmt)) continue;
                $result = DbTool::executeQuery($currentPdo, $stmt);
                if ($result['success']) {
                    $success++;
                } else {
                    $errors[] = $result['error'];
                }
            }
            
            if (empty($errors)) {
                $message = ['type' => 'success', 'text' => "Successfully executed $success statement(s). Table structure updated."];
                // Redirect to structure view
                header("Location: ?db=" . ($_GET['db'] ?? 'main') . "&action=structure&table=" . urlencode($table) . "&updated=1");
                exit;
            } else {
                $message = ['type' => 'error', 'text' => "Executed $success statement(s), but encountered errors: " . implode('; ', $errors)];
            }
        }
    }
    
    
    
    
    
    
   if ($postAction === 'dump_database') {
        $dbName = DbTool::getCurrentDbName();
        
        $dump = "-- Full Database Dump for `$dbName`\n";
        $dump .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        
        set_time_limit(300); // Extend for large databases
        
        // -----------------------------
        // Step 1: Dump BASE TABLEs (structure + data)
        // -----------------------------
        $tables = [];
        $res = $currentPdo->query("
            SELECT TABLE_NAME 
            FROM information_schema.tables 
            WHERE table_schema='$dbName' AND TABLE_TYPE='BASE TABLE'
        ");
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $tables[] = $row['TABLE_NAME'];
        }
        
        $dump .= "-- ========================================================\n";
        $dump .= "-- Step 1: Tables (structure + data)\n";
        $dump .= "-- ========================================================\n\n";
        
        foreach ($tables as $table) {
            $dump .= "-- --------------------------------------------------------\n";
            $dump .= "-- Table: `$table`\n";
            $dump .= "-- --------------------------------------------------------\n\n";
            
            // Table structure
            $dump .= "DROP TABLE IF EXISTS `$table`;\n\n";
            $res = $currentPdo->query("SHOW CREATE TABLE `$table`");
            $row = $res->fetch(PDO::FETCH_ASSOC);
            $createStmt = $row['Create Table'] ?? '';
            $dump .= $createStmt . ";\n\n";
            
            // Table data
            $res = $currentPdo->query("SELECT * FROM `$table`");
            $rows = $res->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($rows)) {
                $dump .= "-- Data for `$table`\n";
                foreach ($rows as $r) {
                    $cols = array_map(function($c) { return "`$c`"; }, array_keys($r));
                    $vals = array_map(function($v) use ($currentPdo) {
                        return $v === null ? "NULL" : $currentPdo->quote($v);
                    }, array_values($r));
                    $dump .= "INSERT INTO `$table` (" . implode(",", $cols) . ") VALUES (" . implode(",", $vals) . ");\n";
                }
                $dump .= "\n";
            }
        }
        
        // -----------------------------
        // Step 2: Dump VIEWs (structure only)
        // -----------------------------
        $views = [];
        $res = $currentPdo->query("
            SELECT TABLE_NAME 
            FROM information_schema.tables 
            WHERE table_schema='$dbName' AND TABLE_TYPE='VIEW'
        ");
        while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
            $views[] = $row['TABLE_NAME'];
        }
        
        if (!empty($views)) {
            $dump .= "-- ========================================================\n";
            $dump .= "-- Step 2: Views (structure only)\n";
            $dump .= "-- ========================================================\n\n";
            
            foreach ($views as $view) {
                $dump .= "-- --------------------------------------------------------\n";
                $dump .= "-- View: `$view`\n";
                $dump .= "-- --------------------------------------------------------\n\n";
                
                $dump .= "DROP VIEW IF EXISTS `$view`;\n\n";
                $res = $currentPdo->query("SHOW CREATE VIEW `$view`");
                $row = $res->fetch(PDO::FETCH_ASSOC);
                $createViewStmt = $row['Create View'] ?? '';
                $dump .= $createViewStmt . ";\n\n";
            }
        }
        
        // -----------------------------
        // Step 3: Dump TRIGGERS
        // -----------------------------
        $res = $currentPdo->query("
            SELECT TRIGGER_NAME, EVENT_MANIPULATION, EVENT_OBJECT_TABLE, ACTION_STATEMENT, ACTION_TIMING
            FROM information_schema.triggers
            WHERE TRIGGER_SCHEMA='$dbName'
        ");
        $triggers = $res->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($triggers)) {
            $dump .= "-- ========================================================\n";
            $dump .= "-- Step 3: Triggers\n";
            $dump .= "-- ========================================================\n\n";
            
            foreach ($triggers as $row) {
                $triggerName = $row['TRIGGER_NAME'];
                $table = $row['EVENT_OBJECT_TABLE'];
                $timing = $row['ACTION_TIMING']; // BEFORE / AFTER
                $event = $row['EVENT_MANIPULATION']; // INSERT / UPDATE / DELETE
                $stmt = $row['ACTION_STATEMENT'];
                
                $dump .= "-- Trigger: `$triggerName` on `$table`\n";
                $dump .= "DELIMITER //\n";
                $dump .= "DROP TRIGGER IF EXISTS `$triggerName`;//\n";
                $dump .= "CREATE TRIGGER `$triggerName` $timing $event ON `$table`\nFOR EACH ROW $stmt //\n";
                $dump .= "DELIMITER ;\n\n";
            }
        }
        
        // -----------------------------
        // Serve the SQL dump as download
        // -----------------------------
        $filename = $dbName . '_full_dump_' . date('Ymd_His') . '.sql';
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dump;
        exit;
    }
    
    
    
    
if ($postAction === 'execute_import') {
        $ignoreErrors = isset($_POST['ignore_errors']);
        
        if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            $message = ['type' => 'error', 'text' => 'Failed to upload file. Error: ' . ($_FILES['sql_file']['error'] ?? 'unknown')];
        } else {
            $filePath = $_FILES['sql_file']['tmp_name'];
            $fileSize = $_FILES['sql_file']['size'];
            $fileName = $_FILES['sql_file']['name'];
            
            // Read file content
            $sqlContent = file_get_contents($filePath);
            
            // Fix 1: Remove the UTF-8 BOM if it exists
            if (substr($sqlContent, 0, 3) === "\xEF\xBB\xBF") {
                $sqlContent = substr($sqlContent, 3);
            }

            if ($sqlContent === false) {
                $message = ['type' => 'error', 'text' => 'Failed to read file content.'];
            } else {
                // =================================================================
                // FIX: Remove all comments from the SQL content BEFORE splitting.
                // This prevents comments at the start of the file from being
                // attached to the first query.
                // =================================================================
                // Remove single-line comments (--, #)
                $sqlContent = preg_replace('/^\s*(--|#).*$/m', '', $sqlContent);
                // Remove multi-line comments (/* ... */)
                $sqlContent = preg_replace('#/\*.*?\*/#s', '', $sqlContent);


                // Disable foreign key checks for the import
                $currentPdo->exec("SET FOREIGN_KEY_CHECKS = 0");
                
                // Split SQL statements
                $statements = preg_split('/;[\s]*(\r?\n|$)/', $sqlContent, -1, PREG_SPLIT_NO_EMPTY);
                $statements = array_map('trim', $statements);
                $statements = array_filter($statements); // Remove empty strings
                
                $totalStatements = count($statements);
                $successCount = 0;
                $errorCount = 0;
                $errors = [];
                
                set_time_limit(300); // Extend execution time for large imports
                
                foreach ($statements as $stmt) {
                    // The checks below are now mostly redundant but act as a good safeguard.
                    if (empty($stmt) || 
                        substr($stmt, 0, 2) === '--' || 
                        substr($stmt, 0, 2) === '/*' ||
                        substr($stmt, 0, 1) === '#') {
                        continue;
                    }
                    
                    if (stripos($stmt, 'DELIMITER') === 0) {
                        continue;
                    }
                    
                    $result = DbTool::executeQuery($currentPdo, $stmt);
                    
                    if ($result['success']) {
                        $successCount++;
                    } else {
                        $errorCount++;
                        $preview = substr($stmt, 0, 100);
                        $errors[] = $preview . '... - ' . $result['error'];
                        
                        if (!$ignoreErrors) {
                            break; 
                        }
                    }
                }
                
                // Re-enable foreign key checks
                $currentPdo->exec("SET FOREIGN_KEY_CHECKS = 1");
                
                if ($errorCount === 0) {
                    $message = ['type' => 'success', 'text' => "Successfully imported file '$fileName'. Executed $successCount statement(s)."];
                } elseif ($successCount > 0) {
                    $message = ['type' => 'warning', 'text' => "Partially imported '$fileName'. Success: $successCount, Errors: $errorCount. First errors: " . implode('; ', array_slice($errors, 0, 3))];
                } else {
                    $message = ['type' => 'error', 'text' => "Import failed for '$fileName'. Errors: " . implode('; ', array_slice($errors, 0, 3))];
                }
            }
        }
    }

    
    
if ($postAction === 'execute_multi_export') {
        $structureTables = $_POST['structure'] ?? [];
        $dataTables = $_POST['data'] ?? [];
        
        if (empty($structureTables) && empty($dataTables)) {
            $message = ['type' => 'error', 'text' => 'No tables selected for export.'];
        } else {
            // Get list of views
            $viewsStmt = $currentPdo->query("SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = '" . $currentPdo->query("SELECT DATABASE()")->fetchColumn() . "'");
            $views = $viewsStmt ? $viewsStmt->fetchAll(PDO::FETCH_COLUMN) : [];
            $viewsList = array_flip($views);
            
            $sql = "-- Multi-Table Export\n";
            $sql .= "-- Database: " . DbTool::getCurrentDbName() . "\n";
            $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- Tables with structure: " . count($structureTables) . "\n";
            $sql .= "-- Tables with data: " . count($dataTables) . "\n\n";
            $sql .= "-- Export order: Tables first, then Views\n\n";
            
            // Get unique list of all tables and separate tables from views
            $allTables = array_unique(array_merge($structureTables, $dataTables));
            $tablesOnly = [];
            $viewsOnly = [];
            
            foreach ($allTables as $table) {
                if (isset($viewsList[$table])) {
                    $viewsOnly[] = $table;
                } else {
                    $tablesOnly[] = $table;
                }
            }
            
            // Process tables first
            if (!empty($tablesOnly)) {
                $sql .= "-- ========================================================\n";
                $sql .= "-- TABLES\n";
                $sql .= "-- ========================================================\n\n";
                
                foreach ($tablesOnly as $table) {
                    $sql .= "-- --------------------------------------------------------\n";
                    $sql .= "-- Table: `$table`\n";
                    $sql .= "-- --------------------------------------------------------\n\n";
                    
                    // Export structure
                    if (in_array($table, $structureTables)) {
                        $sql .= "DROP TABLE IF EXISTS `$table`;\n\n";
                        
                        try {
                            $stmt = $currentPdo->query("SHOW CREATE TABLE `" . DbTool::escapeIdentifier($table) . "`");
                            $create = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if (isset($create['Create Table'])) {
                                $sql .= $create['Create Table'] . ";\n\n";
                            }
                        } catch (Exception $e) {
                            $sql .= "-- Error exporting table: " . $e->getMessage() . "\n\n";
                        }
                    }
                    
                    // Export data
                    if (in_array($table, $dataTables)) {
                        try {
                            $stmt = $currentPdo->query("SELECT * FROM `" . DbTool::escapeIdentifier($table) . "`");
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (!empty($rows)) {
                                foreach ($rows as $row) {
                                    $values = array_map(function($v) use ($currentPdo) {
                                        return $v === null ? 'NULL' : $currentPdo->quote($v);
                                    }, $row);
                                    
                                    $sql .= "INSERT INTO `" . DbTool::escapeIdentifier($table) . "` VALUES (" . implode(', ', $values) . ");\n";
                                }
                                $sql .= "\n";
                            }
                        } catch (Exception $e) {
                            $sql .= "-- Error exporting data: " . $e->getMessage() . "\n\n";
                        }
                    }
                }
            }
            
            // Process views after tables
            if (!empty($viewsOnly)) {
                $sql .= "-- ========================================================\n";
                $sql .= "-- VIEWS (created after tables)\n";
                $sql .= "-- ========================================================\n\n";
                
                foreach ($viewsOnly as $table) {
                    $sql .= "-- --------------------------------------------------------\n";
                    $sql .= "-- View: `$table`\n";
                    $sql .= "-- --------------------------------------------------------\n\n";
                    
                    // Export structure
                    if (in_array($table, $structureTables)) {
                        $sql .= "DROP VIEW IF EXISTS `$table`;\n\n";
                        
                        try {
                            $stmt = $currentPdo->query("SHOW CREATE VIEW `" . DbTool::escapeIdentifier($table) . "`");
                            $create = $stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if (isset($create['Create View'])) {
                                $sql .= $create['Create View'] . ";\n\n";
                            }
                        } catch (Exception $e) {
                            $sql .= "-- Error exporting view: " . $e->getMessage() . "\n\n";
                        }
                    }
                    
                    // Export data (if selected)
                    if (in_array($table, $dataTables)) {
                        try {
                            $stmt = $currentPdo->query("SELECT * FROM `" . DbTool::escapeIdentifier($table) . "`");
                            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (!empty($rows)) {
                                $sql .= "-- Data from view (INSERT statements)\n";
                                foreach ($rows as $row) {
                                    $values = array_map(function($v) use ($currentPdo) {
                                        return $v === null ? 'NULL' : $currentPdo->quote($v);
                                    }, $row);
                                    
                                    $sql .= "INSERT INTO `" . DbTool::escapeIdentifier($table) . "` VALUES (" . implode(', ', $values) . ");\n";
                                }
                                $sql .= "\n";
                            }
                        } catch (Exception $e) {
                            $sql .= "-- Error exporting data: " . $e->getMessage() . "\n\n";
                        }
                    }
                }
            }
            
            // Download as file
            $filename = DbTool::getCurrentDbName() . '_export_' . date('Y-m-d_His') . '.sql';
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $sql;
            exit;
        }
    }
    
    
    
    
}





// Handle export downloads
if ($action === 'export' && $table) {
    $format = $_GET['format'] ?? 'sql';
    
    if ($format === 'sql') {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . $table . '_export.sql"');
        echo DbTool::exportTableAsSQL($currentPdo, $table);
        exit;
    } elseif ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $table . '_export.csv"');
        echo DbTool::exportTableAsCSV($currentPdo, $table);
        exit;
    }
}

?>
<!DOCTYPE html>
<html lang="en" data-theme="">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=0.7">
    <title>Database Tool - <?= htmlspecialchars($currentDbName) ?></title>
    <script>
    (function() {
        try {
            var theme = localStorage.getItem('spw_theme');
            if (theme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
            } else if (theme === 'light') {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        } catch (e) {}
    })();
    </script>
    <link rel="stylesheet" href="/css/base.css">
    <style>
        /* Additional DB Tool specific styles */
        .dbtool-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .dbtool-nav {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .db-selector {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            background: rgba(var(--card-rgb), 1);
            border: 1px solid rgba(var(--muted-border-rgb), 0.12);
            color: rgba(var(--text-rgb), 1);
            font-size: 0.9rem;
        }
        
        .table-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }
        
        .table-card {
            background: rgba(var(--card-rgb), 1);
            border: 1px solid rgba(var(--muted-border-rgb), 0.12);
            border-radius: 8px;
            padding: 1rem;
            transition: all 0.2s;
        }
        
        .table-card:hover {
            border-color: rgba(var(--accent-rgb), 0.5);
            transform: translateY(-2px);
        }
        
        .table-card-title {
            font-weight: 600;
            color: rgba(var(--text-rgb), 1);
            margin-bottom: 0.5rem;
            font-size: 1rem;
        }
        
        .table-card-meta {
            font-size: 0.85rem;
            color: rgba(var(--text-rgb), 0.6);
        }
        
        .sql-editor {
            width: 100%;
            min-height: 200px;
            font-family: 'Courier New', monospace;
            padding: 1rem;
            border-radius: 6px;
            background: rgba(var(--surface-rgb), 1);
            border: 1px solid rgba(var(--muted-border-rgb), 0.12);
            color: rgba(var(--text-rgb), 1);
            resize: vertical;
        }
        
        .data-table-wrapper {
            overflow-x: auto;
            margin-top: 1rem;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        
        .data-table th,
        .data-table td {
            padding: 0.25rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid rgba(var(--muted-border-rgb), 0.12);
            vertical-align: middle;
            line-height: 1.4;
        }
        
        .data-table th {
            background: rgba(var(--surface-rgb), 1);
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .data-table tr:hover {
            background: rgba(var(--hover-rgb), 0.05);
        }
        
        .data-table td {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }



        .null-value {
            color: rgba(var(--text-rgb), 0.4);
            font-style: italic;
        }
        
        .pagination {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        
        .breadcrumb {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 0.9rem;
            color: rgba(var(--text-rgb), 0.7);
        }
        
        .breadcrumb a {
            color: rgba(var(--accent-rgb), 1);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        .structure-table {
            width: 100%;
            margin-top: 1rem;
        }
        
        .structure-table th,
        .structure-table td {
            padding: 0.25rem 0.75rem;
            text-align: left;
            border-bottom: 1px solid rgba(var(--muted-border-rgb), 0.12);
            vertical-align: middle;
            line-height: 1.4;
        }
        
        .structure-table th {
            background: rgba(var(--surface-rgb), 1);
            font-weight: 600;
        }
        
        .code-inline {
            font-family: 'Courier New', monospace;
            background: rgba(var(--surface-rgb), 1);
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-size: 0.9em;
        }
                       /* Sortable table headers */
        .data-table th a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: color 0.2s;
        }
        
        .data-table th a:hover {
            color: rgba(var(--accent-rgb), 1);
        }
        
        .data-table th {
            user-select: none;
        }
        
        .data-table th a span {
            display: inline-flex;
            align-items: center;
            font-size: 0.9em;
        }
/* Zebra striping for better readability */
        .data-table tbody tr:nth-child(odd) {
            background: rgba(0, 0, 0, 0.04);
        }
        
        .data-table tbody tr:nth-child(even) {
            background: transparent;
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(odd) {
            background: rgba(255, 255, 255, 0.06);
        }
        
        [data-theme="dark"] .data-table tbody tr:nth-child(even) {
            background: transparent;
        }
        
        .data-table tbody tr:hover {
            background: rgba(var(--accent-rgb), 0.08) !important;
        }
        
        /* Same for structure tables */
        .structure-table tbody tr:nth-child(odd) {
            background: rgba(0, 0, 0, 0.04);
        }
        
        .structure-table tbody tr:nth-child(even) {
            background: transparent;
        }
        
        [data-theme="dark"] .structure-table tbody tr:nth-child(odd) {
            background: rgba(255, 255, 255, 0.06);
        }
        
        [data-theme="dark"] .structure-table tbody tr:nth-child(even) {
            background: transparent;
        }
        
        .structure-table tbody tr:hover {
            background: rgba(var(--accent-rgb), 0.08) !important;
        }
        
        
        
        
        
        
        
        
        /* responsive add ons */
        
        
/* Structure editor responsive - horizontal scroll on all devices */
        #columnsContainer,
        #indexesContainer {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .column-row > div,
        .index-row > div {
            min-width: 900px; /* Ensure full width is maintained */
        }
        
        /* Slightly smaller on tablets for better fit */
        @media (max-width: 1200px) {
            .column-row .form-control-sm,
            .index-row .form-control-sm {
                font-size: 0.9rem;
            }
        }
        
        
        
    </style>
</head>
<body>
    <div class="container">
        <!-- CONTINUE TO PART 2 -->



        

        
        
        
        
        
        
        <!-- Header -->
        <div class="dbtool-header">
            <!--
            <div>
                <h1>Database Tool</h1>
                <div class="flex-gap" style="margin-top: 0.5rem;">
                    <span class="badge badge-blue">v<?= DBTOOL_VERSION ?></span>
                    <span class="badge badge-gray"><?= htmlspecialchars($currentDbName) ?></span>
                </div>
            </div>
            -->
            <div class="dbtool-nav">
                <select style="margin-bottom:12px;display:block;width:100%;" class="db-selector" onchange="window.location.href='?db=' + this.value + '<?= $action !== 'home' ? '&action=' . urlencode($action) : '' ?>'">
                    <?php 
                    // Determine current DB (handle both format styles)
                    $currentDb = $_POST['preserve_db'] ?? $_GET['db'] ?? 'main';
                    
                    // Show main and sys first
                    foreach (DbTool::getDatabases() as $key => $db): 
                    ?>
                        <option value="<?= $key ?>" <?= ($key === $currentDb) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($db['label']) ?>
                        </option>
                    <?php endforeach; ?>
                    
                    <?php 
                    // Add separator and other databases if root connection exists
                    global $pdoRoot;
                    if ($pdoRoot):
                        $allDatabases = DbTool::listAllDatabases($pdoRoot);
                        $configuredDbs = [DbTool::getDatabases()['main']['name'], DbTool::getDatabases()['sys']['name']];
                        $otherDatabases = array_diff($allDatabases, $configuredDbs);
                        
                        if (!empty($otherDatabases)):
                    ?>
                            <option disabled>──────────</option>
                            <?php foreach ($otherDatabases as $otherDb): 
                                $otherDbKey = 'other:' . $otherDb;
                            ?>
                                <option value="<?= htmlspecialchars($otherDbKey) ?>" 
                                        <?= ($currentDb === $otherDbKey) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($otherDb) ?>
                                </option>
                            <?php endforeach; ?>
                    <?php 
                        endif;
                    endif; 
                    ?>
                </select>
                
                
                               

                <a href="?db=<?= $_GET['db'] ?? 'main' ?>" class="btn btn-sm <?= ($_GET['action'] ?? '') === '' ? 'btn-accent' : '' ?>">Home</a>
                <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=sql" class="btn btn-sm <?= ($_GET['action'] ?? '') === 'sql' ? 'btn-accent' : '' ?>">SQL Query</a>
                <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=manage_databases" class="btn btn-sm <?= ($_GET['action'] ?? '') === 'manage_databases' ? 'btn-accent' : '' ?>">Databases</a>
                <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=import" class="btn btn-sm <?= ($_GET['action'] ?? '') === 'import' ? 'btn-accent' : '' ?>">Import</a>
                <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=multi_export" class="btn btn-sm <?= ($_GET['action'] ?? '') === 'multi_export' ? 'btn-accent' : '' ?>">Export</a>



                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="dump_database">
                    <button type="submit" class="btn btn-sm" title="Export entire database">💾 Dump DB</button>
                </form> 
                
                
            </div>
        </div>
        
        
        
        
        
        
        

        <?php if (isset($message)): ?>
            <div class="notification notification-<?= $message['type'] === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message['text']) ?>
            </div>
        <?php endif; ?>
        
        
        
        
        

        <!-- Breadcrumb -->
        <?php if ($action !== 'home'): ?>
            <div class="breadcrumb">
                <a href="?db=<?= $_GET['db'] ?? 'main' ?>">Home</a>
                <span>/</span>
                <?php if ($table): ?>
                    <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=structure&table=<?= urlencode($table) ?>">
                        <?= htmlspecialchars($table) ?>
                    </a>
                    <?php if ($action !== 'structure'): ?>
                        <span>/</span>
                        <span><?= ucfirst($action) ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span><?= ucfirst($action) ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        
        
        
        
        
        
        
        


<!-- Main Content -->
<?php if ($action === 'home'): ?>
            <!-- HOME VIEW: Table List -->
            <div class="section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 class="section-header" style="margin: 0; display: none;">Tables in <?= htmlspecialchars($currentDbName) ?></h2>
                </div>
                
                <?php
                // MODIFICATION START: Use the new efficient function
                $tablesInfo = DbTool::getTablesWithInfo($currentPdo, $currentDbName);
                
                $totalCount = count($tablesInfo);
                $viewCount = 0;
                foreach ($tablesInfo as $info) {
                    if ($info['TABLE_TYPE'] === 'VIEW') {
                        $viewCount++;
                    }
                }
                $tableCount = $totalCount - $viewCount;
                // MODIFICATION END
                
                if (empty($tablesInfo)):
                ?>

               
                    <div class="empty-state">
                        <p>No tables found in this database.</p>
                    </div>
                <?php else: ?>
                    <!-- Search Input -->
                    <div class="form-group" style="margin-bottom: 1rem;">
                        <input type="text" 
                               id="tableSearch" 
                               class="form-control" 
                               placeholder="Search tables and views... (<?= $tableCount ?> tables, <?= $viewCount ?> views)"
                               style="max-width: 400px;">
                    </div>
                    
                    <div id="tableCount" style="margin-bottom: 0.5rem; color: rgba(var(--text-rgb), 0.7); font-size: 0.9rem;">
                        Showing <strong><span id="visibleCount"><?= $totalCount ?></span></strong> of <strong><?= $totalCount ?></strong> (<?= $tableCount ?> tables, <?= $viewCount ?> views)
                    </div>
                    
                    <div class="data-table-wrapper">
                        <table class="data-table" id="tablesListTable">
                            <thead>
                                <tr>
                                    <th style="width: 40%;">Name</th>
                                    <th style="width: 15%;">Type</th>
                                    <th style="width: 15%;">Rows</th>
                                    <th style="width: 30%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            
                            
                            
                            
                            
                           
    <?php foreach ($tablesInfo as $tableName => $info): ?>
        <?php 
        // MODIFICATION START: Use data from our pre-fetched array
        $isView = ($info['TABLE_TYPE'] === 'VIEW');
        $rowCount = $info['TABLE_ROWS'];
        $tbl = $tableName; // for consistency with the rest of the code
        // MODIFICATION END
        ?>
        <tr class="table-list-row" data-table-name="<?= strtolower(htmlspecialchars($tbl)) ?>">
            <td style="max-width: 200px !important; overflow: hidden;">
                <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=browse&table=<?= urlencode($tbl) ?>" class="btn btn-sm" title="Browse">
                    <strong><?= htmlspecialchars($tbl) ?></strong>
                </a>
            </td>
            <td>
                <?php if ($isView): ?>
                    <span class="badge badge-blue">View</span>
                <?php else: ?>
                    <span class="badge badge-gray">Table</span>
                <?php endif; ?>
            </td>
            <td><?= is_null($rowCount) ? '~0' : number_format($rowCount) ?></td>
            
                                        <td>
                                            <div class="flex-gap" style="gap: 0.25rem;">
                                                <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=browse&table=<?= urlencode($tbl) ?>" style="display: none;"
                                                   class="btn btn-sm" 
                                                   title="Browse">🔍</a>
                                                   
                                                   
                                                   
                                                <?php if ($isView): ?>
                                                    <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=view_structure&table=<?= urlencode($tbl) ?>" 
                                                       class="btn btn-sm" 
                                                       title="View Structure">🏗️</a>
                                                <?php else: ?>
                                                    <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=structure&table=<?= urlencode($tbl) ?>" 
                                                       class="btn btn-sm" 
                                                       title="Structure">🏗️</a>
                                                    <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=insert&table=<?= urlencode($tbl) ?>" 
                                                       class="btn btn-sm" 
                                                       title="Insert">⧾</a>
                                                <?php endif; ?>
                                                   
                                                   
                                                   
                                                
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div id="noResults" style="display: none; margin-top: 1rem;">
                        <div class="empty-state">
                            <p>No tables found matching your search.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <script>
            // Table search functionality
            (function() {
                const searchInput = document.getElementById('tableSearch');
                const tableRows = document.querySelectorAll('.table-list-row');
                const visibleCount = document.getElementById('visibleCount');
                const noResults = document.getElementById('noResults');
                const tablesList = document.getElementById('tablesListTable');
                
                if (!searchInput || !tableRows.length) return;
                
                searchInput.addEventListener('input', function() {
                    const query = this.value.toLowerCase().trim();
                    let visible = 0;
                    
                    tableRows.forEach(row => {
                        const tableName = row.dataset.tableName;
                        if (tableName.includes(query)) {
                            row.style.display = '';
                            visible++;
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    visibleCount.textContent = visible;
                    
                    if (visible === 0) {
                        tablesList.style.display = 'none';
                        noResults.style.display = 'block';
                    } else {
                        tablesList.style.display = 'table';
                        noResults.style.display = 'none';
                    }
                });
                
                // Focus search on / key
                document.addEventListener('keydown', function(e) {
                    if (e.key === '/' && !e.target.matches('input, textarea')) {
                        e.preventDefault();
                        searchInput.focus();
                    }
                });
                
                // Clear on Escape
                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        this.value = '';
                        this.dispatchEvent(new Event('input'));
                        this.blur();
                    }
                });
            })();
            </script>
            
            
            
            
            
            
            
<?php elseif ($action === 'manage_databases'): ?>
            <!-- MANAGE DATABASES VIEW -->
            <?php
            global $pdoRoot;
            $databases = DbTool::listAllDatabases($pdoRoot);
            ?>
            
            <div class="section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 class="section-header" style="margin: 0;">Manage Databases</h2>
                    <div class="flex-gap">
                        <button type="button" class="btn btn-sm btn-primary" onclick="document.getElementById('createDbForm').style.display = document.getElementById('createDbForm').style.display === 'none' ? 'block' : 'none'">
                            + Create Database
                        </button>
                        <a href="?db=<?= $_GET['db'] ?? 'main' ?>" class="btn btn-sm btn-secondary">← Back to Home</a>
                    </div>
                </div>
                
                <!-- Create Database Form -->
                <div id="createDbForm" class="card" style="display: none; margin-bottom: 2rem;">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_database">
                        <div class="card-body">
                            <h3 style="margin-bottom: 1rem;">Create New Database</h3>
                            
                            <div class="form-group">
                                <label for="db_name" class="form-label">Database Name *</label>
                                <input type="text" 
                                       id="db_name" 
                                       name="db_name" 
                                       class="form-control" 
                                       placeholder="my_new_database"
                                       pattern="[a-zA-Z0-9_]+"
                                       title="Only letters, numbers, and underscores allowed"
                                       required>
                                <small style="color: rgba(var(--text-rgb), 0.6); font-size: 0.85rem; margin-top: 0.25rem; display: block;">
                                    Only letters, numbers, and underscores allowed
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label for="db_charset" class="form-label">Character Set</label>
                                <select id="db_charset" name="db_charset" class="form-control">
                                    <option value="utf8mb4" selected>utf8mb4 (recommended)</option>
                                    <option value="utf8">utf8</option>
                                    <option value="latin1">latin1</option>
                                    <option value="ascii">ascii</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="db_collation" class="form-label">Collation</label>
                                <select id="db_collation" name="db_collation" class="form-control">
                                    <option value="utf8mb4_general_ci" selected>utf8mb4_general_ci</option>
                                    <option value="utf8mb4_unicode_ci">utf8mb4_unicode_ci</option>
                                    <option value="utf8_general_ci">utf8_general_ci</option>
                                    <option value="latin1_swedish_ci">latin1_swedish_ci</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-footer flex-gap">
                            <button type="submit" class="btn btn-primary">Create Database</button>
                            <button type="button" class="btn btn-secondary" onclick="document.getElementById('createDbForm').style.display = 'none'">Cancel</button>
                        </div>
                    </form>
                </div>
                
                <!-- Database List -->
                <?php if (empty($databases)): ?>
                    <div class="empty-state">
                        <p>No databases found or insufficient permissions to list databases.</p>
                    </div>
                <?php else: ?>
                    <div style="margin-bottom: 1rem; color: rgba(var(--text-rgb), 0.7); font-size: 0.9rem;">
                        <strong><?= count($databases) ?></strong> database(s) found
                    </div>
                    
                    <div class="data-table-wrapper">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th style="width: 30%;">Database Name</th>
                                    <th style="width: 15%;">Charset</th>
                                    <th style="width: 20%;">Collation</th>
                                    <th style="width: 15%;">Size</th>
                                    <th style="width: 20%;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($databases as $db): ?>
                                    <?php 
                                    $info = DbTool::getDatabaseInfo($pdoRoot, $db);
                                    $size = DbTool::getDatabaseSize($pdoRoot, $db);
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($db) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($info['charset'] ?? 'N/A') ?></td>
                                        <td><?= htmlspecialchars($info['collation'] ?? 'N/A') ?></td>
                                        <td><?= DbTool::formatBytes($size) ?></td>
                                        <td>
                                            <div class="flex-gap" style="gap: 0.25rem;">
                                                <!--
                                                <a href="?db=main&action=browse_external_db&external_db=<?= urlencode($db) ?>" 
                                                   class="btn btn-sm btn-primary" 
                                                   title="Browse">Browse</a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ WARNING: Delete database \'<?= htmlspecialchars($db) ?>\'?\n\nThis will permanently delete ALL tables, views, and data!\n\nThis action CANNOT be undone!');">
                                                    <input type="hidden" name="action" value="drop_database">
                                                    <input type="hidden" name="db_name" value="<?= htmlspecialchars($db) ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" title="Drop Database">Drop</button>
                                                </form>
                                                -->
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            
            
            
            
            
            
<?php elseif ($action === 'multi_export'): ?>
            <!-- MULTI-TABLE EXPORT VIEW -->
            <?php
            // START MODIFICATION: Get all table info in one efficient query
            $tablesInfo = DbTool::getTablesWithInfo($currentPdo, $currentDbName);
            // END MODIFICATION
            ?>

            
            <div class="section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 class="section-header" style="margin: 0;">Multi-Table Export</h2>
                    <a href="?db=<?= $_GET['db'] ?? 'main' ?>" class="btn btn-sm">← Back to Tables</a>
                </div>
                
                <div class="notification notification-info" style="margin-bottom: 1.5rem;">
                    <strong>Export Options:</strong> Select which tables to export and whether to include structure (CREATE TABLE) and/or data (INSERT statements).
                </div>
                
                <?php if (empty($tablesInfo)): ?>

                    <div class="empty-state">
                        <p>No tables found in this database.</p>
                    </div>
                <?php else: ?>
                    <form method="POST" action="?db=<?= $_GET['db'] ?? 'main' ?>&action=execute_multi_export" id="multiExportForm">
                        <!-- Search Input -->
                        <div class="form-group" style="margin-bottom: 1rem;">
                            <input type="text" 
                                   id="tableSearchExport" 
                                   class="form-control" 
                                   placeholder="Search tables..."
                                   style="max-width: 400px;">
                        </div>
                        
                        <div style="margin-bottom: 1rem; display: none;">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="selectAllStructure()">✓ All Structure</button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="selectAllData()">✓ All Data</button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="selectAllBoth()">✓ All Both</button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="deselectAll()">✕ Deselect All</button>
                        </div>
                        
                        <div class="data-table-wrapper">
                            <table class="data-table" id="exportTablesTable">
                                <thead>
                                    <tr>
                                        <th style="width: 20%;">Name</th>
                                        <th style="width: 10%;">Type</th>
                                        <th style="width: 10%;">Rows</th>
                                        <th style="width: 20%; text-align: center;">
                                            <input type="checkbox" id="selectAllStructureCheck" onchange="toggleAllStructure(this)" style="margin-right: 4px;">
                                            🏗️
                                        </th>
                                        <th style="width: 20%; text-align: center;">
                                            <input type="checkbox" id="selectAllDataCheck" onchange="toggleAllData(this)" style="margin-right: 4px;">
                                            📜
                                        </th>
<th style="width: 20%;"> </th>
                                    </tr>
                                </thead>
                                
                                
                                
                                
                                
                                
                                                              <tbody>
                                    <?php foreach ($tablesInfo as $tableName => $info): ?>
                                        <?php 
                                        // START MODIFICATION: Use the pre-fetched info
                                        $isView = ($info['TABLE_TYPE'] === 'VIEW');
                                        $rowCount = $info['TABLE_ROWS'];
                                        $tbl = $tableName; // Use this variable for consistency
                                        // END MODIFICATION
                                        ?>
                                        <tr class="export-table-row" data-table-name="<?= strtolower(htmlspecialchars($tbl)) ?>">
                                            <td>
                                                <strong><?= htmlspecialchars($tbl) ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($isView): ?>
                                                    <span class="badge badge-blue">View</span>
                                                <?php else: ?>
                                                    <span class="badge badge-gray">Table</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= is_null($rowCount) ? 'N/A' : number_format($rowCount) ?></td>
                                            

                                            
                                            
                                            
                                            
                                            
                                            
                                            
                                            <td style="text-align: center;">
                                                <input type="checkbox" 
                                                       name="structure[]" 
                                                       value="<?= htmlspecialchars($tbl) ?>"
                                                       class="form-check-input structure-check">
                                            </td>
                                            <td style="text-align: center;">
                                                <?php if (!$isView): ?>
                                                    <input type="checkbox" 
                                                           name="data[]" 
                                                           value="<?= htmlspecialchars($tbl) ?>"
                                                           class="form-check-input data-check">
                                                <?php else: ?>
                                                    <span style="color: rgba(var(--text-rgb), 0.3);">—</span>
                                                <?php endif; ?>
                                            </td>


<td> </td>

                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                
                                
                                
                                
                                
                                
                                
                                
                                
                            </table>
                        </div>
                        
                        <div id="noResultsExport" style="display: none; margin-top: 1rem;">
                            <div class="empty-state">
                                <p>No tables found matching your search.</p>
                            </div>
                        </div>
                        
                        <div style="margin-top: 1.5rem; display: flex; gap: 1rem; align-items: center;">
                            <button type="submit" class="btn btn-primary">Export Selected</button>
                            <span style="color: rgba(var(--text-rgb), 0.7); font-size: 0.9rem;">
                                <span id="selectedStructureCount">0</span> structure(s), 
                                <span id="selectedDataCount">0</span> data set(s) selected
                            </span>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <script>
            // Search functionality
            (function() {
                const searchInput = document.getElementById('tableSearchExport');
                const tableRows = document.querySelectorAll('.export-table-row');
                const noResults = document.getElementById('noResultsExport');
                const tablesTable = document.getElementById('exportTablesTable');
                
                if (!searchInput || !tableRows.length) return;
                
                searchInput.addEventListener('input', function() {
                    const query = this.value.toLowerCase().trim();
                    let visible = 0;
                    
                    tableRows.forEach(row => {
                        const tableName = row.dataset.tableName;
                        if (tableName.includes(query)) {
                            row.style.display = '';
                            visible++;
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    if (visible === 0) {
                        tablesTable.style.display = 'none';
                        noResults.style.display = 'block';
                    } else {
                        tablesTable.style.display = 'table';
                        noResults.style.display = 'none';
                    }
                });
            })();
            
            // Selection functions
            function selectAllStructure() {
                document.querySelectorAll('.structure-check').forEach(cb => cb.checked = true);
                updateCounts();
            }
            
            
            
            function selectAllData() {
                // Only select data checkboxes that exist (tables only, not views)
                document.querySelectorAll('.data-check').forEach(cb => cb.checked = true);
                updateCounts();
            }
            
            function selectAllBoth() {
                document.querySelectorAll('.structure-check').forEach(cb => cb.checked = true);
                // Only select data checkboxes that exist (tables only, not views)
                document.querySelectorAll('.data-check').forEach(cb => cb.checked = true);
                updateCounts();
            }
            
            
            function deselectAll() {
                document.querySelectorAll('.structure-check, .data-check').forEach(cb => cb.checked = false);
                document.getElementById('selectAllStructureCheck').checked = false;
                document.getElementById('selectAllDataCheck').checked = false;
                updateCounts();
            }
            
            function toggleAllStructure(checkbox) {
                document.querySelectorAll('.structure-check').forEach(cb => cb.checked = checkbox.checked);
                updateCounts();
            }
            
            function toggleAllData(checkbox) {
                document.querySelectorAll('.data-check').forEach(cb => cb.checked = checkbox.checked);
                updateCounts();
            }
            
            function updateCounts() {
                const structureCount = document.querySelectorAll('.structure-check:checked').length;
                const dataCount = document.querySelectorAll('.data-check:checked').length;
                document.getElementById('selectedStructureCount').textContent = structureCount;
                document.getElementById('selectedDataCount').textContent = dataCount;
            }
            
            // Update counts on checkbox change
            document.querySelectorAll('.structure-check, .data-check').forEach(cb => {
                cb.addEventListener('change', updateCounts);
            });
            
            // Form validation
            document.getElementById('multiExportForm').addEventListener('submit', function(e) {
                const structureCount = document.querySelectorAll('.structure-check:checked').length;
                const dataCount = document.querySelectorAll('.data-check:checked').length;
                
                if (structureCount === 0 && dataCount === 0) {
                    e.preventDefault();
                    alert('Please select at least one table structure or data to export.');
                }
            });
            </script>
            
            
            
            
            

<?php elseif ($action === 'sql'): ?>
            <!-- SQL QUERY VIEW -->
            <div class="section">
                <h2 class="section-header">Execute SQL Query</h2>
                <div class="card">
                    <form method="POST">
                        <input type="hidden" name="action" value="execute_sql">
                        <input type="hidden" name="preserve_db" value="<?= htmlspecialchars($_GET['db'] ?? 'main') ?>">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="sql" class="form-label">SQL Query</label>
                                <textarea id="sql" name="sql" class="sql-editor" placeholder="SELECT * FROM table_name LIMIT 10"><?= htmlspecialchars($_POST['sql'] ?? '') ?></textarea>
                            </div>
                        </div>
                        <div class="card-footer flex-gap">
                            <button type="submit" class="btn btn-primary">Execute Query</button>
                            <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=sql" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>

                <?php if (isset($queryResults)): ?>
                    <?php
                    // Pagination for results
                    $resultsPerPage = RECORDS_PER_PAGE;
                    $resultPage = max(1, intval($_POST['result_page'] ?? $_GET['result_page'] ?? 1));
                    $totalResults = count($queryResults);
                    $totalResultPages = ceil($totalResults / $resultsPerPage);
                    $resultOffset = ($resultPage - 1) * $resultsPerPage;
                    $paginatedResults = array_slice($queryResults, $resultOffset, $resultsPerPage);
                    ?>
                    
                
                    
                    <div class="section" style="margin-top: 2rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                            <h3 class="section-header" style="margin: 0;">Query Results</h3>
                            <div class="flex-gap">
                                <button class="btn btn-sm" onclick="exportQueryResults('sql')">Export as SQL</button>
                                <button class="btn btn-sm" onclick="exportQueryResults('csv')">Export as CSV</button>
                            </div>
                        </div>
                        
                        <div style="margin-bottom: 1rem; color: rgba(var(--text-rgb), 0.7); font-size: 0.9rem;">
                            Showing <?= number_format($resultOffset + 1) ?> - <?= number_format(min($resultOffset + $resultsPerPage, $totalResults)) ?> of <?= number_format($totalResults) ?> rows
                        </div>
                        
                        <?php if (empty($queryResults)): ?>
                            <div class="notification notification-info">
                                Query executed successfully. No rows returned.
                            </div>
                        <?php else: ?>
                            <div class="data-table-wrapper">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <?php foreach (array_keys($queryResults[0]) as $col): ?>
                                                <th><?= htmlspecialchars($col) ?></th>
                                            <?php endforeach; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($paginatedResults as $row): ?>
                                            <tr>
                                                <?php foreach ($row as $val): ?>
                                                    <td title="<?= htmlspecialchars($val ?? 'NULL') ?>">
                                                        <?php if ($val === null): ?>
                                                            <span class="null-value">NULL</span>
                                                        <?php else: ?>
                                                            <?= htmlspecialchars(strlen($val) > 100 ? substr($val, 0, 100) . '...' : $val) ?>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            
                            
                            
                            
                            
                           <?php if ($totalResultPages > 1): ?>
                                <div class="pagination">
                                    <?php if ($resultPage > 1): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="execute_sql">
                                            <input type="hidden" name="sql" value="<?= htmlspecialchars($_POST['sql']) ?>">
                                            <input type="hidden" name="result_page" value="<?= $resultPage - 1 ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary">Previous</button>
                                        </form>
                                    <?php endif; ?>
                                    
                                    <?php
                                    $startPage = max(1, $resultPage - 2);
                                    $endPage = min($totalResultPages, $resultPage + 2);
                                    
                                    for ($i = $startPage; $i <= $endPage; $i++):
                                    ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="execute_sql">
                                            <input type="hidden" name="sql" value="<?= htmlspecialchars($_POST['sql']) ?>">
                                            <input type="hidden" name="result_page" value="<?= $i ?>">
                                            <button type="submit" class="btn btn-sm <?= $i === $resultPage ? 'btn-primary' : 'btn-secondary' ?>">
                                                <?= $i ?>
                                            </button>
                                        </form>
                                    <?php endfor; ?>
                                    
                                    <?php if ($resultPage < $totalResultPages): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="execute_sql">
                                            <input type="hidden" name="sql" value="<?= htmlspecialchars($_POST['sql']) ?>">
                                            <input type="hidden" name="result_page" value="<?= $resultPage + 1 ?>">
                                            <button type="submit" class="btn btn-sm btn-secondary">Next</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            
                            
                            
                        <?php endif; ?>
                    </div>
                    
                    
                    
                    
                   <!-- Hidden data for export -->
                    <script>
                    window.queryResultsData = <?= json_encode($queryResults) ?>;
                    window.querySQL = <?= json_encode($_POST['sql'] ?? '') ?>;
                    
                    function exportQueryResults(format) {
                        const data = window.queryResultsData;
                        if (!data || data.length === 0) {
                            alert('No data to export.');
                            return;
                        }
                        
                        // Try to extract table name from SQL query
                        let tableName = 'query_results';
                        const sql = window.querySQL;
                        if (sql) {
                            // Match FROM table_name or FROM `table_name`
                            const match = sql.match(/FROM\s+`?(\w+)`?/i);
                            if (match) {
                                tableName = match[1];
                            }
                        }
                        
                        let content = '';
                        let filename = tableName + '_export_' + Date.now();
                        let mimeType = 'text/plain';
                        
                        if (format === 'csv') {
                            // Generate CSV
                            const headers = Object.keys(data[0]);
                            content = headers.map(h => '"' + h.replace(/"/g, '""') + '"').join(',') + '\n';
                            
                            data.forEach(row => {
                                const values = headers.map(h => {
                                    const val = row[h];
                                    if (val === null) return '""';
                                    return '"' + String(val).replace(/"/g, '""') + '"';
                                });
                                content += values.join(',') + '\n';
                            });
                            
                            filename += '.csv';
                            mimeType = 'text/csv';
                        } else {
                            // Generate SQL INSERT statements
                            content = `-- Query Results Export\n`;
                            content += `-- Table: ${tableName}\n`;
                            content += `-- Generated: ${new Date().toISOString()}\n`;
                            content += `-- Total rows: ${data.length}\n\n`;
                            
                            data.forEach(row => {
                                const columns = Object.keys(row);
                                const values = Object.values(row).map(v => {
                                    if (v === null) return 'NULL';
                                    return "'" + String(v).replace(/'/g, "''") + "'";
                                });
                                
                                content += `INSERT INTO \`${tableName}\` (\`${columns.join('`, `')}\`) VALUES (${values.join(', ')});\n`;
                            });
                            
                            filename += '.sql';
                        }
                        
                        // Download
                        const blob = new Blob([content], { type: mimeType });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                        
                        if (window.showToast) {
                            window.showToast(`Exported ${data.length} rows as ${format.toUpperCase()}!`, 'success');
                        }
                    }
                    </script>
                    
                    
                    
                    
                    
                    
                    
                    
                <?php endif; ?>
            </div>

            
<?php elseif ($action === 'import'): ?>
            <!-- SQL IMPORT VIEW -->
            <?php
            $uploadMaxSize = ini_get('upload_max_filesize');
            $postMaxSize = ini_get('post_max_size');
            $memoryLimit = ini_get('memory_limit');
            $maxExecutionTime = ini_get('max_execution_time');
            ?>
            
            <div class="section">
                <h2 class="section-header">Import SQL File</h2>
                
                <div class="notification notification-info" style="margin-bottom: 1.5rem;">
                    <strong>Current PHP Limits:</strong><br>
                    Upload max filesize: <strong><?= $uploadMaxSize ?></strong> | 
                    POST max size: <strong><?= $postMaxSize ?></strong> | 
                    Memory limit: <strong><?= $memoryLimit ?></strong> | 
                    Max execution time: <strong><?= $maxExecutionTime ?>s</strong>
                    <br><br>
                    For larger files, adjust these settings in your php.ini file.
                </div>
                
                <div class="card">
                    <form method="POST" action="?db=<?= $_GET['db'] ?? 'main' ?>&action=execute_import" enctype="multipart/form-data" id="importForm">
                        <div class="card-body">
                            <div class="form-group">
                                <label for="sql_file" class="form-label">Select SQL File</label>
                                <input type="file" 
                                       id="sql_file" 
                                       name="sql_file" 
                                       class="form-control" 
                                       accept=".sql,.txt"
                                       required>
                                <small style="color: rgba(var(--text-rgb), 0.6); font-size: 0.85rem; margin-top: 0.25rem; display: block;">
                                    Accepts .sql or .txt files
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <div class="form-check">
                                    <input type="checkbox" id="ignore_errors" name="ignore_errors" class="form-check-input" value="1">
                                    <label for="ignore_errors">Continue on errors (ignore failed statements)</label>
                                </div>
                            </div>
                        </div>
                        <div class="card-footer flex-gap">
                            <button type="submit" class="btn btn-primary" id="importBtn">Import SQL File</button>
                            <a href="?db=<?= $_GET['db'] ?? 'main' ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
                
                <div id="importProgress" style="display: none; margin-top: 1.5rem;">
                    <div class="card">
                        <div class="card-body">
                            <h3 style="margin-bottom: 1rem;">Importing...</h3>
                            <div style="background: rgba(var(--surface-rgb), 1); border-radius: 8px; height: 30px; overflow: hidden; position: relative;">
                                <div id="progressBar" style="background: rgba(var(--accent-rgb), 1); height: 100%; width: 0%; transition: width 0.3s;"></div>
                            </div>
                            <div id="progressText" style="margin-top: 0.5rem; color: rgba(var(--text-rgb), 0.7); font-size: 0.9rem;">
                                Uploading file...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <script>
            document.getElementById('importForm').addEventListener('submit', function(e) {
                const fileInput = document.getElementById('sql_file');
                const file = fileInput.files[0];
                
                if (file && file.size > 100 * 1024 * 1024) { // 100MB
                    if (!confirm('This file is quite large (' + (file.size / 1024 / 1024).toFixed(2) + ' MB). Import may take several minutes. Continue?')) {
                        e.preventDefault();
                        return;
                    }
                }
                
                // Show progress
                document.getElementById('importProgress').style.display = 'block';
                document.getElementById('importBtn').disabled = true;
                document.getElementById('importBtn').textContent = 'Importing...';
                
                // Simulate progress (real progress tracking would require AJAX)
                let progress = 0;
                const interval = setInterval(function() {
                    progress += Math.random() * 15;
                    if (progress > 90) progress = 90;
                    document.getElementById('progressBar').style.width = progress + '%';
                    document.getElementById('progressText').textContent = 'Processing... ' + Math.round(progress) + '%';
                }, 500);
                
                // Store interval ID to clear on page load
                window.importInterval = interval;
            });
            </script>
            
            
<?php elseif ($action === 'browse' && $table): ?>
            <!-- BROWSE TABLE VIEW WITH SORTING AND CHECKBOXES -->
            <?php
            $page = max(1, intval($_GET['page'] ?? 1));
            $offset = ($page - 1) * RECORDS_PER_PAGE;
            
            // Get sort parameters
            $sortColumn = $_GET['sort'] ?? null;
            $sortDirection = $_GET['dir'] ?? 'asc';
            
            // Validate sort direction
            $sortDirection = in_array($sortDirection, ['asc', 'desc']) ? $sortDirection : 'asc';
            
            // Get total count
            $countStmt = $currentPdo->query("SELECT COUNT(*) FROM `" . DbTool::escapeIdentifier($table) . "`");
            $totalRows = $countStmt->fetchColumn();
            $totalPages = ceil($totalRows / RECORDS_PER_PAGE);
            
            // Build query with sorting
            $sql = "SELECT * FROM `" . DbTool::escapeIdentifier($table) . "`";
            if ($sortColumn) {
                $sql .= " ORDER BY `" . DbTool::escapeIdentifier($sortColumn) . "` " . strtoupper($sortDirection);
            }
            $sql .= " LIMIT " . RECORDS_PER_PAGE . " OFFSET " . $offset;
            
            $stmt = $currentPdo->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get columns info for identifying primary key
            $columns = DbTool::getTableInfo($currentPdo, $table);
            $primaryKey = null;
            foreach ($columns as $col) {
                if ($col['Key'] === 'PRI') {
                    $primaryKey = $col['Field'];
                    break;
                }
            }
            if (!$primaryKey && !empty($columns)) {
                $primaryKey = $columns[0]['Field'];
            }
            
            // Helper function to generate sort URL
            function getSortUrl($column, $currentSort, $currentDir, $table, $db) {
                $newDir = 'asc';
                $newSort = $column;
                
                if ($currentSort === $column) {
                    if ($currentDir === 'asc') {
                        $newDir = 'desc';
                    } elseif ($currentDir === 'desc') {
                        // Third click - remove sort
                        return "?db=" . urlencode($db) . "&action=browse&table=" . urlencode($table);
                    }
                }
                
                return "?db=" . urlencode($db) . "&action=browse&table=" . urlencode($table) . "&sort=" . urlencode($newSort) . "&dir=" . urlencode($newDir);
            }
            
            // Helper function to get sort icon
            function getSortIcon($column, $currentSort, $currentDir) {
                if ($currentSort !== $column) {
                    return '<span style="opacity: 0.3; margin-left: 4px;">⇅</span>';
                }
                return $currentDir === 'asc' 
                    ? '<span style="margin-left: 4px;">↑</span>' 
                    : '<span style="margin-left: 4px;">↓</span>';
            }
            ?>
            
            <div class="section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 class="section-header" style="margin: 0;">Browse: <?= htmlspecialchars($table) ?></h2>
<div style="width:100%; display: block;"> </div>
                    <div class="flex-gap">
                        <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=structure&table=<?= urlencode($table) ?>" class="btn btn-sm">Structure</a>
                        <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=insert&table=<?= urlencode($table) ?>" class="btn btn-sm">Insert Row</a>
                        <button id="deleteSelectedBtn" class="btn btn-sm" style="display: none;" onclick="deleteSelected()">Delete Selected</button>
                        <button id="exportSelectedBtn" class="btn btn-sm" style="display: none;" onclick="exportSelected()">Export Selected</button>
                        <div style="position: relative; display: inline-block;">
                            <button class="btn btn-sm" onclick="document.getElementById('exportMenu').style.display = document.getElementById('exportMenu').style.display === 'block' ? 'none' : 'block'">Export All</button>
                            <div id="exportMenu" style="display: none; position: absolute; top: 100%; right: 0; margin-top: 0.25rem; background: rgba(var(--card-rgb), 1); border: 1px solid rgba(var(--muted-border-rgb), 0.12); border-radius: 6px; padding: 0.5rem; z-index: 100; min-width: 120px;">
                                <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=export&table=<?= urlencode($table) ?>&format=sql" class="btn btn-sm" style="display: block;background:#111; margin-bottom: 0.25rem; width: 100%;">SQL</a>
                                <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=export&table=<?= urlencode($table) ?>&format=csv" class="btn btn-sm" style="display: block; background:#111; width: 100%;">CSV</a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div style="margin-bottom: 1rem; color: rgba(var(--text-rgb), 0.7); font-size: 0.9rem;">
                    Showing <?= number_format($offset + 1) ?> - <?= number_format(min($offset + RECORDS_PER_PAGE, $totalRows)) ?> of <?= number_format($totalRows) ?> rows
                    <?php if ($sortColumn): ?>
                        <span style="margin-left: 1rem;">
                            Sorted by <strong><?= htmlspecialchars($sortColumn) ?></strong> (<?= $sortDirection === 'asc' ? 'ascending' : 'descending' ?>)
                            <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=browse&table=<?= urlencode($table) ?>" style="margin-left: 0.5rem; color: rgba(var(--accent-rgb), 1); text-decoration: none;">✕ Clear</a>
                        </span>
                    <?php endif; ?>
                    <span id="selectedCount" style="margin-left: 1rem; display: none;">
                        <strong><span id="selectedCountNum">0</span></strong> rows selected
                    </span>
                </div>

                <?php if (empty($rows)): ?>
                    <div class="empty-state">
                        <p>No data in this table.</p>
                        <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=insert&table=<?= urlencode($table) ?>" class="btn btn-primary">Insert First Row</a>
                    </div>
                <?php else: ?>
                    <div class="data-table-wrapper">
                        <table class="data-table" id="dataTable">
                            <thead>
                                <tr>
                                    <th style="width: 120px;">Actions</th>
                                    <th style="width: 50px;">
                                        <input type="checkbox" id="selectAll" class="form-check-input" style="margin: 0;" title="Select all rows">
                                    </th>
                                    <?php foreach (array_keys($rows[0]) as $col): ?>
                                        <th>
                                            <a href="<?= getSortUrl($col, $sortColumn, $sortDirection, $table, $_GET['db'] ?? 'main') ?>" 
                                               style="text-decoration: none; color: inherit; display: block; cursor: pointer;"
                                               title="Click to sort by <?= htmlspecialchars($col) ?>">
                                                <?= htmlspecialchars($col) ?>
                                                <?= getSortIcon($col, $sortColumn, $sortDirection) ?>
                                            </a>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td>
                                            <div class="flex-gap" style="gap: 0.25rem;">
                                                <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=edit&table=<?= urlencode($table) ?>&id=<?= urlencode($row[$primaryKey]) ?>" 
                                                   class="btn btn-sm" 
                                                   title="Edit">✎</a>
                                                <button type="button" 
                                                        class="btn btn-sm btn-danger delete-single-btn" 
                                                        data-id="<?= htmlspecialchars($row[$primaryKey]) ?>"
                                                        title="Delete">✕</button>
                                            </div>
                                        </td>
                                        <td>
                                            <input type="checkbox" class="row-checkbox form-check-input" 
                                                   data-id="<?= htmlspecialchars($row[$primaryKey]) ?>" 
                                                   data-row='<?= htmlspecialchars(json_encode($row), ENT_QUOTES) ?>'
                                                   style="margin: 0;">
                                        </td>
                                        <?php foreach ($row as $val): ?>
                                            <td title="<?= htmlspecialchars($val ?? 'NULL') ?>">
                                                <?php if ($val === null): ?>
                                                    <span class="null-value">NULL</span>
                                                <?php else: ?>
                                                    <?= htmlspecialchars(strlen($val) > 100 ? substr($val, 0, 100) . '...' : $val) ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Hidden form for deletions -->
                    <form id="deleteForm" method="POST" style="display: none;">
                        <input type="hidden" name="action" value="delete_rows">
                        <input type="hidden" name="id_column" value="<?= htmlspecialchars($primaryKey) ?>">
                        <input type="hidden" name="ids" id="deleteIds" value="">
                    </form>

                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <?php
                            // Build pagination URL with sort parameters
                            $paginationBase = "?db=" . urlencode($_GET['db'] ?? 'main') . "&action=browse&table=" . urlencode($table);
                            if ($sortColumn) {
                                $paginationBase .= "&sort=" . urlencode($sortColumn) . "&dir=" . urlencode($sortDirection);
                            }
                            ?>
                            
                            <?php if ($page > 1): ?>
                                <a href="<?= $paginationBase ?>&page=<?= $page - 1 ?>" class="btn btn-sm btn-secondary">Previous</a>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <a href="<?= $paginationBase ?>&page=<?= $i ?>" 
                                   class="btn btn-sm <?= $i === $page ? 'btn-primary' : 'btn-secondary' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                                <a href="<?= $paginationBase ?>&page=<?= $page + 1 ?>" class="btn btn-sm btn-secondary">Next</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <script>
            // Checkbox selection logic
            (function() {
                const selectAll = document.getElementById('selectAll');
                const checkboxes = document.querySelectorAll('.row-checkbox');
                const exportBtn = document.getElementById('exportSelectedBtn');
                const deleteBtn = document.getElementById('deleteSelectedBtn');
                const selectedCount = document.getElementById('selectedCount');
                const selectedCountNum = document.getElementById('selectedCountNum');
                
                if (!selectAll || !checkboxes.length) return;
                
                function updateUI() {
                    const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
                    selectedCountNum.textContent = checkedCount;
                    
                    if (checkedCount > 0) {
                        exportBtn.style.display = 'inline-block';
                        deleteBtn.style.display = 'inline-block';
                        selectedCount.style.display = 'inline';
                    } else {
                        exportBtn.style.display = 'none';
                        deleteBtn.style.display = 'none';
                        selectedCount.style.display = 'none';
                    }
                    
                    selectAll.checked = checkedCount === checkboxes.length;
                    selectAll.indeterminate = checkedCount > 0 && checkedCount < checkboxes.length;
                }
                
                selectAll.addEventListener('change', function() {
                    checkboxes.forEach(cb => cb.checked = this.checked);
                    updateUI();
                });
                
                checkboxes.forEach(cb => {
                    cb.addEventListener('change', updateUI);
                });
                
                // Single row delete
                document.querySelectorAll('.delete-single-btn').forEach(btn => {
                    btn.addEventListener('click', function() {
                        if (confirm('Delete this row? This action cannot be undone.')) {
                            const id = this.dataset.id;
                            document.getElementById('deleteIds').value = JSON.stringify([id]);
                            document.getElementById('deleteForm').submit();
                        }
                    });
                });
                
                // Bulk delete
                window.deleteSelected = function() {
                    const checked = document.querySelectorAll('.row-checkbox:checked');
                    if (checked.length === 0) {
                        alert('Please select at least one row to delete.');
                        return;
                    }
                    
                    const count = checked.length;
                    if (confirm(`Delete ${count} row${count > 1 ? 's' : ''}? This action cannot be undone.`)) {
                        const ids = Array.from(checked).map(cb => cb.dataset.id);
                        document.getElementById('deleteIds').value = JSON.stringify(ids);
                        document.getElementById('deleteForm').submit();
                    }
                };
                
                // Export selected
                window.exportSelected = function() {
                    const checked = document.querySelectorAll('.row-checkbox:checked');
                    if (checked.length === 0) {
                        alert('Please select at least one row to export.');
                        return;
                    }
                    
                    const rows = Array.from(checked).map(cb => JSON.parse(cb.dataset.row));
                    const tableName = '<?= addslashes($table) ?>';
                    
                    // Generate SQL INSERT statements
                    let sql = `-- Export of ${checked.length} selected rows from table: ${tableName}\n\n`;
                    
                    rows.forEach(row => {
                        const columns = Object.keys(row);
                        const values = Object.values(row).map(v => {
                            if (v === null) return 'NULL';
                            // Escape single quotes
                            return "'" + String(v).replace(/'/g, "''") + "'";
                        });
                        
                        sql += `INSERT INTO \`${tableName}\` (\`${columns.join('`, `')}\`) VALUES (${values.join(', ')});\n`;
                    });
                    
                    // Download as file
                    const blob = new Blob([sql], { type: 'text/plain' });
                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `${tableName}_selected_${checked.length}_rows.sql`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);
                    
                    if (window.showToast) {
                        window.showToast(`Exported ${checked.length} rows successfully!`, 'success');
                    }
                };
            })();
            </script>
            
            
            
<?php elseif ($action === 'structure' && $table): ?>
            <!-- STRUCTURE VIEW -->
            <?php
            $columns = DbTool::getTableInfo($currentPdo, $table);
            $indexes = DbTool::getTableIndexes($currentPdo, $table);
            ?>
            
            <div class="section">
            
                
               <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 class="section-header" style="margin: 0;">Structure: <?= htmlspecialchars($table) ?></h2>
<div style="display: block; width: 100%;"> </div>
                    <div class="flex-gap">
                        <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=alter_structure&table=<?= urlencode($table) ?>" class="btn btn-sm">✎ Edit Structure</a>
                        <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=browse&table=<?= urlencode($table) ?>" class="btn btn-sm">Browse Data</a>
                        <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=insert&table=<?= urlencode($table) ?>" class="btn btn-sm">Insert Row</a>
                    </div>
                </div>

                <h3 style="margin-top: 2rem; margin-bottom: 1rem;">Columns</h3>
                <div class="data-table-wrapper">
                    <table class="structure-table">
                        <thead>
                            <tr>
                                <th>Field</th>
                                <th>Type</th>
                                <th>Null</th>
                                <th>Key</th>
                                <th>Default</th>
                                <th>Extra</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($columns as $col): ?>
                                <tr>
                                    <td><span class="code-inline"><?= htmlspecialchars($col['Field']) ?></span></td>
                                    <td><span class="code-inline"><?= htmlspecialchars($col['Type']) ?></span></td>
                                    <td><?= $col['Null'] === 'YES' ? 'Yes' : 'No' ?></td>
                                    <td>
                                        <?php if ($col['Key'] === 'PRI'): ?>
                                            <span class="badge badge-blue">PRIMARY</span>
                                        <?php elseif ($col['Key'] === 'UNI'): ?>
                                            <span class="badge badge-green">UNIQUE</span>
                                        <?php elseif ($col['Key'] === 'MUL'): ?>
                                            <span class="badge badge-gray">INDEX</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($col['Default'] === null): ?>
                                            <span class="null-value">NULL</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars($col['Default']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($col['Extra']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if (!empty($indexes)): ?>
                    <h3 style="margin-top: 2rem; margin-bottom: 1rem;">Indexes</h3>
                    <div class="data-table-wrapper">
                        <table class="structure-table">
                            <thead>
                                <tr>
                                    <th>Key Name</th>
                                    <th>Column</th>
                                    <th>Unique</th>
                                    <th>Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($indexes as $idx): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($idx['Key_name']) ?></td>
                                        <td><span class="code-inline"><?= htmlspecialchars($idx['Column_name']) ?></span></td>
                                        <td><?= $idx['Non_unique'] == 0 ? 'Yes' : 'No' ?></td>
                                        <td><?= htmlspecialchars($idx['Index_type']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <h3 style="margin-top: 2rem; margin-bottom: 1rem;">Table Statistics</h3>
                <div class="card">
                    <div class="card-body">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                            <div>
                                <div style="color: rgba(var(--text-rgb), 0.6); font-size: 0.85rem; margin-bottom: 0.25rem;">Total Rows</div>
                                <div style="font-size: 1.5rem; font-weight: 600;"><?= number_format(DbTool::getTableRowCount($currentPdo, $table)) ?></div>
                            </div>
                            <div>
                                <div style="color: rgba(var(--text-rgb), 0.6); font-size: 0.85rem; margin-bottom: 0.25rem;">Total Columns</div>
                                <div style="font-size: 1.5rem; font-weight: 600;"><?= count($columns) ?></div>
                            </div>
                            <div>
                                <div style="color: rgba(var(--text-rgb), 0.6); font-size: 0.85rem; margin-bottom: 0.25rem;">Total Indexes</div>
                                <div style="font-size: 1.5rem; font-weight: 600;"><?= count(array_unique(array_column($indexes, 'Key_name'))) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                
                
                
                
               <h3 style="margin-top: 2rem; margin-bottom: 1rem;">Create SQL</h3>
                <div class="card">
                    <div class="card-body">
                        <?php
                        // Get CREATE TABLE/VIEW statement
                        $isView = false;
                        $createSQL = '';
                        
                        // Check if it's a view
                        try {
                            $viewCheck = $currentPdo->query("SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '" . addslashes($table) . "'");
                            $isView = $viewCheck && $viewCheck->rowCount() > 0;
                        } catch (Exception $e) {
                            $isView = false;
                        }
                        
                        // Get the CREATE statement
                        try {
                            if ($isView) {
                                $stmt = $currentPdo->query("SHOW CREATE VIEW `" . DbTool::escapeIdentifier($table) . "`");
                                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                $createSQL = $result['Create View'] ?? '';
                            } else {
                                $stmt = $currentPdo->query("SHOW CREATE TABLE `" . DbTool::escapeIdentifier($table) . "`");
                                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                                $createSQL = $result['Create Table'] ?? '';
                            }
                        } catch (Exception $e) {
                            $createSQL = '-- Error: ' . $e->getMessage();
                        }
                        ?>
                        
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <div>
                                <strong><?= $isView ? 'CREATE VIEW' : 'CREATE TABLE' ?> Statement</strong>
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="copyCreateSQL()">
                                📋 Copy to Clipboard
                            </button>
                        </div>
                        
                        <textarea id="createSQLText" 
                                  class="sql-editor" 
                                  readonly 
                                  style="min-height: 200px; background: rgba(var(--surface-rgb), 0.5);"><?= htmlspecialchars($createSQL) ?></textarea>
                    </div>
                </div>
                
                <script>
                function copyCreateSQL() {
                    const textarea = document.getElementById('createSQLText');
                    textarea.select();
                    textarea.setSelectionRange(0, 99999); // For mobile
                    
                    try {
                        if (navigator.clipboard) {
                            navigator.clipboard.writeText(textarea.value).then(function() {
                                if (window.showToast) {
                                    window.showToast('SQL copied to clipboard!', 'success');
                                } else {
                                    alert('SQL copied to clipboard!');
                                }
                            }).catch(function() {
                                // Fallback
                                document.execCommand('copy');
                                if (window.showToast) {
                                    window.showToast('SQL copied to clipboard!', 'success');
                                } else {
                                    alert('SQL copied to clipboard!');
                                }
                            });
                        } else {
                            // Old browser fallback
                            document.execCommand('copy');
                            if (window.showToast) {
                                window.showToast('SQL copied to clipboard!', 'success');
                            } else {
                                alert('SQL copied to clipboard!');
                            }
                        }
                    } catch (err) {
                        alert('Failed to copy. Please select and copy manually.');
                    }
                }
                </script>
                
                
                
                
                
                
                
                
                
                
            </div>
            
            

<?php elseif ($action === 'alter_structure' && $table): ?>
            <!-- ALTER STRUCTURE VIEW -->
            <?php
            $columns = DbTool::getTableInfo($currentPdo, $table);
            $indexes = DbTool::getTableIndexes($currentPdo, $table);
            
            // Group indexes by key name
            $groupedIndexes = [];
            foreach ($indexes as $idx) {
                $keyName = $idx['Key_name'];
                if (!isset($groupedIndexes[$keyName])) {
                    $groupedIndexes[$keyName] = [
                        'name' => $keyName,
                        'unique' => $idx['Non_unique'] == 0,
                        'type' => $idx['Index_type'],
                        'columns' => []
                    ];
                }
                $groupedIndexes[$keyName]['columns'][] = $idx['Column_name'];
            }
            ?>
            
            <div class="section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 class="section-header" style="margin: 0;">Edit Structure: <?= htmlspecialchars($table) ?></h2>
<div style="display: block; width: 100%;"> </div>
                    <div class="flex-gap">
                        <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=structure&table=<?= urlencode($table) ?>" class="btn btn-sm">View Only</a>
                        <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=browse&table=<?= urlencode($table) ?>" class="btn btn-sm">Browse Data</a>
                    </div>
                </div>

                <div class="notification notification-warning" style="margin-bottom: 1.5rem;">
                    <strong>Warning!</strong> Altering table structure can cause data loss. Always backup your data before making structural changes.
                </div>

                <!-- Columns Section -->
                <div class="card" style="margin-bottom: 2rem;">
                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h3 style="margin: 0;">Columns</h3>
                            <button type="button" class="btn btn-sm btn-accent" onclick="addColumnRow()">+ Add Column</button>
                        </div>
                        
                        
                        
                        
                       <div id="columnsContainer">
                            <?php foreach ($columns as $idx => $col): ?>
                                <?php
                                // Parse default value properly
                                $defaultValue = $col['Default'];
                                if ($defaultValue !== null) {
                                    // Don't show quotes around CURRENT_TIMESTAMP or other functions
                                    if (stripos($defaultValue, 'CURRENT_TIMESTAMP') !== false || 
                                        stripos($defaultValue, 'NOW()') !== false ||
                                        stripos($defaultValue, 'UUID()') !== false) {
                                        $defaultValue = strtoupper($defaultValue);
                                    }
                                }
                                
                                // Parse extra field to extract ON UPDATE if present
                                $extraField = $col['Extra'];
                                ?>
                                
                                
                                
                                
                                
                               <div class="column-row" data-original="true" data-original-name="<?= htmlspecialchars($col['Field']) ?>">
                                    <div style="display: grid; grid-template-columns: 2fr 2fr 1fr 2fr 1fr 140px; gap: 0.5rem; align-items: start; padding: 0.75rem; border: 1px solid rgba(var(--muted-border-rgb), 0.12); border-radius: 6px; margin-bottom: 0.5rem; background: rgba(var(--card-rgb), 1);">
                                        <div>
                                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Name</label>
                                            <input type="text" 
                                                   class="form-control form-control-sm col-name" 
                                                   value="<?= htmlspecialchars($col['Field']) ?>"
                                                   <?= $col['Key'] === 'PRI' ? 'readonly title="Cannot rename primary key"' : '' ?>>
                                        </div>
                                        <div>
                                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Type</label>
                                            <select class="form-control form-control-sm col-type">
                                                <?php
                                                $types = ['INT', 'BIGINT', 'VARCHAR(255)', 'TEXT', 'LONGTEXT', 'DATETIME', 'DATE', 'TIMESTAMP', 'DECIMAL(10,2)', 'FLOAT', 'DOUBLE', 'BOOLEAN', 'ENUM', 'JSON'];
                                                $currentType = strtoupper($col['Type']);
                                                $matched = false;
                                                
                                                foreach ($types as $type) {
                                                    $typeBase = explode('(', $type)[0];
                                                    $currentTypeBase = explode('(', $currentType)[0];
                                                    $selected = ($currentTypeBase === $typeBase) ? 'selected' : '';
                                                    if ($selected) $matched = true;
                                                    echo "<option value=\"$type\" $selected>$type</option>";
                                                }
                                                ?>
                                                <option value="CUSTOM" <?= !$matched ? 'selected' : '' ?>>Custom...</option>
                                            </select>
                                            <input type="text" 
                                                   class="form-control form-control-sm col-type-custom" 
                                                   value="<?= htmlspecialchars($col['Type']) ?>"
                                                   placeholder="e.g., VARCHAR(100)"
                                                   style="margin-top: 0.25rem; display: <?= !$matched ? 'block' : 'none' ?>;">
                                        </div>
                                        <div>
                                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Null</label>
                                            <select class="form-control form-control-sm col-null">
                                                <option value="YES" <?= $col['Null'] === 'YES' ? 'selected' : '' ?>>NULL</option>
                                                <option value="NO" <?= $col['Null'] === 'NO' ? 'selected' : '' ?>>NOT NULL</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Default</label>
                                            <input type="text" 
                                                   class="form-control form-control-sm col-default" 
                                                   value="<?= $defaultValue !== null ? htmlspecialchars($defaultValue) : '' ?>"
                                                   placeholder="NULL or value">
                                        </div>
                                        <div>
                                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Extra</label>
                                            <input type="text" 
                                                   class="form-control form-control-sm col-extra" 
                                                   value="<?= htmlspecialchars($extraField) ?>"
                                                   placeholder="auto_increment"
                                                   <?= $col['Key'] === 'PRI' && strpos($extraField, 'auto_increment') !== false ? 'readonly' : '' ?>>
                                        </div>
                                        <div style="display: flex; align-items: flex-end; gap: 0.25rem;">
                                            <button type="button" 
                                                    class="btn btn-sm btn-secondary" 
                                                    onclick="moveColumnUp(this)"
                                                    title="Move up"
                                                    style="padding: 0.25rem 0.5rem;">↑</button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-secondary" 
                                                    onclick="moveColumnDown(this)"
                                                    title="Move down"
                                                    style="padding: 0.25rem 0.5rem;">↓</button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-primary" 
                                                    onclick="addColumnAfter(this)"
                                                    title="Add column after"
                                                    style="padding: 0.25rem 0.5rem;">+</button>
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
                                                    onclick="removeColumnRow(this)"
                                                    <?= $col['Key'] === 'PRI' ? 'disabled title="Cannot drop primary key"' : '' ?>
                                                    style="padding: 0.25rem 0.5rem;">✕</button>
                                        </div>
                                    </div>
                                </div>
                                
                                
                                
                                
                                
                                
                                
                                
                            <?php endforeach; ?>
                        </div>
                        
                        
                
                        
                        
                        
                        
                        
                        
                        
                        
                        
                        
                    </div>
                </div>

                <!-- Indexes Section -->
                <div class="card" style="margin-bottom: 2rem;">
                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h3 style="margin: 0;">Indexes</h3>
                            <button type="button" class="btn btn-sm btn-accent" onclick="addIndexRow()">+ Add Index</button>
                        </div>
                        
                        <div id="indexesContainer">
                            <?php foreach ($groupedIndexes as $keyName => $index): ?>
                                <?php if ($keyName === 'PRIMARY') continue; // Skip primary key ?>
                                <div class="index-row" data-original="true" data-original-name="<?= htmlspecialchars($keyName) ?>">
                                    <div style="display: grid; grid-template-columns: 2fr 3fr 1fr 80px; gap: 0.5rem; align-items: start; padding: 0.75rem; border: 1px solid rgba(var(--muted-border-rgb), 0.12); border-radius: 6px; margin-bottom: 0.5rem; background: rgba(var(--card-rgb), 1);">
                                        <div>
                                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Name</label>
                                            <input type="text" 
                                                   class="form-control form-control-sm idx-name" 
                                                   value="<?= htmlspecialchars($keyName) ?>">
                                        </div>
                                        <div>
                                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Columns (comma-separated)</label>
                                            <input type="text" 
                                                   class="form-control form-control-sm idx-columns" 
                                                   value="<?= htmlspecialchars(implode(', ', $index['columns'])) ?>">
                                        </div>
                                        <div>
                                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Type</label>
                                            <select class="form-control form-control-sm idx-unique">
                                                <option value="0" <?= !$index['unique'] ? 'selected' : '' ?>>INDEX</option>
                                                <option value="1" <?= $index['unique'] ? 'selected' : '' ?>>UNIQUE</option>
                                            </select>
                                        </div>
                                        <div style="display: flex; align-items: flex-end;">
                                            <button type="button" 
                                                    class="btn btn-sm btn-danger" 
                                                    onclick="removeIndexRow(this)"
                                                    style="width: 100%;">✕</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (empty(array_filter($groupedIndexes, function($k) { return $k !== 'PRIMARY'; }, ARRAY_FILTER_USE_KEY))): ?>
                            <div id="noIndexes" style="padding: 1rem; text-align: center; color: rgba(var(--text-rgb), 0.5);">
                                No indexes defined. Click "Add Index" to create one.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Preview & Execute -->
                <div class="card">
                    <div class="card-body">
                        <h3 style="margin-bottom: 1rem;">SQL Preview</h3>
                        <button type="button" class="btn btn-secondary" onclick="generateSQL()" style="margin-bottom: 1rem;">Generate SQL</button>
                        <textarea id="sqlPreview" 
                                  class="sql-editor" 
                                  readonly 
                                  placeholder="Click 'Generate SQL' to preview changes..."
                                  style="min-height: 150px; background: rgba(var(--surface-rgb), 0.5);"></textarea>
                    </div>
                    <div class="card-footer flex-gap">
                        <button type="button" class="btn btn-primary" onclick="executeAlter()">Execute Changes</button>
                        <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=structure&table=<?= urlencode($table) ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>
            </div>

            <script>
            const tableName = '<?= addslashes($table) ?>';
            const originalColumns = <?= json_encode($columns) ?>;
            
            // Show/hide custom type input
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('col-type')) {
                    const customInput = e.target.nextElementSibling;
                    if (e.target.value === 'CUSTOM') {
                        customInput.style.display = 'block';
                    } else {
                        customInput.style.display = 'none';
                    }
                }
            });
            
            function addColumnRow() {
                const container = document.getElementById('columnsContainer');
                const row = document.createElement('div');
                row.className = 'column-row';
                row.dataset.original = 'false';
                row.innerHTML = `
                    <div style="display: grid; grid-template-columns: 2fr 2fr 1fr 2fr 1fr 140px; gap: 0.5rem; align-items: start; padding: 0.75rem; border: 1px solid rgba(var(--muted-border-rgb), 0.12); border-radius: 6px; margin-bottom: 0.5rem; background: rgba(var(--accent-rgb), 0.05);">
                        <div>
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Name</label>
                            <input type="text" class="form-control form-control-sm col-name" placeholder="new_column">
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Type</label>
                            <select class="form-control form-control-sm col-type">
                                <option value="INT">INT</option>
                                <option value="BIGINT">BIGINT</option>
                                <option value="VARCHAR(255)" selected>VARCHAR(255)</option>
                                <option value="TEXT">TEXT</option>
                                <option value="LONGTEXT">LONGTEXT</option>
                                <option value="DATETIME">DATETIME</option>
                                <option value="DATE">DATE</option>
                                <option value="TIMESTAMP">TIMESTAMP</option>
                                <option value="DECIMAL(10,2)">DECIMAL(10,2)</option>
                                <option value="FLOAT">FLOAT</option>
                                <option value="DOUBLE">DOUBLE</option>
                                <option value="BOOLEAN">BOOLEAN</option>
                                <option value="ENUM">ENUM</option>
                                <option value="JSON">JSON</option>
                                <option value="CUSTOM">Custom...</option>
                            </select>
                            <input type="text" class="form-control form-control-sm col-type-custom" placeholder="e.g., VARCHAR(100)" style="margin-top: 0.25rem; display: none;">
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Null</label>
                            <select class="form-control form-control-sm col-null">
                                <option value="YES">NULL</option>
                                <option value="NO">NOT NULL</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Default</label>
                            <input type="text" class="form-control form-control-sm col-default" placeholder="NULL or value">
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Extra</label>
                            <input type="text" class="form-control form-control-sm col-extra" placeholder="auto_increment">
                        </div>
                        <div style="display: flex; align-items: flex-end; gap: 0.25rem;">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="moveColumnUp(this)" title="Move up" style="padding: 0.25rem 0.5rem;">↑</button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="moveColumnDown(this)" title="Move down" style="padding: 0.25rem 0.5rem;">↓</button>
                            <button type="button" class="btn btn-sm btn-primary" onclick="addColumnAfter(this)" title="Add column after" style="padding: 0.25rem 0.5rem;">+</button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="removeColumnRow(this)" style="padding: 0.25rem 0.5rem;">✕</button>
                        </div>
                    </div>
                `;
                container.appendChild(row);
            }
            
            function removeColumnRow(btn) {
                const row = btn.closest('.column-row');
                if (row.dataset.original === 'true') {
                    row.style.opacity = '0.4';
                    row.dataset.deleted = 'true';
                    btn.textContent = '↺';
                    btn.classList.remove('btn-danger');
                    btn.classList.add('btn-secondary');
                    btn.onclick = function() { undoRemoveColumnRow(this); };
                } else {
                    row.remove();
                }
            }
            
            function undoRemoveColumnRow(btn) {
                const row = btn.closest('.column-row');
                row.style.opacity = '1';
                row.dataset.deleted = 'false';
                btn.textContent = '✕';
                btn.classList.add('btn-danger');
                btn.classList.remove('btn-secondary');
                btn.onclick = function() { removeColumnRow(this); };
            }
            
            function addIndexRow() {
                const container = document.getElementById('indexesContainer');
                const noIndexes = document.getElementById('noIndexes');
                if (noIndexes) noIndexes.remove();
                
                const row = document.createElement('div');
                row.className = 'index-row';
                row.dataset.original = 'false';
                row.innerHTML = `
                    <div style="display: grid; grid-template-columns: 2fr 3fr 1fr 80px; gap: 0.5rem; align-items: start; padding: 0.75rem; border: 1px solid rgba(var(--muted-border-rgb), 0.12); border-radius: 6px; margin-bottom: 0.5rem; background: rgba(var(--accent-rgb), 0.05);">
                        <div>
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Name</label>
                            <input type="text" class="form-control form-control-sm idx-name" placeholder="idx_column_name">
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Columns (comma-separated)</label>
                            <input type="text" class="form-control form-control-sm idx-columns" placeholder="column1, column2">
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Type</label>
                            <select class="form-control form-control-sm idx-unique">
                                <option value="0">INDEX</option>
                                <option value="1">UNIQUE</option>
                            </select>
                        </div>
                        <div style="display: flex; align-items: flex-end;">
                            <button type="button" class="btn btn-sm btn-danger" onclick="removeIndexRow(this)" style="width: 100%;">✕</button>
                        </div>
                    </div>
                `;
                container.appendChild(row);
            }
            
            function removeIndexRow(btn) {
                const row = btn.closest('.index-row');
                if (row.dataset.original === 'true') {
                    row.style.opacity = '0.4';
                    row.dataset.deleted = 'true';
                    btn.textContent = '↺';
                    btn.classList.remove('btn-danger');
                    btn.classList.add('btn-secondary');
                    btn.onclick = function() { undoRemoveIndexRow(this); };
                } else {
                    row.remove();
                }
            }
            
            function undoRemoveIndexRow(btn) {
                const row = btn.closest('.index-row');
                row.style.opacity = '1';
                row.dataset.deleted = 'false';
                btn.textContent = '✕';
                btn.classList.add('btn-danger');
                btn.classList.remove('btn-secondary');
                btn.onclick = function() { removeIndexRow(this); };
            }
            
            
            
            
            
            
            
           function addColumnAfter(btn) {
                const currentRow = btn.closest('.column-row');
                const container = document.getElementById('columnsContainer');
                const row = document.createElement('div');
                row.className = 'column-row';
                row.dataset.original = 'false';
                row.innerHTML = `
                    <div style="display: grid; grid-template-columns: 2fr 2fr 1fr 2fr 1fr 140px; gap: 0.5rem; align-items: start; padding: 0.75rem; border: 1px solid rgba(var(--muted-border-rgb), 0.12); border-radius: 6px; margin-bottom: 0.5rem; background: rgba(var(--accent-rgb), 0.05);">
                        <div>
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Name</label>
                            <input type="text" class="form-control form-control-sm col-name" placeholder="new_column">
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Type</label>
                            <select class="form-control form-control-sm col-type">
                                <option value="INT">INT</option>
                                <option value="BIGINT">BIGINT</option>
                                <option value="VARCHAR(255)" selected>VARCHAR(255)</option>
                                <option value="TEXT">TEXT</option>
                                <option value="LONGTEXT">LONGTEXT</option>
                                <option value="DATETIME">DATETIME</option>
                                <option value="DATE">DATE</option>
                                <option value="TIMESTAMP">TIMESTAMP</option>
                                <option value="DECIMAL(10,2)">DECIMAL(10,2)</option>
                                <option value="FLOAT">FLOAT</option>
                                <option value="DOUBLE">DOUBLE</option>
                                <option value="BOOLEAN">BOOLEAN</option>
                                <option value="ENUM">ENUM</option>
                                <option value="JSON">JSON</option>
                                <option value="CUSTOM">Custom...</option>
                            </select>
                            <input type="text" class="form-control form-control-sm col-type-custom" placeholder="e.g., VARCHAR(100)" style="margin-top: 0.25rem; display: none;">
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Null</label>
                            <select class="form-control form-control-sm col-null">
                                <option value="YES">NULL</option>
                                <option value="NO">NOT NULL</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Default</label>
                            <input type="text" class="form-control form-control-sm col-default" placeholder="NULL or value">
                        </div>
                        <div>
                            <label class="form-label" style="font-size: 0.8rem; margin-bottom: 0.25rem;">Extra</label>
                            <input type="text" class="form-control form-control-sm col-extra" placeholder="auto_increment">
                        </div>
                        <div style="display: flex; align-items: flex-end; gap: 0.25rem;">
                            <button type="button" class="btn btn-sm btn-secondary" onclick="moveColumnUp(this)" title="Move up" style="padding: 0.25rem 0.5rem;">↑</button>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="moveColumnDown(this)" title="Move down" style="padding: 0.25rem 0.5rem;">↓</button>
                            <button type="button" class="btn btn-sm btn-primary" onclick="addColumnAfter(this)" title="Add column after" style="padding: 0.25rem 0.5rem;">+</button>
                            <button type="button" class="btn btn-sm btn-danger" onclick="removeColumnRow(this)" style="padding: 0.25rem 0.5rem;">✕</button>
                        </div>
                    </div>
                `;
                
                // Insert after current row
                currentRow.parentNode.insertBefore(row, currentRow.nextSibling);
                
                // Focus on the name input
                row.querySelector('.col-name').focus();
            }
            
            function moveColumnUp(btn) {
                const row = btn.closest('.column-row');
                const prev = row.previousElementSibling;
                if (prev) {
                    row.parentNode.insertBefore(row, prev);
                }
            }
            
            function moveColumnDown(btn) {
                const row = btn.closest('.column-row');
                const next = row.nextElementSibling;
                if (next) {
                    row.parentNode.insertBefore(next, row);
                }
            }
            
            
            
            
            
            
            
            
           function generateSQL() {
                const statements = [];
                const dropIndexes = [];
                const dropColumns = [];
                const modifyColumns = [];
                const addColumns = [];
                const addIndexes = [];
                
                // Build original column order map
                const originalOrder = {};
                originalColumns.forEach((col, idx) => {
                    originalOrder[col.Field] = idx;
                });
                
                // Process columns
                const columnRows = document.querySelectorAll('.column-row');
                const currentOrder = [];
                
                columnRows.forEach((row, currentIndex) => {
                    const isOriginal = row.dataset.original === 'true';
                    const isDeleted = row.dataset.deleted === 'true';
                    const originalName = row.dataset.originalName;
                    
                    const name = row.querySelector('.col-name').value.trim();
                    const typeSelect = row.querySelector('.col-type');
                    const typeCustom = row.querySelector('.col-type-custom');
                    const type = typeSelect.value === 'CUSTOM' ? typeCustom.value.trim() : typeSelect.value;
                    const isNull = row.querySelector('.col-null').value;
                    const defaultVal = row.querySelector('.col-default').value.trim();
                    const extra = row.querySelector('.col-extra').value.trim();
                    
                    if (!name || !type) return;
                    
                    if (!isDeleted && isOriginal) {
                        currentOrder.push({ name: originalName || name, currentIndex });
                    }
                    
                    let colDef = `\`${name}\` ${type}`;
                    colDef += isNull === 'NO' ? ' NOT NULL' : ' NULL';
                    
                    // Handle default values properly
                    if (defaultVal) {
                        const defaultUpper = defaultVal.toUpperCase();
                        // SQL functions and keywords shouldn't be quoted
                        if (defaultUpper === 'NULL' || 
                            defaultUpper === 'CURRENT_TIMESTAMP' || 
                            defaultUpper === 'CURRENT_TIMESTAMP()' ||
                            defaultUpper.includes('NOW()') ||
                            defaultUpper.includes('UUID()')) {
                            colDef += ` DEFAULT ${defaultUpper.replace('()', '')}`;
                        } else if (!isNaN(defaultVal)) {
                            // Numeric values
                            colDef += ` DEFAULT ${defaultVal}`;
                        } else {
                            // String values - needs quotes
                            colDef += ` DEFAULT '${defaultVal.replace(/'/g, "''")}'`;
                        }
                    }
                    
                    if (extra) {
                        const extraUpper = extra.toUpperCase();
                        // Handle ON UPDATE for timestamps
                        if (extraUpper.includes('ON UPDATE')) {
                            colDef += ` ${extraUpper}`;
                        } else {
                            colDef += ` ${extra}`;
                        }
                    }
                    
                    if (isDeleted) {
                        dropColumns.push(`ALTER TABLE \`${tableName}\` DROP COLUMN \`${originalName}\`;`);
                    } else if (isOriginal && name !== originalName) {
                        // Column renamed - need CHANGE with position
                        const allRows = Array.from(document.querySelectorAll('.column-row:not([data-deleted="true"])'));
                        const rowIndex = allRows.indexOf(row);
                        let afterClause = '';
                        if (rowIndex > 0) {
                            const prevRow = allRows[rowIndex - 1];
                            const prevName = prevRow.querySelector('.col-name').value.trim();
                            if (prevName) {
                                afterClause = ` AFTER \`${prevName}\``;
                            }
                        } else {
                            afterClause = ' FIRST';
                        }
                        modifyColumns.push(`ALTER TABLE \`${tableName}\` CHANGE \`${originalName}\` ${colDef}${afterClause};`);
                    } else if (isOriginal) {
                        // Check if something changed
                        const original = originalColumns.find(c => c.Field === originalName);
                        if (original) {
                            const currentType = (typeSelect.value === 'CUSTOM' ? typeCustom.value.trim() : typeSelect.value).toUpperCase();
                            const originalType = original.Type.toUpperCase();
                            
                            // Normalize types for comparison
                            const normalizeType = (t) => t.replace(/\s+/g, ' ').trim();
                            const typeChanged = normalizeType(currentType) !== normalizeType(originalType);
                            
                            const nullChanged = original.Null !== isNull;
                            
                            // Normalize defaults for comparison
                            const normalizeDefault = (d) => {
                                if (!d) return '';
                                const upper = d.toUpperCase().replace(/[()]/g, '');
                                if (upper === 'CURRENT_TIMESTAMP') return 'CURRENT_TIMESTAMP';
                                return d;
                            };
                            const defaultChanged = normalizeDefault(original.Default || '') !== normalizeDefault(defaultVal);
                            
                            // Normalize extra for comparison
                            const normalizeExtra = (e) => {
                                if (!e) return '';
                                return e.toUpperCase().replace(/[()]/g, '').trim();
                            };
                            const extraChanged = normalizeExtra(original.Extra || '') !== normalizeExtra(extra);
                            
                            // Check if position changed
                            const originalPos = originalOrder[originalName];
                            const allRows = Array.from(document.querySelectorAll('.column-row:not([data-deleted="true"])'));
                            const currentPos = allRows.indexOf(row);
                            
                            // Count how many original columns are before this one in current order
                            let originalColsBefore = 0;
                            for (let i = 0; i < currentPos; i++) {
                                if (allRows[i].dataset.original === 'true' && allRows[i].dataset.deleted !== 'true') {
                                    originalColsBefore++;
                                }
                            }
                            const positionChanged = originalPos !== originalColsBefore;
                            
                            if (typeChanged || nullChanged || defaultChanged || extraChanged || positionChanged) {
                                // Determine position clause
                                let afterClause = '';
                                if (currentPos > 0) {
                                    const prevRow = allRows[currentPos - 1];
                                    const prevName = prevRow.querySelector('.col-name').value.trim();
                                    if (prevName) {
                                        afterClause = ` AFTER \`${prevName}\``;
                                    }
                                } else {
                                    afterClause = ' FIRST';
                                }
                                
                                modifyColumns.push(`ALTER TABLE \`${tableName}\` MODIFY ${colDef}${afterClause};`);
                            }
                        }
                    } else {
                        // New column - determine position
                        const allRows = Array.from(document.querySelectorAll('.column-row:not([data-deleted="true"])'));
                        const currentIndex = allRows.indexOf(row);
                        
                        let afterClause = '';
                        if (currentIndex > 0) {
                            const prevRow = allRows[currentIndex - 1];
                            const prevName = prevRow.querySelector('.col-name').value.trim();
                            if (prevName) {
                                afterClause = ` AFTER \`${prevName}\``;
                            }
                        } else {
                            afterClause = ' FIRST';
                        }
                        
                        addColumns.push(`ALTER TABLE \`${tableName}\` ADD ${colDef}${afterClause};`);
                    }
                });
                
                // Process indexes
                const indexRows = document.querySelectorAll('.index-row');
                indexRows.forEach(row => {
                    const isOriginal = row.dataset.original === 'true';
                    const isDeleted = row.dataset.deleted === 'true';
                    const originalName = row.dataset.originalName;
                    
                    const name = row.querySelector('.idx-name').value.trim();
                    const columns = row.querySelector('.idx-columns').value.trim();
                    const unique = row.querySelector('.idx-unique').value;
                    
                    if (!name || !columns) return;
                    
                    const colList = columns.split(',').map(c => `\`${c.trim()}\``).join(', ');
                    const idxType = unique === '1' ? 'UNIQUE INDEX' : 'INDEX';
                    
                    if (isDeleted) {
                        dropIndexes.push(`ALTER TABLE \`${tableName}\` DROP INDEX \`${originalName}\`;`);
                    } else if (isOriginal && name !== originalName) {
                        dropIndexes.push(`ALTER TABLE \`${tableName}\` DROP INDEX \`${originalName}\`;`);
                        addIndexes.push(`ALTER TABLE \`${tableName}\` ADD ${idxType} \`${name}\` (${colList});`);
                    } else if (!isOriginal) {
                        addIndexes.push(`ALTER TABLE \`${tableName}\` ADD ${idxType} \`${name}\` (${colList});`);
                    }
                });
                
                // Proper order: DROP indexes, DROP columns, MODIFY columns, ADD columns, ADD indexes
                statements.push(...dropIndexes);
                statements.push(...dropColumns);
                statements.push(...modifyColumns);
                statements.push(...addColumns);
                statements.push(...addIndexes);
                
                const preview = document.getElementById('sqlPreview');
                if (statements.length === 0) {
                    preview.value = '-- No changes detected';
                } else {
                    preview.value = '-- Operations ordered for safety:\n-- 1. Drop indexes first\n-- 2. Drop columns\n-- 3. Modify columns (includes reordering)\n-- 4. Add columns\n-- 5. Add indexes\n\n' + statements.join('\n\n');
                }
            }
            
            
            
            
            
      
            
            function executeAlter() {
                const sql = document.getElementById('sqlPreview').value;
                if (!sql || sql.includes('No changes detected')) {
                    alert('No changes to execute. Click "Generate SQL" first.');
                    return;
                }
                
                if (!confirm('Execute these ALTER TABLE statements? This will modify the table structure.\n\nMake sure you have a backup!')) {
                    return;
                }
                
                // Send to server
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = window.location.href;
                
                const sqlInput = document.createElement('input');
                sqlInput.type = 'hidden';
                sqlInput.name = 'alter_sql';
                sqlInput.value = sql;
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'execute_alter';
                
                form.appendChild(sqlInput);
                form.appendChild(actionInput);
                document.body.appendChild(form);
                form.submit();
            }
            </script>
             
            
            
            
            
            
            
            
            
            
            
            
<?php elseif ($action === 'view_structure' && $table): ?>
            <!-- VIEW STRUCTURE PAGE -->
            <?php
            // Get CREATE VIEW statement
            $createSQL = '';
            try {
                $stmt = $currentPdo->query("SHOW CREATE VIEW `" . DbTool::escapeIdentifier($table) . "`");
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $createSQL = $result['Create View'] ?? '';
            } catch (Exception $e) {
                $createSQL = '-- Error: ' . $e->getMessage();
            }
            
            // Get view info
            try {
                $viewInfo = $currentPdo->query("
                    SELECT * FROM information_schema.VIEWS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = '" . addslashes($table) . "'
                ")->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $viewInfo = null;
            }
            ?>
            
            <div class="section">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                    <h2 class="section-header" style="margin: 0;">View: <?= htmlspecialchars($table) ?></h2>
                    <div class="flex-gap">
                        <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=browse&table=<?= urlencode($table) ?>" class="btn btn-sm btn-secondary">Browse Data</a>
                        <a href="?db=<?= $_GET['db'] ?? 'main' ?>" class="btn btn-sm btn-secondary">← Back to Tables</a>
                    </div>
                </div>

                <?php if ($viewInfo): ?>
                    <div class="card" style="margin-bottom: 2rem;">
                        <div class="card-body">
                            <h3 style="margin-bottom: 1rem;">View Information</h3>
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                                <div>
                                    <div style="color: rgba(var(--text-rgb), 0.6); font-size: 0.85rem; margin-bottom: 0.25rem;">Definer</div>
                                    <div style="font-size: 1rem;"><?= htmlspecialchars($viewInfo['DEFINER'] ?? 'N/A') ?></div>
                                </div>
                                <div>
                                    <div style="color: rgba(var(--text-rgb), 0.6); font-size: 0.85rem; margin-bottom: 0.25rem;">Security Type</div>
                                    <div style="font-size: 1rem;"><?= htmlspecialchars($viewInfo['SECURITY_TYPE'] ?? 'N/A') ?></div>
                                </div>
                                <div>
                                    <div style="color: rgba(var(--text-rgb), 0.6); font-size: 0.85rem; margin-bottom: 0.25rem;">Algorithm</div>
                                    <div style="font-size: 1rem;"><?= htmlspecialchars($viewInfo['ALGORITHM'] ?? 'N/A') ?></div>
                                </div>
                                <div>
                                    <div style="color: rgba(var(--text-rgb), 0.6); font-size: 0.85rem; margin-bottom: 0.25rem;">Check Option</div>
                                    <div style="font-size: 1rem;"><?= htmlspecialchars($viewInfo['CHECK_OPTION'] ?? 'NONE') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-body">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                            <h3 style="margin: 0;">CREATE VIEW Statement</h3>
                            <button type="button" class="btn btn-sm btn-secondary" onclick="copyViewSQL()">
                                📋 Copy to Clipboard
                            </button>
                        </div>
                        
                        <textarea id="viewSQLText" 
                                  class="sql-editor" 
                                  readonly 
                                  style="min-height: 300px; background: rgba(var(--surface-rgb), 0.5);"><?= htmlspecialchars($createSQL) ?></textarea>
                    </div>
                </div>
            </div>
            
            <script>
            function copyViewSQL() {
                const textarea = document.getElementById('viewSQLText');
                textarea.select();
                textarea.setSelectionRange(0, 99999);
                
                try {
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(textarea.value).then(function() {
                            if (window.showToast) {
                                window.showToast('SQL copied to clipboard!', 'success');
                            } else {
                                alert('SQL copied to clipboard!');
                            }
                        }).catch(function() {
                            document.execCommand('copy');
                            if (window.showToast) {
                                window.showToast('SQL copied to clipboard!', 'success');
                            } else {
                                alert('SQL copied to clipboard!');
                            }
                        });
                    } else {
                        document.execCommand('copy');
                        if (window.showToast) {
                            window.showToast('SQL copied to clipboard!', 'success');
                        } else {
                            alert('SQL copied to clipboard!');
                        }
                    }
                } catch (err) {
                    alert('Failed to copy. Please select and copy manually.');
                }
            }
            </script>
            
            
            
            
            
            
            
            

<?php elseif ($action === 'insert' && $table): ?>
            <!-- INSERT ROW VIEW -->
            <?php
            $columns = DbTool::getTableInfo($currentPdo, $table);
            ?>
            
            <div class="section">
                <h2 class="section-header">Insert Row: <?= htmlspecialchars($table) ?></h2>
                <div class="card">
                    <form method="POST" action="?db=<?= $_GET['db'] ?? 'main' ?>&action=insert_row&table=<?= urlencode($table) ?>">
                        <div class="card-body">
                            <?php foreach ($columns as $col): ?>
                                <?php if ($col['Extra'] === 'auto_increment') continue; ?>
                                <div class="form-group">
                                    <label for="field_<?= htmlspecialchars($col['Field']) ?>" class="form-label">
                                        <?= htmlspecialchars($col['Field']) ?>
                                        <span style="color: rgba(var(--text-rgb), 0.5); font-size: 0.85rem;">
                                            (<?= htmlspecialchars($col['Type']) ?>)
                                        </span>
                                        <?php if ($col['Null'] === 'NO' && $col['Default'] === null): ?>
                                            <span style="color: red;">*</span>
                                        <?php endif; ?>
                                    </label>
                                    
                                    <?php if (strpos($col['Type'], 'text') !== false || strpos($col['Type'], 'blob') !== false): ?>
                                        <textarea 
                                            id="field_<?= htmlspecialchars($col['Field']) ?>" 
                                            name="values[]" 
                                            class="form-control"
                                            <?= $col['Null'] === 'NO' && $col['Default'] === null ? 'required' : '' ?>
                                        ></textarea>
                                    <?php elseif (strpos($col['Type'], 'int') !== false): ?>
                                        <input 
                                            type="number" 
                                            id="field_<?= htmlspecialchars($col['Field']) ?>" 
                                            name="values[]" 
                                            class="form-control"
                                            <?= $col['Null'] === 'NO' && $col['Default'] === null ? 'required' : '' ?>
                                        >
                                    <?php elseif (strpos($col['Type'], 'date') !== false || strpos($col['Type'], 'time') !== false): ?>
                                        <input 
                                            type="<?= strpos($col['Type'], 'datetime') !== false ? 'datetime-local' : (strpos($col['Type'], 'date') !== false ? 'date' : 'time') ?>" 
                                            id="field_<?= htmlspecialchars($col['Field']) ?>" 
                                            name="values[]" 
                                            class="form-control"
                                            <?= $col['Null'] === 'NO' && $col['Default'] === null ? 'required' : '' ?>
                                        >
                                    <?php else: ?>
                                        <input 
                                            type="text" 
                                            id="field_<?= htmlspecialchars($col['Field']) ?>" 
                                            name="values[]" 
                                            class="form-control"
                                            <?= $col['Null'] === 'NO' && $col['Default'] === null ? 'required' : '' ?>
                                        >
                                    <?php endif; ?>
                                    
                                    <input type="hidden" name="columns[]" value="<?= htmlspecialchars($col['Field']) ?>">
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="card-footer flex-gap">
                            <button type="submit" class="btn btn-primary">Insert Row</button>
                            <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=browse&table=<?= urlencode($table) ?>" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>

<?php elseif ($action === 'edit' && $table): ?>
            <!-- EDIT ROW VIEW -->
            <?php
            $id = $_GET['id'] ?? null;
            $columns = DbTool::getTableInfo($currentPdo, $table);
            
            // Find primary key
            $primaryKey = null;
            foreach ($columns as $col) {
                if ($col['Key'] === 'PRI') {
                    $primaryKey = $col['Field'];
                    break;
                }
            }
            if (!$primaryKey && !empty($columns)) {
                $primaryKey = $columns[0]['Field'];
            }
            
            // Get row data
            $stmt = $currentPdo->prepare("SELECT * FROM `" . DbTool::escapeIdentifier($table) . "` WHERE `" . DbTool::escapeIdentifier($primaryKey) . "` = ? LIMIT 1");
            $stmt->execute([$id]);
            $rowData = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$rowData):
            ?>
                <div class="notification notification-error">
                    Row not found.
                </div>
                <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=browse&table=<?= urlencode($table) ?>" class="btn btn-secondary">Back to Table</a>
            <?php else: ?>
                <div class="section">
                    <h2 class="section-header">Edit Row: <?= htmlspecialchars($table) ?></h2>
                    <div class="card">
                        <form method="POST" action="?db=<?= $_GET['db'] ?? 'main' ?>&action=update_row&table=<?= urlencode($table) ?>">
                            <input type="hidden" name="id" value="<?= htmlspecialchars($id) ?>">
                            <input type="hidden" name="id_column" value="<?= htmlspecialchars($primaryKey) ?>">
                            
                            <div class="card-body">
                                <?php foreach ($columns as $col): ?>
                                    <div class="form-group">
                                        <label for="field_<?= htmlspecialchars($col['Field']) ?>" class="form-label">
                                            <?= htmlspecialchars($col['Field']) ?>
                                            <span style="color: rgba(var(--text-rgb), 0.5); font-size: 0.85rem;">
                                                (<?= htmlspecialchars($col['Type']) ?>)
                                            </span>
                                            <?php if ($col['Key'] === 'PRI'): ?>
                                                <span class="badge badge-blue" style="font-size: 0.7rem;">PRIMARY KEY</span>
                                            <?php endif; ?>
                                        </label>
                                        
                                        <?php 
                                        $value = $rowData[$col['Field']];
                                        $disabled = ($col['Key'] === 'PRI' || $col['Extra'] === 'auto_increment') ? 'readonly' : '';
                                        ?>
                                        
                                        <?php if (strpos($col['Type'], 'text') !== false || strpos($col['Type'], 'blob') !== false): ?>
                                            <textarea 
                                                id="field_<?= htmlspecialchars($col['Field']) ?>" 
                                                name="values[]" 
                                                class="form-control"
                                                <?= $disabled ?>
                                            ><?= htmlspecialchars($value ?? '') ?></textarea>
                                        <?php elseif (strpos($col['Type'], 'int') !== false): ?>
                                            <input 
                                                type="number" 
                                                id="field_<?= htmlspecialchars($col['Field']) ?>" 
                                                name="values[]" 
                                                class="form-control"
                                                value="<?= htmlspecialchars($value ?? '') ?>"
                                                <?= $disabled ?>
                                            >
                                        <?php elseif (strpos($col['Type'], 'datetime') !== false): ?>
                                            <input 
                                                type="datetime-local" 
                                                id="field_<?= htmlspecialchars($col['Field']) ?>" 
                                                name="values[]" 
                                                class="form-control"
                                                value="<?= $value ? date('Y-m-d\TH:i', strtotime($value)) : '' ?>"
                                                <?= $disabled ?>
                                            >
                                        <?php elseif (strpos($col['Type'], 'date') !== false): ?>
                                            <input 
                                                type="date" 
                                                id="field_<?= htmlspecialchars($col['Field']) ?>" 
                                                name="values[]" 
                                                class="form-control"
                                                value="<?= htmlspecialchars($value ?? '') ?>"
                                                <?= $disabled ?>
                                            >
                                        <?php else: ?>
                                            <input 
                                                type="text" 
                                                id="field_<?= htmlspecialchars($col['Field']) ?>" 
                                                name="values[]" 
                                                class="form-control"
                                                value="<?= htmlspecialchars($value ?? '') ?>"
                                                <?= $disabled ?>
                                            >
                                        <?php endif; ?>
                                        
                                        <input type="hidden" name="columns[]" value="<?= htmlspecialchars($col['Field']) ?>">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="card-footer flex-gap">
                                <button type="submit" class="btn btn-primary">Save Changes</button>
                                <a href="?db=<?= $_GET['db'] ?? 'main' ?>&action=browse&table=<?= urlencode($table) ?>" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>

        <?php endif; ?>
        </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        
        // Close export menu when clicking outside
        document.addEventListener('click', function(e) {
            const exportMenu = document.getElementById('exportMenu');
            if (exportMenu && !e.target.closest('#exportMenu') && !e.target.matches('button')) {
                exportMenu.style.display = 'none';
            }
        });
        
        
        // Auto-resize textareas
        const textareas = document.querySelectorAll('textarea.form-control');
        textareas.forEach(textarea => {
            textarea.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = (this.scrollHeight) + 'px';
            });
        });
        
        // SQL Editor enhancements
        const sqlEditor = document.getElementById('sql');
        if (sqlEditor) {
            // Add tab support
            sqlEditor.addEventListener('keydown', function(e) {
                if (e.key === 'Tab') {
                    e.preventDefault();
                    const start = this.selectionStart;
                    const end = this.selectionEnd;
                    this.value = this.value.substring(0, start) + '    ' + this.value.substring(end);
                    this.selectionStart = this.selectionEnd = start + 4;
                }
            });
            
            // Auto-resize
            sqlEditor.style.height = 'auto';
            sqlEditor.style.height = (sqlEditor.scrollHeight) + 'px';
        }
        
        // Highlight current table in sidebar (if you add one later)
        const currentTable = new URLSearchParams(window.location.search).get('table');
        if (currentTable) {
            document.querySelectorAll('.table-card').forEach(card => {
                const title = card.querySelector('.table-card-title');
                if (title && title.textContent === currentTable) {
                    card.style.borderColor = 'rgba(var(--accent-rgb), 0.5)';
                }
            });
        }
        
        // Copy to clipboard functionality (for future use)
        window.copyToClipboard = function(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function() {
                    showToast('Copied to clipboard!', 'success');
                }).catch(function() {
                    showToast('Failed to copy', 'error');
                });
            } else {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                try {
                    document.execCommand('copy');
                    showToast('Copied to clipboard!', 'success');
                } catch (err) {
                    showToast('Failed to copy', 'error');
                }
                document.body.removeChild(textarea);
            }
        };
        
        // Toast notification system
        window.showToast = function(message, type = 'success') {
            let toastContainer = document.getElementById('toastContainer');
            if (!toastContainer) {
                toastContainer = document.createElement('div');
                toastContainer.id = 'toastContainer';
                toastContainer.className = 'toast-container';
                toastContainer.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000;';
                document.body.appendChild(toastContainer);
            }
            
            const toast = document.createElement('div');
            toast.className = `notification notification-${type}`;
            toast.style.cssText = 'margin-bottom: 10px; min-width: 250px; animation: slideIn 0.3s ease;';
            toast.textContent = message;
            
            toastContainer.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        };
        
        // Add CSS for toast animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            
            @keyframes slideOut {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(400px);
                    opacity: 0;
                }
            }
            
            .data-table td:hover {
                background: rgba(var(--hover-rgb), 0.1);
            }
            
            .table-card {
                cursor: pointer;
            }
            
            /* Responsive improvements */
            @media (max-width: 768px) {
                .dbtool-header {
                    flex-direction: column;
                    align-items: flex-start;
                }
                
                .dbtool-nav {
                    width: 100%;
                }
                
                .db-selector {
                    width: 100%;
                }
                
                .data-table-wrapper {
                    overflow-x: auto;
                    -webkit-overflow-scrolling: touch;
                }
                
                .data-table {
                    min-width: 600px;
                }
                
                .flex-gap {
                    flex-wrap: wrap;
                }
                
                .table-list {
                    grid-template-columns: 1fr;
                }
            }
            
            /* Print styles */
            @media print {
                .dbtool-header,
                .dbtool-nav,
                .breadcrumb,
                .btn,
                button,
                .action-cell {
                    display: none !important;
                }
                
                .data-table {
                    border: 1px solid #000;
                }
                
                .data-table th,
                .data-table td {
                    border: 1px solid #000;
                    padding: 5px;
                }
            }
        `;
        document.head.appendChild(style);
        
        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + K for SQL query
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                window.location.href = '?db=' + (new URLSearchParams(window.location.search).get('db') || 'main') + '&action=sql';
            }
            
            // Escape to go home
            if (e.key === 'Escape' && !e.target.matches('input, textarea, select')) {
                const db = new URLSearchParams(window.location.search).get('db') || 'main';
                window.location.href = '?db=' + db;
            }
        });
        
        // Show keyboard shortcuts hint (optional)
        console.log('%cKeyboard Shortcuts:', 'font-weight: bold; font-size: 14px;');
        console.log('Ctrl/Cmd + K: Open SQL Query');
        console.log('Escape: Go to Home');
        
    });
    </script>

<?php 
    //require "floatool.php";
    //echo $eruda; 
?>

<script src="/js/sage-home-button.js" data-home="/dashboard.php"></script>

</body>
</html>
        
