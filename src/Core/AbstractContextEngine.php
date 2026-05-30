<?php
// src/Core/AbstractContextEngine.php
namespace App\Core;

use PDO;

abstract class AbstractContextEngine {
    protected $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * @param int|null $contextId  ID of a Lore Document (optional)
     * @param string|null $customQuery Free text query from the Advanced Filter (optional)
     * @return array Array of ['id' => int, 'score' => float, 'matches' => array]
     */
    abstract public function getRankedItems(?int $contextId, ?string $customQuery = null): array;
}
