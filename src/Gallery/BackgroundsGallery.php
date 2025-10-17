<?php
namespace App\Gallery;

require_once "AbstractGallery.php";

class BackgroundsGallery extends AbstractGallery {

    protected function getFiltersFromRequest(): array {
        return [
            'location_id' => $_GET['location_id'] ?? 'all',
            'type'        => $_GET['type'] ?? 'all',
            'style'       => $_GET['style'] ?? 'all',
        ];
    }

    protected function getFilterOptions(): array {
        $options = [];

        // Location options
        $loc_res = $this->mysqli->query("SELECT DISTINCT location_id, location_name FROM v_gallery_backgrounds ORDER BY location_name");
        $locValues = [];
        while ($row = $loc_res->fetch_assoc()) {
            $locValues[$row['location_id']] = $row['location_name'];
        }
        $options['location_id'] = ['label' => 'Locations', 'values' => $locValues, 'left' => 0];

        // Type options
        $type_res = $this->mysqli->query("SELECT DISTINCT background_type FROM v_gallery_backgrounds ORDER BY background_type");
        $typeValues = [];
        while ($row = $type_res->fetch_assoc()) {
            $typeValues[] = $row['background_type'];
        }
        $options['type'] = ['label' => 'Types', 'values' => $typeValues, 'left' => 75];

        // Style options
        $style_res = $this->mysqli->query("SELECT DISTINCT style FROM v_gallery_backgrounds ORDER BY style");
        $styleValues = [];
        while ($row = $style_res->fetch_assoc()) {
            $styleValues[] = $row['style'];
        }
        $options['style'] = ['label' => 'Styles', 'values' => $styleValues, 'left' => 150];

        return $options;
    }

    protected function getWhereClause(): string {
        $clauses = [];
        if (($loc = $this->filters['location_id'] ?? 'all') !== 'all') {
            $clauses[] = "location_id=" . intval($loc);
        }
        if (($type = $this->filters['type'] ?? 'all') !== 'all') {
            $clauses[] = "background_type='" . $this->mysqli->real_escape_string($type) . "'";
        }
        if (($style = $this->filters['style'] ?? 'all') !== 'all') {
            $clauses[] = "style='" . $this->mysqli->real_escape_string($style) . "'";
        }
        return $clauses ? "WHERE " . implode(" AND ", $clauses) : '';
    }

    protected function getBaseQuery(): string {
        return "v_gallery_backgrounds";
    }


    /*
    protected function renderItem(array $row): string {
        $filename = htmlspecialchars($row['filename']);
        $prompt = htmlspecialchars($row['prompt']);
        $background = htmlspecialchars($row['background_name']);
        $type = htmlspecialchars($row['background_type']);
        $location = htmlspecialchars($row['location_name']);
        $style = htmlspecialchars($row['style']);

        return <<<HTML
<div class="img-wrapper">
    <img src="{$filename}" alt="">
    <div class="caption">
        {$prompt}<br>
        <strong>Background:</strong> {$background}<br>
        <strong>Type:</strong> {$type}<br>
        <strong>Location:</strong> {$location}<br>
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
        'Background' => 'background_name',
        'Type'       => 'background_type',
        'Style'      => 'style',
    ];
    }

protected function getGalleryEntity(): string {
        return "backgrounds";
    }

    protected function getGalleryTitle(): string {
        return "Backgrounds Gallery";
    }

    protected function getToggleButtonLeft(): int {
        return 225; // adjust based on filter select widths
    }

}



