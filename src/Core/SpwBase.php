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
    private ?\mysqli $mysqliSys = null;
    private ?DbalMysqliAdapter $dbalAdapterSys = null;
    private ?PDO $pdoSys = null;
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
    }

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
