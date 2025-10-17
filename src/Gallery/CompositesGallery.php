<?php
namespace App\Gallery;

require_once "AbstractGallery.php";

class CompositesGallery extends AbstractGallery {

    // ← include all filters here
    protected function getFiltersFromRequest(): array {
        return [
            'composite_name' => $_GET['composite_name'] ?? 'all',
            'style'          => $_GET['style'] ?? 'all', // ← optional style filter
        ];
    }

    protected function getFilterOptions(): array {
        $options = [];

        // Distinct composite names
        $options['composite_name'] = [
            'label'  => 'Names',
            'values' => $this->fetchDistinct('composite_name'),
            'left'   => 0
        ];

        // Distinct styles
        $options['style'] = [
            'label'  => 'Styles',
            'values' => $this->fetchDistinct('style'),
            'left'   => 55
        ];

        return $options;
    }

    protected function getWhereClause(): string {
        $clauses = [];
        foreach ($this->filters as $key => $val) {
            if ($val !== 'all') {
                $col = match($key) {
                    'composite_name' => 'composite_name',
                    'style'          => 'style',
                };
                $clauses[] = "$col='" . $this->mysqli->real_escape_string($val) . "'";
            }
        }
        return $clauses ? "WHERE " . implode(" AND ", $clauses) : '';
    }

    protected function getBaseQuery(): string {
        return "v_gallery_composites";
    }

    /*
    protected function renderItem(array $row): string {
        $filename = htmlspecialchars($row['filename']);
        $prompt   = htmlspecialchars($row['prompt']);
        $name     = htmlspecialchars($row['composite_name']);
        $style    = htmlspecialchars($row['style']); // optional display

        return <<<HTML
<div class="img-wrapper">
    <img src="{$filename}" alt="">
    <div class="caption">
        {$prompt}<br>
        <strong>Composite:</strong> {$name}<br>
        <strong>Style:</strong> {$style}
    </div>
</div>
HTML;
    }
    */

    protected function getCaptionFields(): array {
        return [
            'Entity ID'   => 'entity_id',
            'Frame ID'    => 'frame_id',
            'Composite'   => 'composite_name',
            'Style'       => 'style',
        ];
    }

    protected function getGalleryEntity(): string {
        return "composites";
    }

    protected function getGalleryTitle(): string {
        return "Composites Gallery";
    }

    protected function getToggleButtonLeft(): int {
        return 110; // sits just after the style filter
    }
}
