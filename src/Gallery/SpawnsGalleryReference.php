<?php
namespace App\Gallery;

/**
 * SpawnsGalleryReference - Gallery for reference images
 * Shows high-quality reference images for style matching
 */
class SpawnsGalleryReference extends AbstractGallery
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
        return $this->spawnType['label'] ?? "Reference Images Gallery";
    }

    protected function getToggleButtonLeft(): int
    {
        return 150;
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'name' => $_GET['name'] ?? 'all'
        ];
    }

    protected function getFilterOptions(): array
    {
        return [
            'name' => [
                'label' => 'Reference Name',
                'values' => $this->fetchDistinct('name'),
                'left' => 0
            ]
        ];
    }

    protected function getWhereClause(): string
    {
        $clauses = [];

        // Always filter by spawn type
        if ($this->spawnType) {
            $clauses[] = "spawn_type_id = " . (int)$this->spawnType['id'];
        } else {
            // Fallback: filter by type code
            $clauses[] = "type = 'reference'";
        }

        if (($this->filters['name'] ?? 'all') !== 'all') {
            $clauses[] = "name='" . $this->mysqli->real_escape_string($this->filters['name']) . "'";
        }

        return $clauses ? "WHERE " . implode(" AND ", $clauses) : "";
    }

    protected function getCaptionFields(): array
    {
        return [
            'Frame ID' => 'frame_id',
            'Reference Name' => 'name',
            'Description' => 'description',
            'Style' => 'style'
        ];
    }

    protected function getGalleryUrl() {
	return 'upload_spawns.php?spawn_type=reference';
    }

    protected function getBaseQuery(): string
    {
        return "v_gallery_spawns";
    }
}
