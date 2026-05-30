<?php
namespace App\Gallery;

class AnimaticsGallery extends AbstractNuGallery {

    protected function getGalleryEntity(): string {
        return "animatics";
    }

    protected function getGalleryTitle(): string {
        return "Animatics Frames";
    }

    // Override to match the specific filename requested
    protected function getGalleryUrl(): string {
        return 'animatics_gallery.php';
    }
    
    protected function getCrudUrl(): string {
        return  '<h2><a href="view_video_admin.php">🎥</a></h2>';
    }



    protected function getToggleButtonLeft(): int {
        return 220;
    }

    protected function getFiltersFromRequest(): array {
        return [
            'map_run' => $_GET['map_run'] ?? 'all'
        ];
    }

    protected function getFilterOptions(): array {
        $options = [];
        // Filter by entity_type = 'animatics' or 'videos' to capture extractions
        $sql = "SELECT id, note, created_at 
                FROM map_runs 
                WHERE entity_type IN ('animatics', 'videos') 
                ORDER BY id DESC LIMIT 50";
        $result = $this->mysqli->query($sql);
        while($row = $result->fetch_assoc()) {
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
            $parts = explode(':', $selected);
            $mapRunId = intval($parts[0]);
            if ($mapRunId > 0) {
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
            'Description' => 'description'
        ];
    }

    protected function getBaseQuery(): string {
        return "v_gallery_animatics";
    }
}
