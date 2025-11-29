<?php
namespace App\Gallery;

/**
 * SpawnsGalleryProp - Gallery for props
 * Uses AbstractNuGallery and supports the new Ajax endpoint.
 */
class SpawnsGalleryProp extends AbstractNuGallery
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
        return $this->spawnType['label'] ?? "Props Gallery";
    }

    protected function getToggleButtonLeft(): int
    {
        return 150;
    }

    protected function getFiltersFromRequest(): array
    {
        return [
            'prop' => $_GET['prop'] ?? 'all'
        ];
    }

    protected function getFilterOptions(): array
    {
        return [
            'prop' => [
                'label'  => 'Prop Type',
                'values' => $this->fetchDistinct('prop'),
                'left'   => 0
            ]
        ];
    }

    protected function getWhereClause(): string
    {
        $clauses = [];

        if ($this->spawnType) {
            $clauses[] = "spawn_type_id = " . (int)$this->spawnType['id'];
        }

        if (($this->filters['prop'] ?? 'all') !== 'all') {
            $clauses[] = "prop='" . $this->mysqli->real_escape_string($this->filters['prop']) . "'";
        }

        return $clauses ? "WHERE " . implode(" AND ", $clauses) : "";
    }

    protected function getCaptionFields(): array
    {
        return [
            'Frame ID'     => 'frame_id',
            'Spawn ID'     => 'spawn_id',
            'File Name'    => 'filename',
            'Prop'         => 'prop',
            'Description'  => 'description'
        ];
    }

    protected function getGalleryUrl(): string
    {
        return 'upload_spawns.php?spawn_type=prop';
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
