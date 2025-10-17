<?php
namespace App\Gallery;

require_once "AbstractGallery.php";

class AnimasGallery extends AbstractGallery {

    protected function getFiltersFromRequest(): array {
        return [
            'character_id' => $_GET['character_id'] ?? 'all',
            'anima_name'   => $_GET['anima_name'] ?? 'all',
            'style'        => $_GET['style'] ?? 'all',
        ];
    }

    protected function getFilterOptions(): array {
        $options = [];

        // Character filter
        $char_res = $this->mysqli->query("SELECT DISTINCT character_id, character_name FROM v_gallery_animas ORDER BY character_name");
        $charValues = [];
        while ($row = $char_res->fetch_assoc()) {
            // associative values (id => name) so dropdowns show names
            $charValues[$row['character_id']] = $row['character_name'];
        }
        $options['character_id'] = ['label' => 'Characters', 'values' => $charValues, 'left' => 0];

        // Anima filter
        $anima_res = $this->mysqli->query("SELECT DISTINCT anima_name FROM v_gallery_animas ORDER BY anima_name");
        $animaValues = [];
        while ($row = $anima_res->fetch_assoc()) {
            $animaValues[] = $row['anima_name'];
        }
        $options['anima_name'] = ['label' => 'Animas', 'values' => $animaValues, 'left' => 75];

        // Style filter
        $style_res = $this->mysqli->query("SELECT DISTINCT style FROM v_gallery_animas ORDER BY style");
        $styleValues = [];
        while ($row = $style_res->fetch_assoc()) {
            $styleValues[] = $row['style'];
        }
        $options['style'] = ['label' => 'Styles', 'values' => $styleValues, 'left' => 150];

        return $options;
    }

    protected function getWhereClause(): string {
        $clauses = [];
        if (($char = $this->filters['character_id'] ?? 'all') !== 'all') {
            $clauses[] = "character_id=" . intval($char);
        }
        if (($anima = $this->filters['anima_name'] ?? 'all') !== 'all') {
            $clauses[] = "anima_name='" . $this->mysqli->real_escape_string($anima) . "'";
        }
        if (($style = $this->filters['style'] ?? 'all') !== 'all') {
            $clauses[] = "style='" . $this->mysqli->real_escape_string($style) . "'";
        }
        return $clauses ? "WHERE " . implode(" AND ", $clauses) : '';
    }

    protected function getBaseQuery(): string {
        return "v_gallery_animas";
    }



    /*
    protected function renderItem(array $row): string {
        $filename  = htmlspecialchars($row['filename']);
        $prompt    = htmlspecialchars($row['prompt']);
        $anima     = htmlspecialchars($row['anima_name']);
        $character = htmlspecialchars($row['character_name']);
        $style     = htmlspecialchars($row['style']);

        return <<<HTML
<div class="img-wrapper">
    <img src="{$filename}" alt="">
    <div class="caption">
        {$prompt}<br>
        <strong>Anima:</strong> {$anima}<br>
        <strong>Character:</strong> {$character}<br>
        <strong>Style:</strong> {$style}
    </div>
</div>
HTML;
    }
     */





    protected function getCaptionFields(): array {
	    return [
	        'Entity ID'    => 'entity_id',
        'Frame ID'     => 'frame_id',
        'Anima' => 'anima_name',
        'Type'  => 'anima_type',
        'Style' => 'style',
    ];
    }

    protected function getGalleryEntity(): string {
        return "animas";
    }

    protected function getGalleryTitle(): string {
        return "Animas Gallery";
    }

    protected function getToggleButtonLeft(): int {
        return 225; // adjust so the grid toggle button sits after the filters
    }

}



