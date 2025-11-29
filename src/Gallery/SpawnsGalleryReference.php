<?php
namespace App\Gallery;

/**
 * SpawnsGalleryReference - Gallery for reference images
 * Updated to use AbstractNuGallery and include spawn_type hidden field.
 */
class SpawnsGalleryReference extends AbstractNuGallery
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
            'Frame ID'       => 'frame_id',
            'Reference Name' => 'name',
            'Description'    => 'description',
            'Style'          => 'style'
        ];
    }

    protected function getGalleryUrl(): string
    {
        return 'upload_spawns.php?spawn_type=reference';
    }

    protected function getBaseQuery(): string
    {
        return "v_gallery_spawns";
    }

    /**
     * Inject the spawn_type hidden field so Ajax requests include the spawn type code.
     */
    protected function renderFilters(): void
    {
        if ($this->spawnType) {
            echo '<input type="hidden" name="spawn_type" value="' . htmlspecialchars($this->spawnType['code']) . '">';
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
