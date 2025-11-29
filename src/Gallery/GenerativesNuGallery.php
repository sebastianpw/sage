<?php
// src/Gallery/GenerativesNuGallery.php
namespace App\Gallery;
class GenerativesNuGallery extends AbstractNuGallery {
    protected function getGalleryEntity(): string {
        return "generatives";
    }
    protected function getGalleryTitle(): string {
        return "Generatives Gallery";
    }
    protected function getToggleButtonLeft(): int {
        return 150;
    }
    protected function getFiltersFromRequest(): array {
        return [
            'name'  => $_GET['name'] ?? 'all',
            'style' => $_GET['style'] ?? 'all'
        ];
    }
    protected function getFilterOptions(): array {
        return [
            'name' => [
                'label'  => 'Generatives',
                'values' => $this->fetchDistinct('name'),
                'left'   => 0
            ],
            'style' => [
                'label'  => 'Styles',
                'values' => $this->fetchDistinct('style'),
                'left'   => 75
            ]
        ];
    }
    protected function getWhereClause(): string {
        $clauses = [];
        if (($this->filters['name'] ?? 'all') !== 'all') {
            $clauses[] = "name='" . $this->mysqli->real_escape_string($this->filters['name']) . "'";
        }
        if (($this->filters['style'] ?? 'all') !== 'all') {
            $clauses[] = "style='" . $this->mysqli->real_escape_string($this->filters['style']) . "'";
        }
        return $clauses ? "WHERE " . implode(" AND ", $clauses) : "";
    }
    protected function getCaptionFields(): array {
        return [
            'Entity ID'   => 'entity_id',
            'Frame ID'    => 'frame_id',
            'Name'        => 'name',
            'Description' => 'description',
            'Style'       => 'style'
        ];
    }
    protected function getBaseQuery(): string {
        return "v_gallery_generatives";
    }
}
