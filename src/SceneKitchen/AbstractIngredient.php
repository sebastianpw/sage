<?php

namespace App\SceneKitchen;

abstract class AbstractIngredient
{
    protected ?int $id = null;
    protected array $data = [];

    public function __construct(?int $id = null, array $data = [])
    {
        $this->id = $id;
        $this->data = $data;
    }

    /**
     * Unique type identifier for DB storage and UI selection
     */
    abstract public static function getType(): string;

    /**
     * Display name for the UI
     */
    abstract public function getLabel(): string;

    /**
     * The actual text this ingredient contributes to the final prompt
     */
    abstract public function getPromptSegment(): string;

    /**
     * Icon for the UI
     */
    abstract public function getIcon(): string;

    /**
     * Get snapshot data for meta storage
     */
    public function getSnapshotData(): array
    {
        return $this->data;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * Fetch available ingredients of this type from DB
     * @param \PDO $pdo
     * @param array $filters
     * @return AbstractIngredient[]
     */
    abstract public static function fetchAvailable(\PDO $pdo, array $filters = []): array;
}
