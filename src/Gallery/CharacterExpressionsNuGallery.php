<?php
// src/Gallery/CharacterExpressionsNuGallery.php
namespace App\Gallery;
class CharacterExpressionsNuGallery extends AbstractNuGallery {
    protected function getFiltersFromRequest(): array {
        return [
            'character' => $_GET['character'] ?? 'all',
            'expression'      => $_GET['expression'] ?? 'all',
            'angle'     => $_GET['angle'] ?? 'all',
            'style'     => $_GET['style'] ?? 'all',
        ];
    }
    protected function getFilterOptions(): array {
        return [
            'character' => [
                'label'  => 'Characters',
                'values' => $this->fetchDistinct('character_name'),
                'left'   => 0
            ],
            'expression' => [
                'label'  => 'Expressions',
                'values' => $this->fetchDistinct('expression_name'),
                'left'   => 55
            ],
            'angle' => [
                'label'  => 'Angles',
                'values' => $this->fetchDistinct('angle_name'),
                'left'   => 110
            ],
            'style' => [
                'label'  => 'Styles',
                'values' => $this->fetchDistinct('style'),
                'left'   => 165
            ],
        ];
    }
    protected function getWhereClause(): string {
        $clauses = [];
        if ($this->filters['character'] !== 'all') {
            $clauses[] = "character_name LIKE '%" . $this->mysqli->real_escape_string($this->filters['character']) . "%'";
        }
        if ($this->filters['expression'] !== 'all') {
            $clauses[] = "expression_name LIKE '%" . $this->mysqli->real_escape_string($this->filters['expression']) . "%'";
        }
        if ($this->filters['angle'] !== 'all') {
            $clauses[] = "angle_name LIKE '%" . $this->mysqli->real_escape_string($this->filters['angle']) . "%'";
        }
        if ($this->filters['style'] !== 'all') {
            $clauses[] = "style LIKE '%" . $this->mysqli->real_escape_string($this->filters['style']) . "%'";
        }
        return $clauses ? "WHERE " . implode(" AND ", $clauses) : '';
    }
    protected function getBaseQuery(): string {
        return "v_gallery_character_expressions";
    }
    protected function getCaptionFields(): array {
        return [
            'Frame ID'          => 'frame_id',
            'Character Expression ID' => 'character_expression_id',
            'File Name'         => 'filename',
            'Character'         => 'character_name',
            'Expression'              => 'expression_name',
            'Angle'             => 'angle_name',
            'Style'             => 'style',
        ];
    }
    protected function getGalleryEntity(): string {
        return "character_expressions";
    }
    protected function getGalleryTitle(): string {
        return "Character Expressions Gallery";
    }
    protected function getToggleButtonLeft(): int {
        return 220;
    }
}
