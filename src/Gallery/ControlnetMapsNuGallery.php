<?php
// src/Gallery/ControlnetMapsNuGallery.php
namespace App\Gallery;
class ControlnetMapsNuGallery extends AbstractNuGallery {
    protected function getFiltersFromRequest(): array {
        return [
            'style' => $_GET['style'] ?? 'all',
        ];
    }
    protected function getFilterOptions(): array {
        return [
            'style' => [
                'label'  => 'Styles',
                'values' => $this->fetchDistinct('style'),
                'left'   => 0
            ],
        ];
    }
    protected function getWhereClause(): string {
        $clauses = [];
        if ($this->filters['style'] !== 'all') {
            $clauses[] = "style='" . $this->mysqli->real_escape_string($this->filters['style']) . "'";
        }
        return $clauses ? "WHERE " . implode(" AND ", $clauses) : '';
    }
    protected function getBaseQuery(): string {
        return "v_gallery_controlnet_maps";
    }
    protected function getCaptionFields(): array {
        return [
            'Entity ID' => 'entity_id',
            'Frame ID'  => 'frame_id',
            'Map Name'  => 'map_name',
            'Style'     => 'style',
        ];
    }
    protected function getGalleryEntity(): string {
        return "controlnet_maps";
    }
    protected function getGalleryTitle(): string {
        return "ControlNet Maps Gallery";
    }
    protected function getToggleButtonLeft(): int {
        return 75;
    }
}
