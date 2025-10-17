<?php
namespace App\Gallery;

class CharacterPosesGallery extends AbstractGallery {

    protected function getFiltersFromRequest(): array {
        return [
            'character' => $_GET['character'] ?? 'all',
            'pose'      => $_GET['pose'] ?? 'all',
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
            'pose' => [
                'label'  => 'Poses',
                'values' => $this->fetchDistinct('pose_name'),
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
        if ($this->filters['pose'] !== 'all') {
            $clauses[] = "pose_name LIKE '%" . $this->mysqli->real_escape_string($this->filters['pose']) . "%'";
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
        return "v_gallery_character_poses";
    }

    protected function getCaptionFields(): array {
	    return [

'Frame ID' => 'frame_id',
        'Character Pose ID' => 'character_pose_id',
        'File Name' => 'filename',


            'Character' => 'character_name',
            'Pose'      => 'pose_name',
            'Angle'     => 'angle_name',
	    'Style'     => 'style',




        ];
    }

    protected function getGalleryEntity(): string {
        return "character_poses";
    }

    protected function getGalleryTitle(): string {
        return "Character Poses Gallery";
    }

    protected function getToggleButtonLeft(): int {
        return 220; // adjust UI position
    }
}


