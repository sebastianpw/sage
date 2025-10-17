<?php
namespace App\Gallery;

/**
 * SpawnsGalleryTexture - Gallery for texture library
 * Shows seamless textures and patterns
 */
class SpawnsGalleryTexture extends AbstractGallery
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
        return $this->spawnType['label'] ?? "Texture Library";
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
                'label' => 'Texture Name',
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
            $clauses[] = "type = 'texture'";
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
            'Texture Name' => 'name',
            'Description' => 'description',
            'Filename' => 'filename'
        ];
    }

    protected function getBaseQuery(): string
    {
        return "v_gallery_spawns";
    }

    protected function getGalleryUrl() {
	return 'upload_spawns.php?spawn_type=texture';
    }

    /**
     * Override renderFrame to add texture-specific styling
     */
    protected function renderFrame(array $row): string
    {
        // Add tiling preview option for textures
        $html = parent::renderFrame($row);
        
        // Inject tiling button after image (simple approach)
        $html = str_replace(
            '</div><!-- frame-item -->',
            '<button class="tile-preview" data-frame-id="' . $row['frame_id'] . '">Preview Tiled</button></div><!-- frame-item -->',
            $html
        );
        
        return $html;
    }
}
