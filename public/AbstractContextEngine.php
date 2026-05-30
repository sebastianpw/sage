<?php

/**
 * Abstract Base Class for Context Scoring.
 * Future "Orchestration UIs" can simply implement a new version of this.
 */
abstract class AbstractContextEngine {
    protected $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Must return an array of Sketch IDs sorted by relevance.
     * Also returns metadata (like 'matches') for the UI.
     */
    abstract public function getRankedItems(?int $contextId): array;
}
