<?php
namespace App\Gallery;

/**
 * Updated SpawnsGallery - Default gallery with type filtering
 */
class SpawnsGalleryDefault extends AbstractGallery
{
    private ?array $spawnType = null;

    public function __construct(?array $spawnType = null)
    {
        $this->spawnType = $spawnType;
        parent::__construct();
    }

    protected function getGalleryEntity(): string
    {
        return "spawns";
    }

    protected function getGalleryTitle(): string
    {
        return $this->spawnType['label'] ?? "Spawns Gallery";
    }

    protected function getToggleButtonLeft(): int
    {
        return 150;
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'type' => $_GET['type'] ?? 'all'
        ];
    }

    protected function getFilterOptions(): array
    {
        return [
            'type' => [
                'label' => 'Type',
                'values' => $this->fetchDistinct('type'),
                'left' => 0
            ]
        ];
    }

    protected function getWhereClause(): string
    {
        $clauses = [];
        
        // If spawn type is set, filter by it
        if ($this->spawnType) {
            $clauses[] = "spawn_type_id = " . (int)$this->spawnType['id'];
        }
        
        if (($this->filters['type'] ?? 'all') !== 'all') {
            $clauses[] = "type='" . $this->mysqli->real_escape_string($this->filters['type']) . "'";
        }
        
        return $clauses ? "WHERE " . implode(" AND ", $clauses) : "";
    }

    protected function getCaptionFields(): array
    {
        return [
            'Frame ID' => 'frame_id',
            'Spawn ID' => 'spawn_id',
            'File Name' => 'filename',
            'Name' => 'name',
            'Description' => 'description',
            'Type' => 'type'
        ];
    }

    protected function getGalleryUrl() {
	return 'upload_spawns.php?spawn_type=default';
    }

    protected function getBaseQuery(): string
    {
        return "v_gallery_spawns";
    }
}
