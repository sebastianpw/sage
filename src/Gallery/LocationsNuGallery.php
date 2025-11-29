<?php
// src/Gallery/LocationsNuGallery.php
namespace App\Gallery;
require_once __DIR__ . '/AbstractNuGallery.php';
class LocationsNuGallery extends AbstractNuGallery {
    protected function getFiltersFromRequest(): array {
        return [
            'location_name' => $_GET['location_name'] ?? 'all',
            'location_type' => $_GET['location_type'] ?? 'all',
            'style'         => $_GET['style'] ?? 'all',
        ];
    }
    protected function getFilterOptions(): array {
        $options = [];
        $options['location_name'] = [
            'label'  => 'Names',
            'values' => $this->fetchDistinct('location_name'),
            'left'   => 0
        ];
        $options['location_type'] = [
            'label'  => 'Types',
            'values' => $this->fetchDistinct('location_type'),
            'left'   => 55
        ];
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
                    'location_type' => 'location_type',
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
    protected function getCaptionFields(): array {
        return [
            'Entity ID' => 'entity_id',
            'Frame ID'  => 'frame_id',
            'Location'  => 'location_name',
            'Type'      => 'location_type',
            'Style'     => 'style',
        ];
    }
    protected function getGalleryEntity(): string {
        return "locations";
    }
    protected function getGalleryTitle(): string {
        return "Locations Gallery";
    }
    protected function getToggleButtonLeft(): int {
        return 165;
    }
}
