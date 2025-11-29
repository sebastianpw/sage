<?php

namespace App\Core;

use App\Session\AbstractSessionManager;
use App\Session\CliSessionManager;
use App\Core\ChatManager;
use App\Core\AIProvider;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Dotenv\Dotenv;
use App\Core\DbalMysqliAdapter;
use Doctrine\DBAL\Connection;
use App\Core\FileLogger;
use App\Core\SchedulerFileLogger;
use App\Core\DatabaseLogger;
use PDO;
use InvalidArgumentException;

class SpwBase extends AbstractProjectBase
{

    public const CDN_USAGE = false;
    public const VERSION = '0.1.1';
    
    public const ALLOW_PDO_ROOT = true;

    public static $JQUERY_LOADED = false;

    private ?AbstractSessionManager $sessionManager = null;
    private ?EntityManagerInterface $em = null;
    private ?ChatManager $chatManager = null;
    private ?AIProvider $aiProvider = null;
    private array $dbParams = [];
    private ?\mysqli $mysqli = null;
    private ?DbalMysqliAdapter $dbalAdapter = null;
    private ?FileLogger $fileLogger = null;
    private ?SchedulerFileLogger $schedulerFileLogger = null;
    private ?DatabaseLogger $databaseLogger = null;

    private ?PDO $pdo = null;

    // SYS (shared) DB
    private array $dbParamsSys = [];
    private array $dbParamsRoot = [];
    private ?\mysqli $mysqliSys = null;
    private ?DbalMysqliAdapter $dbalAdapterSys = null;
    private ?PDO $pdoSys = null;
    private ?PDO $pdoRoot = null;
    
    
   // WordNet DB (separate)
    private array $dbParamsWn = [];
    private ?\mysqli $mysqliWn = null;
    private ?PDO $pdoWn = null;
    private ?EntityManagerInterface $emWn = null;
    private ?DbalMysqliAdapter $dbalAdapterWn = null;
    

    private ?EntityManagerInterface $emSys = null;

    private string $framesDir;
    private string $framesDirRel;

    private string $appLogLevel = 'INFO'; // default

    public function setAppLogLevel(string $level): void
    {
        $allowed = ['DEBUG', 'INFO', 'WARNING', 'ERROR'];
        $level = strtoupper($level);

        if (!in_array($level, $allowed)) {
            throw new InvalidArgumentException("Invalid log level: $level");
        }

        $this->appLogLevel = $level;
    }

    public function getAppLogLevel(): string
    {
        return $this->appLogLevel;
    }

    public function getDbName(): string
    {
        return $this->dbParams['dbname'];
    }

    public function getJquery(): string
    {
        $result = "";

        if (!self::$JQUERY_LOADED) {
            if (self::CDN_USAGE) {
                // jQuery via CDN
                $result .= '<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>';
                // jQuery UI via CDN
                $result .= '<script src="https://code.jquery.com/ui/1.10.3/jquery-ui.min.js"></script>';
                // jQuery UI Touch Punch via CDN
                $result .= '<script src="https://cdnjs.cloudflare.com/ajax/libs/jqueryui-touch-punch/0.2.3/jquery.ui.touch-punch.min.js"></script>';
            } else {
                // jQuery via local copy
                $result .= '<script src="/vendor/jquery/jquery-3.7.0.min.js"></script>';
                // jQuery UI via local copy
                $result .= '<script src="/vendor/jquery-ui/jquery-ui.min.js"></script>';
                // jQuery UI Touch Punch via local copy
                $result .= '<script src="/vendor/jquery-ui/jquery.ui.touch-punch.min.js"></script>';
            }

            self::$JQUERY_LOADED = true;
        }

        return $result;
    }

    public function getPDO(): PDO
    {
        if ($this->pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;port=%d;charset=%s',
                $this->dbParams['host'],
                $this->dbParams['dbname'],
                $this->dbParams['port'] ?? 3306,
                $this->dbParams['charset'] ?? 'utf8mb4'
            );
            $this->pdo = new PDO($dsn, $this->dbParams['user'], $this->dbParams['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	    ]);
            $this->pdo->exec("SET time_zone = '+00:00'");
        }
        return $this->pdo;
    }

    public function getSysPDO(): PDO
    {
        if ($this->pdoSys === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;port=%d;charset=%s',
                $this->dbParamsSys['host'],
                $this->dbParamsSys['dbname'],
                $this->dbParamsSys['port'] ?? 3306,
                $this->dbParamsSys['charset'] ?? 'utf8mb4'
            );
            $this->pdoSys = new PDO($dsn, $this->dbParamsSys['user'], $this->dbParamsSys['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
	    ]);
            $this->pdoSys->exec("SET time_zone = '+00:00'");
        }
        return $this->pdoSys;
    }
    
    
    public function getRootPDO(): ?PDO
    {
        // Check if root PDO is allowed via class constant
        if (!defined('self::ALLOW_PDO_ROOT') || !self::ALLOW_PDO_ROOT) {
            return null;
        }
        
        // Only initialize if root params are configured
        if (empty($this->dbParamsRoot)) {
            return null;
        }
        
        if ($this->pdoRoot === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;charset=%s',
                $this->dbParamsRoot['host'],
                $this->dbParamsRoot['port'] ?? 3306,
                $this->dbParamsRoot['charset'] ?? 'utf8mb4'
            );
            
            // Optionally include dbname if specified (like your 'mysql' database)
            if (!empty($this->dbParamsRoot['dbname'])) {
                $dsn .= ';dbname=' . $this->dbParamsRoot['dbname'];
            }
            
            try {
                $this->pdoRoot = new PDO(
                    $dsn, 
                    $this->dbParamsRoot['user'], 
                    $this->dbParamsRoot['password'], 
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    ]
                );
                $this->pdoRoot->exec("SET time_zone = '+00:00'");
            } catch (PDOException $e) {
                error_log("Failed to create root PDO connection: " . $e->getMessage());
                return null;
            }
        }
        
        return $this->pdoRoot;
    }
    
    
    /**
     * Get PDO connection for a specific database
     */
    public function getPDOForDatabase(string $dbName): PDO
    {
        // TODO: update .env.local for dbName specific credentials
        $host = $this->dbParams['host'];
        $user = $this->dbParams['user'];
        $pass = $this->dbParams['password'];
        $port = $this->dbParams['port'] ?? 3306;
        $charset = $this->dbParamsSys['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};dbname={$dbName};port={$port};charset={$charset}";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    }





    public function getDbalAdapter(): DbalMysqliAdapter
    {
        if ($this->dbalAdapter === null) {
            $conn = $this->getEntityManager()->getConnection(); // DBAL Connection
            $this->dbalAdapter = new DbalMysqliAdapter($conn);
        }
        return $this->dbalAdapter;
    }

    public function getSysDbalAdapter(): DbalMysqliAdapter
    {
        if ($this->dbalAdapterSys === null) {
            $conn = $this->getSysEntityManager()->getConnection(); // DBAL Connection for sys DB
            $this->dbalAdapterSys = new DbalMysqliAdapter($conn);
        }
        return $this->dbalAdapterSys;
    }

    public function getMysqli(): \mysqli
    {
        if ($this->mysqli === null) {
            $host = $this->dbParams['host'];
            $user = $this->dbParams['user'];
            $pass = $this->dbParams['password'];
            $dbname = $this->dbParams['dbname'];
            $port = $this->dbParams['port'] ?? 3306;

            $this->mysqli = new \mysqli($host, $user, $pass, $dbname, $port);

            if ($this->mysqli->connect_error) {
                die("MySQLi connection failed: " . $this->mysqli->connect_error);
            }
        }

        return $this->mysqli;
    }

    public function getSysMysqli(): \mysqli
    {
        if ($this->mysqliSys === null) {
            $host = $this->dbParamsSys['host'];
            $user = $this->dbParamsSys['user'];
            $pass = $this->dbParamsSys['password'];
            $dbname = $this->dbParamsSys['dbname'];
            $port = $this->dbParamsSys['port'] ?? 3306;

            $this->mysqliSys = new \mysqli($host, $user, $pass, $dbname, $port);

            if ($this->mysqliSys->connect_error) {
                die("MySQLi SYS connection failed: " . $this->mysqliSys->connect_error);
            }
        }

        return $this->mysqliSys;
    }
    
    
    /**
     * Get PDO connection for the WordNet database
     *
     * Usage: $pdoWN = $spw->getWNPDO();
     */
    public function getWNPDO(): PDO
    {
        if ($this->pdoWn === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;port=%d;charset=%s',
                $this->dbParamsWn['host'],
                $this->dbParamsWn['dbname'],
                $this->dbParamsWn['port'] ?? 3306,
                $this->dbParamsWn['charset'] ?? 'utf8mb4'
            );
            $this->pdoWn = new PDO($dsn, $this->dbParamsWn['user'], $this->dbParamsWn['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            $this->pdoWn->exec("SET time_zone = '+00:00'");
        }
        return $this->pdoWn;
    }

    /**
     * Get mysqli connection for the WordNet database
     */
    public function getWnMysqli(): \mysqli
    {
        if ($this->mysqliWn === null) {
            $host = $this->dbParamsWn['host'];
            $user = $this->dbParamsWn['user'];
            $pass = $this->dbParamsWn['password'];
            $dbname = $this->dbParamsWn['dbname'];
            $port = $this->dbParamsWn['port'] ?? 3306;

            $this->mysqliWn = new \mysqli($host, $user, $pass, $dbname, $port);

            if ($this->mysqliWn->connect_error) {
                die("MySQLi WordNet connection failed: " . $this->mysqliWn->connect_error);
            }
        }
        return $this->mysqliWn;
    }

    /**
     * Get a DBAL adapter for WordNet (if you need DBAL operations)
     */
    public function getWnDbalAdapter(): DbalMysqliAdapter
    {
        if ($this->dbalAdapterWn === null) {
            $conn = $this->getWnEntityManager()->getConnection();
            $this->dbalAdapterWn = new DbalMysqliAdapter($conn);
        }
        return $this->dbalAdapterWn;
    }

    /**
     * Get EntityManager for WordNet DB (optional; will create one if used).
     * This expects mapping/entity classes under src/Entity/WordNet (adjust as needed).
     */
    public function getWnEntityManager(): EntityManagerInterface
    {
        if ($this->emWn === null) {
            $config = ORMSetup::createAttributeMetadataConfiguration(
                [$this->getProjectPath() . '/src/Entity/WordNet'],
                true // dev mode
            );

            $connectionParams = $this->dbParamsWn;
            if (!empty($this->dbParamsWn['serverVersion'])) {
                $connectionParams['serverVersion'] = $this->dbParamsWn['serverVersion'];
            }

            $connection = DriverManager::getConnection($connectionParams, $config);
            $this->emWn = new EntityManager($connection, $config);
        }
        return $this->emWn;
    }
    

    protected function __construct()
    {
	date_default_timezone_set('UTC');

        // Load root paths
        require $this->getProjectPath() . '/public/load_root.php'; // must define PROJECT_ROOT and FRAMES_ROOT

        // Directories
        $this->framesDir = FRAMES_ROOT . '/'; // absolute, safe
        $this->framesDirRel = str_replace(PROJECT_ROOT . '/public/', '', FRAMES_ROOT); // dynamic relative

        // Ensure folder exists
        if (!is_dir($this->framesDir)) {
            mkdir($this->framesDir, 0777, true);
        }

        $this->loadEnv();
    }

    public function getPublicPath(): string
    {
        return $this->getProjectPath() . '/public';
    }

    public function getFramesDir(): string
    {
        return $this->framesDir;
    }

    public function getFramesDirRel(): string
    {
        return $this->framesDirRel;
    }

    public function getProjectPath(): string
    {
        return PROJECT_ROOT;
    }
    
    
    
    
    private function loadEnv(): void
    {
        $dotenv = new Dotenv();
        $envFile = $this->getProjectPath() . '/.env.local';
    
        if (!file_exists($envFile)) {
            throw new \RuntimeException(".env.local missing — cannot determine database connection!");
        }
    
        $dotenv->load($envFile);
    
        if (empty($_ENV['DATABASE_URL'])) {
            throw new \RuntimeException("DATABASE_URL missing in .env.local — cannot connect to database!");
        }
    
        $url = parse_url($_ENV['DATABASE_URL']);
    
        $this->dbParams = [
            'dbname'   => isset($url['path']) ? ltrim($url['path'], '/') : '',
            'user'     => $url['user'] ?? 'root',
            'password' => $url['pass'] ?? '',
            'host'     => $url['host'] ?? '127.0.0.1',
            'port'     => $url['port'] ?? 3306,
            'driver'   => 'pdo_mysql',
            'charset'  => 'utf8mb4',
        ];
    
        // pick up query params (serverVersion, charset) if present
        if (!empty($url['query'])) {
            parse_str($url['query'], $q);
            if (!empty($q['charset'])) {
                $this->dbParams['charset'] = $q['charset'];
            }
            if (!empty($q['serverVersion'])) {
                $this->dbParams['serverVersion'] = $q['serverVersion'];
            }
        }
    
        // --- SYS DB: allow explicit DATABASE_SYS_URL, otherwise use project DB ---
        if (!empty($_ENV['DATABASE_SYS_URL'])) {
            $urlSys = parse_url($_ENV['DATABASE_SYS_URL']);
        } else {
            $urlSys = $url; // fallback to same connection for local/dev convenience
        }
    
        $this->dbParamsSys = [
            'dbname'   => isset($urlSys['path']) ? ltrim($urlSys['path'], '/') : $this->dbParams['dbname'],
            'user'     => $urlSys['user'] ?? $this->dbParams['user'],
            'password' => $urlSys['pass'] ?? $this->dbParams['password'],
            'host'     => $urlSys['host'] ?? $this->dbParams['host'],
            'port'     => $urlSys['port'] ?? $this->dbParams['port'],
            'driver'   => 'pdo_mysql',
            'charset'  => $this->dbParams['charset'] ?? 'utf8mb4',
        ];
    
        if (!empty($urlSys['query'])) {
            parse_str($urlSys['query'], $qsys);
            if (!empty($qsys['charset'])) {
                $this->dbParamsSys['charset'] = $qsys['charset'];
            }
            if (!empty($qsys['serverVersion'])) {
                $this->dbParamsSys['serverVersion'] = $qsys['serverVersion'];
            }
        }
        
        // --- WORDNET DB: allow explicit DATABASE_WORDNET_URL, otherwise fall back to project DB (for convenience) ---
        if (!empty($_ENV['DATABASE_WORDNET_URL'])) {
            $urlWn = parse_url($_ENV['DATABASE_WORDNET_URL']);
        } else {
            // fallback to project DB connection so existing setups still work
            $urlWn = $url;
        }

        $this->dbParamsWn = [
            'dbname'   => isset($urlWn['path']) ? ltrim($urlWn['path'], '/') : $this->dbParams['dbname'],
            'user'     => $urlWn['user'] ?? $this->dbParams['user'],
            'password' => $urlWn['pass'] ?? $this->dbParams['password'],
            'host'     => $urlWn['host'] ?? $this->dbParams['host'],
            'port'     => $urlWn['port'] ?? $this->dbParams['port'],
            'driver'   => 'pdo_mysql',
            'charset'  => $this->dbParams['charset'] ?? 'utf8mb4',
        ];

        if (!empty($urlWn['query'])) {
            parse_str($urlWn['query'], $qwn);
            if (!empty($qwn['charset'])) {
                $this->dbParamsWn['charset'] = $qwn['charset'];
            }
            if (!empty($qwn['serverVersion'])) {
                $this->dbParamsWn['serverVersion'] = $qwn['serverVersion'];
            }
        }
    
        // --- ROOT DB: allow explicit DATABASE_ROOT_URL for multi-database management ---
        if (!empty($_ENV['DATABASE_ROOT_URL'])) {
            $urlRoot = parse_url($_ENV['DATABASE_ROOT_URL']);
            
            $this->dbParamsRoot = [
                'dbname'   => isset($urlRoot['path']) ? ltrim($urlRoot['path'], '/') : '', // Optional, can be empty
                'user'     => $urlRoot['user'] ?? 'root',
                'password' => $urlRoot['pass'] ?? '',
                'host'     => $urlRoot['host'] ?? '127.0.0.1',
                'port'     => $urlRoot['port'] ?? 3306,
                'driver'   => 'pdo_mysql',
                'charset'  => 'utf8mb4',
            ];
    
            if (!empty($urlRoot['query'])) {
                parse_str($urlRoot['query'], $qroot);
                if (!empty($qroot['charset'])) {
                    $this->dbParamsRoot['charset'] = $qroot['charset'];
                }
                if (!empty($qroot['serverVersion'])) {
                    $this->dbParamsRoot['serverVersion'] = $qroot['serverVersion'];
                }
            }
        } else {
            $this->dbParamsRoot = []; // Empty array if not configured
        }
    }
    
    
    /*
    private function loadEnv(): void
    {
        $dotenv = new Dotenv();
        $envFile = $this->getProjectPath() . '/.env.local';

        if (!file_exists($envFile)) {
            throw new \RuntimeException(".env.local missing — cannot determine database connection!");
        }

        $dotenv->load($envFile);

        if (empty($_ENV['DATABASE_URL'])) {
            throw new \RuntimeException("DATABASE_URL missing in .env.local — cannot connect to database!");
        }

        $url = parse_url($_ENV['DATABASE_URL']);

        $this->dbParams = [
            'dbname'   => isset($url['path']) ? ltrim($url['path'], '/') : '',
            'user'     => $url['user'] ?? 'root',
            'password' => $url['pass'] ?? '',
            'host'     => $url['host'] ?? '127.0.0.1',
            'port'     => $url['port'] ?? 3306,
            'driver'   => 'pdo_mysql',
            'charset'  => 'utf8mb4',
        ];

        // pick up query params (serverVersion, charset) if present
        if (!empty($url['query'])) {
            parse_str($url['query'], $q);
            if (!empty($q['charset'])) {
                $this->dbParams['charset'] = $q['charset'];
            }
            if (!empty($q['serverVersion'])) {
                $this->dbParams['serverVersion'] = $q['serverVersion'];
            }
        }

        // --- SYS DB: allow explicit DATABASE_SYS_URL, otherwise use project DB ---
        if (!empty($_ENV['DATABASE_SYS_URL'])) {
            $urlSys = parse_url($_ENV['DATABASE_SYS_URL']);
        } else {
            $urlSys = $url; // fallback to same connection for local/dev convenience
        }

        $this->dbParamsSys = [
            'dbname'   => isset($urlSys['path']) ? ltrim($urlSys['path'], '/') : $this->dbParams['dbname'],
            'user'     => $urlSys['user'] ?? $this->dbParams['user'],
            'password' => $urlSys['pass'] ?? $this->dbParams['password'],
            'host'     => $urlSys['host'] ?? $this->dbParams['host'],
            'port'     => $urlSys['port'] ?? $this->dbParams['port'],
            'driver'   => 'pdo_mysql',
            'charset'  => $this->dbParams['charset'] ?? 'utf8mb4',
        ];

        if (!empty($urlSys['query'])) {
            parse_str($urlSys['query'], $qsys);
            if (!empty($qsys['charset'])) {
                $this->dbParamsSys['charset'] = $qsys['charset'];
            }
            if (!empty($qsys['serverVersion'])) {
                $this->dbParamsSys['serverVersion'] = $qsys['serverVersion'];
            }
        }
    }
    */

    public function getSessionManager(): AbstractSessionManager
    {
        return $this->sessionManager ??= new CliSessionManager($this->getProjectPath() . '/session');
    }

    public function getEntityManager(): EntityManagerInterface
    {
        if ($this->em === null) {
            $config = ORMSetup::createAttributeMetadataConfiguration(
                [$this->getProjectPath() . '/src/Entity'],
                true // dev mode
            );

            // allow serverVersion / additional driver hints if present
            $connectionParams = $this->dbParams;
            if (!empty($this->dbParams['serverVersion'])) {
                $connectionParams['serverVersion'] = $this->dbParams['serverVersion'];
            }

            $connection = DriverManager::getConnection($connectionParams, $config);
            $this->em = new EntityManager($connection, $config);
        }

        return $this->em;
    }

    public function getSysEntityManager(): EntityManagerInterface
    {
        if ($this->emSys === null) {
            // metadata for sys entities (keep separate from project entities)
            $config = ORMSetup::createAttributeMetadataConfiguration(
                [$this->getProjectPath() . '/src/Entity/Sys'],
                true // dev mode
            );

            // allow serverVersion / additional driver hints if present
            $connectionParams = $this->dbParamsSys;
            if (!empty($this->dbParamsSys['serverVersion'])) {
                $connectionParams['serverVersion'] = $this->dbParamsSys['serverVersion'];
            }

            $connection = DriverManager::getConnection($connectionParams, $config);
            $this->emSys = new EntityManager($connection, $config);
        }

        return $this->emSys;
    }

    /**
     * Get the centralized AIProvider instance
     */
    public function getAIProvider(): AIProvider
    {
        if ($this->aiProvider === null) {
            $this->aiProvider = new AIProvider($this->getFileLogger());
        }
        return $this->aiProvider;
    }

    /**
     * Get ChatManager with AIProvider dependency injection
     */
    public function getChatManager(): ChatManager
    {
        if ($this->chatManager === null) {
            $this->chatManager = new ChatManager(
                $this->getEntityManager(),
                $this->getAIProvider()
            );
        }
        return $this->chatManager;
    }

    public function renderLayout(string $content, string $title = "", string $layoutPath = ""): void
    {
        $layoutPath = $layoutPath ?: $this->getProjectPath() . '/templates/layout.php';

        if (file_exists($layoutPath)) {
            $pageTitle = $title;
            $pageContent = $content;

            include $layoutPath;
        } else {
            echo "Layout file not found!";
        }
    }

    // --- Logger accessors ---
    public function getLogger(?string $logDir = null): FileLogger
    {
        return $this->getFileLogger($logDir);
    }

    public function getFileLogger(?string $logDir = null): FileLogger
    {
        if ($this->fileLogger === null) {
            $this->fileLogger = new FileLogger($logDir); // defaults to PROJECT_ROOT/logs
        }
        return $this->fileLogger;
    }

    public function getSchedulerFileLogger(?string $logDir = null): SchedulerFileLogger
    {
        if ($this->schedulerFileLogger === null) {
            $this->schedulerFileLogger = new SchedulerFileLogger($logDir); // defaults to PROJECT_ROOT/logs
        }
        return $this->schedulerFileLogger;
    }

    public function getDatabaseLogger(): DatabaseLogger
    {
        if ($this->databaseLogger === null) {
            $this->databaseLogger = new DatabaseLogger();
        }
        return $this->databaseLogger;
    }

    // Sys helpers
    public function getSysDbName(): string
    {
        return $this->dbParamsSys['dbname'];
    }
}
