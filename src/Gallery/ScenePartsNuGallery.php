<?php
// src/Gallery/ScenePartsNuGallery.php
namespace App\Gallery;
class ScenePartsNuGallery extends AbstractNuGallery {
    protected function getFiltersFromRequest(): array {
        return [
            'character' => $_GET['character'] ?? 'all',
            'anima'     => $_GET['anima'] ?? 'all',
            'style'     => $_GET['style'] ?? 'all',
        ];
    }
    protected function getFilterOptions(): array {
        return [
            'character' => [
                'label'  => 'Characters',
                'values' => $this->fetchDistinct('characters'),
                'left'   => 0
            ],
            'anima' => [
                'label'  => 'Animas',
                'values' => $this->fetchDistinct('animas'),
                'left'   => 75
            ],
            'style' => [
                'label'  => 'Styles',
                'values' => $this->fetchDistinct('style'),
                'left'   => 150
            ],
        ];
    }
    protected function getWhereClause(): string {
        $clauses = [];
        if ($this->filters['character'] !== 'all') {
            $clauses[] = "characters LIKE '%" . $this->mysqli->real_escape_string($this->filters['character']) . "%'";
        }
        if ($this->filters['anima'] !== 'all') {
            $clauses[] = "animas LIKE '%" . $this->mysqli->real_escape_string($this->filters['anima']) . "%'";
        }
        if ($this->filters['style'] !== 'all') {
            $clauses[] = "style LIKE '%" . $this->mysqli->real_escape_string($this->filters['style']) . "%'";
        }
        return $clauses ? "WHERE " . implode(" AND ", $clauses) : '';
    }
    protected function getBaseQuery(): string {
        return "v_gallery_scene_parts";
    }
    protected function getCaptionFields(): array {
        return [
            'Scene'       => 'scene_part_title',
            'Characters'  => 'characters',
            'Animas'      => 'animas',
            'Artifacts'   => 'artifacts',
            'Backgrounds' => 'backgrounds',
            'Style'       => 'style',
        ];
    }
    protected function getGalleryEntity(): string {
        return "scene_parts";
    }
    protected function getGalleryTitle(): string {
        return "Scene Parts Gallery";
    }
    protected function getToggleButtonLeft(): int {
        return 225;
    }
}
