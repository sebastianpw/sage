<?php
namespace App\Gallery;

/**
 * SpawnsGalleryLocation - Gallery for location-based spawns
 * Uses AbstractNuGallery and supports the new Ajax endpoint.
 */
class SpawnsGalleryLocation extends AbstractNuGallery
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
        return $this->spawnType['label'] ?? "Locations Gallery";
    }

    protected function getToggleButtonLeft(): int
    {
        return 150;
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'location' => $_GET['location'] ?? 'all'
        ];
    }

    protected function getFilterOptions(): array
    {
        return [
            'location' => [
                'label' => 'Location',
                'values' => $this->fetchDistinct('location'),
                'left'  => 0
            ]
        ];
    }

    protected function getWhereClause(): string
    {
        $clauses = [];

        if ($this->spawnType) {
            $clauses[] = "spawn_type_id = " . (int)$this->spawnType['id'];
        }

        if (($this->filters['location'] ?? 'all') !== 'all') {
            $clauses[] = "location='" . $this->mysqli->real_escape_string($this->filters['location']) . "'";
        }

        return $clauses ? "WHERE " . implode(" AND ", $clauses) : "";
    }

    protected function getCaptionFields(): array
    {
        return [
            'Frame ID'     => 'frame_id',
            'Spawn ID'     => 'spawn_id',
            'File Name'    => 'filename',
            'Location'     => 'location',
            'Description'  => 'description'
        ];
    }

    protected function getGalleryUrl(): string
    {
        return 'upload_spawns.php?spawn_type=location';
    }

    protected function getBaseQuery(): string
    {
        return "v_gallery_spawns";
    }

    protected function renderFilters(): void
    {
        if ($this->spawnType) {
            echo '<input type="hidden" name="spawn_type" value="' . 
                 htmlspecialchars($this->spawnType['code']) . '">';
        }

        parent::renderFilters();
    }
    
    
   
    /**
     * Override the AJAX endpoint to use the dedicated spawns handler.
     */
    protected function getAjaxEndpoint(): string
    {
        return '/ajax_spawns_gallery.php';
    }
    
    
    
}
