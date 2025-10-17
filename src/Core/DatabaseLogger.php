<?php

namespace App\Core;

use PDO;
use \App\Core\SpwBase;

class DatabaseLogger extends AbstractLogger
{
	private PDO $pdo;
	private ?SpwBase $spw = null;
    private string $table;

    public function __construct(string $table = 'logs')
    {
	    $this->spw = SpwBase::getInstance();
        $this->pdo = $spw->getPDO(); // gets PDO from SpwBase
        $this->table = $table;
        $this->initTable();
    }


    private function initTable(): void
{
    $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `level` VARCHAR(10) NOT NULL,
        `message` JSON NOT NULL,
        `log_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";

    $this->spw->getPDO()->exec($sql);
    }




    public function log(string $level, array $data): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO `{$this->table}` (`level`, `message`) VALUES (:level, :message)"
        );
        $stmt->execute([
            ':level'   => $level,
            ':message' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }
}


