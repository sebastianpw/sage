<?php
namespace App\Gallery;

class SketchesNuGallery extends AbstractNuGallery {

    protected function getGalleryEntity(): string {
        return "sketches";
    }

    protected function getGalleryTitle(): string {
        return "Sketches";
    }

    protected function getToggleButtonLeft(): int {
        // Adjust button position for the wide dropdown
        return 220;
    }

    protected function getFiltersFromRequest(): array {
        return [
            'map_run' => $_GET['map_run'] ?? 'all'
        ];
    }

    protected function getFilterOptions(): array {
        $options = [];
        
        // Filter by entity_type = 'sketches' so we don't see runs for other entities
        $sql = "SELECT id, note, created_at 
                FROM map_runs 
                WHERE entity_type = 'sketches' 
                ORDER BY id DESC LIMIT 50";
                
        $result = $this->mysqli->query($sql);
        
        while($row = $result->fetch_assoc()) {
            // Format: "123: Note text (Date)"
            $note = $row['note'] ? substr($row['note'], 0, 20) . '...' : 'No Note';
            $date = date('m-d H:i', strtotime($row['created_at']));
            $options[] = "{$row['id']}: {$note} ($date)";
        }

        return [
            'map_run' => [
                'label'  => 'Batch Run',
                'values' => $options,
                'left'   => 0
            ]
        ];
    }

    protected function getWhereClause(): string {
        $selected = $this->filters['map_run'] ?? 'all';
        
        if ($selected !== 'all') {
            // Extract the ID from the string "123: Note..."
            $parts = explode(':', $selected);
            $mapRunId = intval($parts[0]);
            
            if ($mapRunId > 0) {
                // Relies on the view having map_run_id
                return "WHERE map_run_id = " . $mapRunId;
            }
        }
        
        return "";
    }

    protected function getCaptionFields(): array {
        return [
            'Entity ID'   => 'entity_id',
            'Map Run'     => 'map_run_id',
            'Frame ID'    => 'frame_id',
            'Name'        => 'name',
            'Description' => 'description',
            'Style'       => 'style'
        ];
    }

    protected function getBaseQuery(): string {
        return "v_gallery_sketches";
    }

    protected function getOrderBy(): string {
        return 'frame_id DESC';
    }
}
