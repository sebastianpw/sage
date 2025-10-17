<?php
namespace App\Gallery;

require_once "AbstractGallery.php";

class LocationsGallery extends AbstractGallery {

    // ← include all filters here
    protected function getFiltersFromRequest(): array {
	    return [
	'location_name' => $_GET['location_name'] ?? 'all',
            'location_type' => $_GET['location_type'] ?? 'all',
            'style'         => $_GET['style'] ?? 'all', // ← added style
        ];
    }

    protected function getFilterOptions(): array {
        $options = [];


                                                             // Distinct location names
        $options['location_name'] = [                               'label'  => 'Names',
            'values' => $this->fetchDistinct('location_name'),
            'left'   => 0
        ];


        // Distinct location types
        $options['location_type'] = [
            'label'  => 'Types',
            'values' => $this->fetchDistinct('location_type'),
            'left'   => 55
        ];

        // Distinct styles
        $options['style'] = [
            'label'  => 'Styles',
            'values' => $this->fetchDistinct('style'),
            'left'   => 110
        ];

        return $options;
    }

    protected function getWhereClause(): string {
        $clauses = [];
        foreach ($this->filters as $key => $val) {
            if ($val !== 'all') {
		    $col = match($key) {
		'location_name' => 'location_name', 
                    'location_type' => 'location_type', // ← fix the key here
                    'style'         => 'style',
                };
                $clauses[] = "$col='" . $this->mysqli->real_escape_string($val) . "'";
            }
        }
        return $clauses ? "WHERE " . implode(" AND ", $clauses) : '';
    }

    protected function getBaseQuery(): string {
        return "v_gallery_locations";
    }




    /*
    protected function renderItem(array $row): string {
        $filename = htmlspecialchars($row['filename']);
        $prompt   = htmlspecialchars($row['prompt']);
        $name     = htmlspecialchars($row['location_name']);
        $type     = htmlspecialchars($row['location_type']);
        $style    = htmlspecialchars($row['style']); // optional display

        return <<<HTML
<div class="img-wrapper">
    <img src="{$filename}" alt="">
    <div class="caption">
        {$prompt}<br>
        <strong>Location:</strong> {$name}<br>
        <strong>Type:</strong> {$type}<br>
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
        'Location'   => 'location_name',
        'Type'       => 'location_type',
        'Style'      => 'style',
    ];
}


    protected function getGalleryEntity(): string {
        return "locations";
    }

    protected function getGalleryTitle(): string {
        return "Locations Gallery";
    }

    protected function getToggleButtonLeft(): int {
        return 165; // sits just after the filter select
    }
}


